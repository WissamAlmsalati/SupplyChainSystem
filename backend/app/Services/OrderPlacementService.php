<?php

namespace App\Services;

use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\AppUser;
use App\Models\Cart;
use App\Models\Notification;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Single path for creating orders: server-side prices, address/product
// snapshots, stock deduction, delegate auto-assignment and admin notification.
class OrderPlacementService
{
    public function __construct(
        private StockService $stock,
        private DelegateAssignmentService $delegates,
        private WalletService $wallets,
    ) {}

    /**
     * @param  array<int, array{product_variant_id:int, quantity:int}>  $items
     */
    public function place(
        AppUser $customer,
        Address $address,
        array $items,
        OrderSource $source,
        ?Cart $cart = null,
        ?int $delegateId = null,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?string $note = null,
    ): Order {
        if (! in_array($paymentMethod, [PaymentMethod::Cash, PaymentMethod::Wallet], true)) {
            throw ValidationException::withMessages(['payment_method' => 'طريقة الدفع غير مدعومة']);
        }

        $quantities = [];
        foreach ($items as $item) {
            $variantId = (int) $item['product_variant_id'];
            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + (int) $item['quantity'];
        }

        if (! $quantities) {
            throw ValidationException::withMessages(['items' => 'السلة فارغة']);
        }

        // A size is sellable only while its product is: hiding or deleting a
        // product must stop its sizes being ordered from an old cart or the API.
        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($quantities))
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->get()
            ->keyBy('id');

        $missing = array_diff(array_keys($quantities), $variants->keys()->all());
        if ($missing) {
            throw ValidationException::withMessages([
                'items' => 'بعض المنتجات غير متاحة: '.implode(', ', $missing),
            ]);
        }

        $address->loadMissing('deliveryZone');

        // An order from the app to an address with no zone, or a zone that was
        // switched off, used to go through with a delivery fee of zero. The
        // office may still send one there on purpose from the dashboard.
        if ($source === OrderSource::App && ! $address->deliveryZone?->is_active) {
            throw ValidationException::withMessages(['address_id' => 'هذا العنوان خارج نطاق التوصيل حالياً، اختر عنواناً آخر. سنبلغك فور بدء التوصيل إلى منطقتك.']);
        }

        $order = DB::transaction(function () use ($customer, $address, $quantities, $variants, $source, $cart, $delegateId, $paymentMethod, $note) {
            $lines = collect($quantities)->map(fn (int $qty, int $variantId) => [
                'product_variant_id' => $variantId,
                'product_name' => $variants[$variantId]->product?->name ?? '',
                'variant_name' => $variants[$variantId]->name,
                'quantity' => $qty,
                'unit_price' => $variants[$variantId]->price,
                // Kept like the price is: profit is measured against what it cost then.
                'unit_cost' => $variants[$variantId]->cost_price,
            ])->values();

            $subtotal = round($lines->sum(fn ($l) => $l['quantity'] * (float) $l['unit_price']), 2);
            $deliveryFee = (float) ($address->deliveryZone?->delivery_price ?? 0);

            $order = new Order([
                'user_id' => $customer->id,
                'delegate_id' => $delegateId,
                'cart_id' => $cart?->id,
                'source' => $source,
                'customer_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $subtotal + $deliveryFee,
            ]);
            $order->fillDeliveryAddress($address)->save();
            $order->items()->createMany($lines->all());

            $warehouseId = $this->stock->drainForOrder($order, $quantities, $address->deliveryZone?->warehouse_id);
            $order->forceFill(['warehouse_id' => $warehouseId])->saveQuietly();

            // Wallet orders are paid in full now; a short balance rolls everything back.
            if ($paymentMethod === PaymentMethod::Wallet) {
                $this->wallets->payOrder($order, $customer);
            }

            return $order;
        });

        if (! $order->delegate_id) {
            $this->delegates->assignNearest($order);
        }

        Notification::notifyAdmins(
            'طلب جديد',
            "تم إنشاء طلب جديد برقم {$order->order_number}",
            "/orders/{$order->id}",
            'order'
        );

        return $order;
    }
}
