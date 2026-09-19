<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Enums\WalletTransactionType;
use App\Models\AppUser;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// The only place a return is written. One transaction covers the record, the
// stock that goes back on the shelf and the money that goes back to the
// customer, so a return can never be half done.
class ReturnService
{
    public function __construct(
        private StockService $stock,
        private WalletService $wallets,
    ) {}

    /**
     * @param  array<int, array{order_item_id:int, quantity:int, condition:string}>  $lines
     */
    public function create(Order $order, array $lines, string $reason, string $refundMethod = 'wallet'): OrderReturn
    {
        $return = DB::transaction(function () use ($order, $lines, $reason, $refundMethod) {
            // Two people returning the same order at once must queue, or both
            // would pass the "not more than was delivered" check.
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->status, [OrderStatus::Delivered, OrderStatus::Received], true)) {
                throw ValidationException::withMessages(['order_id' => 'المرتجع يُسجَّل بعد تسليم الطلب فقط']);
            }

            $items = $order->items()->withSum('returnItems as returned_quantity', 'quantity')->get()->keyBy('id');

            $wanted = [];
            foreach ($lines as $i => $line) {
                $item = $items->get((int) $line['order_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(["items.$i.order_item_id" => 'الصنف ليس من هذا الطلب']);
                }
                $key = $item->id.'|'.$line['condition'];
                $wanted[$key] = [
                    'item' => $item,
                    'condition' => $line['condition'],
                    'quantity' => ($wanted[$key]['quantity'] ?? 0) + (int) $line['quantity'],
                ];
            }

            foreach ($items as $item) {
                $asked = collect($wanted)->where('item.id', $item->id)->sum('quantity');
                $left = $item->quantity - (int) $item->returned_quantity;
                if ($asked > $left) {
                    throw ValidationException::withMessages([
                        'items' => "الكمية المرتجعة من «{$item->product_name}» أكبر من المتبقي ({$left})",
                    ]);
                }
            }

            $cents = fn ($v) => (int) round((float) $v * 100);
            $valueCents = collect($wanted)->sum(fn ($w) => $w['quantity'] * $cents($w['item']->unit_price));

            // Refund only what the customer has paid beyond what they now owe:
            // an unpaid order simply owes less, and no money moves.
            $balance = $order->balanceCents();
            $dueAfter = $balance['due'] - $valueCents;
            $refundCents = min($valueCents, max(0, ($balance['paid'] - $balance['refunded']) - $dueAfter));

            if ($refundCents > 0 && $refundMethod === 'none') {
                throw ValidationException::withMessages([
                    'refund_method' => 'الزبون دفع هذه القيمة، اختر طريقة الاسترداد (المحفظة أو نقداً)',
                ]);
            }

            $return = $order->returns()->create([
                'reason' => $reason,
                'total_value' => $valueCents / 100,
                'refund_amount' => $refundCents / 100,
                'refund_method' => $refundCents > 0 ? $refundMethod : 'none',
                // A wallet refund is paid right here; cash waits for payRefund().
                'refund_paid_at' => $refundCents > 0 && $refundMethod === 'wallet' ? now() : null,
                'refund_paid_by' => $refundCents > 0 && $refundMethod === 'wallet' ? auth()->id() : null,
                'created_by' => auth()->id(),
            ]);

            foreach ($wanted as $w) {
                $warehouseId = $w['condition'] === 'restock' ? $this->warehouseFor($order, $w['item']->product_variant_id) : null;

                $return->items()->create([
                    'order_item_id' => $w['item']->id,
                    'quantity' => $w['quantity'],
                    'unit_price' => $w['item']->unit_price,
                    'condition' => $w['condition'],
                    'warehouse_id' => $warehouseId,
                ]);

                // Damaged goods never become sellable again, so they do not
                // touch inventory; the return row is their record.
                if ($warehouseId) {
                    $this->stock->adjust($warehouseId, $w['item']->product_variant_id, $w['quantity'], StockMovementType::Return, $return, "مرتجع الطلب {$order->order_number}");
                }
            }

            if ($refundCents > 0 && $refundMethod === 'wallet') {
                $this->wallets->credit(
                    $this->wallets->walletFor($order->user),
                    $refundCents / 100,
                    WalletTransactionType::Refund,
                    $return,
                    "مرتجع الطلب {$order->order_number}",
                );
            }

            return $return;
        });

        $message = 'سُجّل مرتجع بقيمة '.number_format((float) $return->total_value, 2)." د.ل على الطلب {$order->order_number}";
        if ((float) $return->refund_amount > 0) {
            $message .= $return->refund_method === 'wallet'
                ? '، وأُعيد '.number_format((float) $return->refund_amount, 2).' د.ل إلى محفظتك'
                : '، ويُسلَّم لك '.number_format((float) $return->refund_amount, 2).' د.ل نقداً';
        }
        Notification::sendTo([$order->user_id], 'مرتجع على طلبك', $message, "/orders/{$order->id}", 'order');

        return $return->load(['items.orderItem', 'items.warehouse:id,name', 'createdBy:id,name']);
    }

    /**
     * Records that a cash refund was handed to the customer: by the office, or
     * by a delegate out of the cash they hold (their custody drops by it).
     */
    public function payRefund(OrderReturn $return, ?AppUser $fromDelegate = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $fromDelegate) {
            $return = OrderReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->refund_method !== 'cash' || (float) $return->refund_amount <= 0) {
                throw ValidationException::withMessages(['return' => 'هذا المرتجع ليس له استرداد نقدي']);
            }
            if ($return->refund_paid_at !== null) {
                throw ValidationException::withMessages(['return' => 'سُلّم هذا الاسترداد من قبل']);
            }

            if ($fromDelegate) {
                app(CustodyService::class)->payRefund($fromDelegate, $return->load('order'));
            }

            $return->update([
                'refund_paid_at' => now(),
                'refund_paid_by' => auth()->id(),
                'refund_paid_from_delegate_id' => $fromDelegate?->id,
            ]);

            return $return;
        });
    }

    // Back to the shelf it came from; the first warehouse when the sale left no trace.
    private function warehouseFor(Order $order, int $variantId): int
    {
        $from = StockMovement::where('reference_type', $order->getMorphClass())
            ->where('reference_id', $order->id)
            ->where('product_variant_id', $variantId)
            ->where('type', StockMovementType::Sale->value)
            ->orderBy('quantity_change')
            ->value('warehouse_id');

        $warehouseId = $from ?? Warehouse::orderBy('id')->value('id');

        if (! $warehouseId) {
            throw ValidationException::withMessages(['items' => 'لا يوجد مخزن لإعادة الأصناف إليه']);
        }

        return (int) $warehouseId;
    }
}
