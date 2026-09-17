<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustodyEntryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\WalletTransactionType;
use App\Models\AppUser;
use App\Models\CustodyEntry;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Reports\InventoryReport;
use App\Services\Reports\LedgerStatement;
use App\Services\Reports\PdfRenderer;
use App\Services\Reports\Period;
use App\Services\Reports\SalesReport;
use App\Services\Reports\XlsxRenderer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Reports", description="Sales, custody, wallet and stock reports (JSON, PDF, Excel)")
 */
class ReportController extends BaseApiController
{
    public const CUSTODY_LABELS = [
        'order_collection' => 'تحصيل طلب',
        'wallet_collection' => 'شحن محفظة',
        'settlement' => 'تسليم للمكتب',
        'adjustment' => 'تعديل إداري',
    ];

    public const WALLET_LABELS = [
        'topup' => 'شحن',
        'payment' => 'دفع طلب',
        'refund' => 'استرجاع',
        'adjustment' => 'تعديل إداري',
    ];

    public const PAYMENT_LABELS = [
        'cash' => 'نقداً',
        'card' => 'بطاقة',
        'bank_transfer' => 'تحويل بنكي',
        'wallet' => 'المحفظة',
    ];

    public function __construct(
        private PdfRenderer $pdf,
        private XlsxRenderer $xlsx,
    ) {}

    private function format(Request $request, array $allowed = ['json', 'pdf', 'xlsx']): string
    {
        return $request->validate(['format' => ['nullable', Rule::in($allowed)]])['format'] ?? 'json';
    }

    /**
     * @OA\Get(path="/reports/sales", tags={"Reports"}, summary="Sales report for a period",
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="group_by", in="query", @OA\Schema(type="string", enum={"day","month"})),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json","pdf","xlsx"})),
     *     @OA\Response(response=200, description="Report (JSON), or a PDF / Excel download"))
     */
    public function sales(Request $request, SalesReport $sales)
    {
        $period = Period::fromRequest($request);
        $groupBy = $request->validate(['group_by' => ['nullable', Rule::in(['day', 'month'])]])['group_by'] ?? 'day';
        $data = $sales->build($period, $groupBy);
        $name = 'sales-'.$period->slug();

        return match ($this->format($request)) {
            'pdf' => $this->pdf->render('reports.sales', ['title' => 'تقرير المبيعات', 'report' => $data, 'payment_labels' => self::PAYMENT_LABELS], $name.'.pdf'),
            'xlsx' => $this->xlsx->render([
                'الملخص' => ['headers' => ['البند', 'القيمة'], 'rows' => [
                    ['الفترة', $period->label()],
                    ['عدد الطلبات', $data['summary']['orders']],
                    ['الطلبات الملغية', $data['summary']['cancelled']],
                    ['الإيرادات', $data['summary']['revenue']],
                    ['رسوم التوصيل', $data['summary']['delivery_fees']],
                    ['متوسط الطلب', $data['summary']['avg_order']],
                    ['المحصّل', $data['summary']['collected']],
                    ['المتبقي', $data['summary']['outstanding']],
                    ['القطع المباعة', $data['summary']['items_sold']],
                ]],
                'حسب الفترة' => ['headers' => [$groupBy === 'month' ? 'الشهر' : 'اليوم', 'الطلبات', 'الإيرادات'], 'rows' => $data['series']],
                'الحالات' => ['headers' => ['الحالة', 'الطلبات', 'المبلغ'], 'rows' => collect($data['by_status'])->map(fn ($r) => [$r['label'], $r['orders'], $r['amount']])],
                'أعلى المنتجات' => ['headers' => ['المنتج', 'الحجم', 'الكمية', 'الإيرادات'], 'rows' => $data['top_products']],
                'أعلى الزبائن' => ['headers' => ['الزبون', 'الطلبات', 'الإيرادات'], 'rows' => collect($data['top_customers'])->map(fn ($r) => [$r['name'], $r['orders'], $r['revenue']])],
                'المناديب' => ['headers' => ['المندوب', 'الطلبات', 'الإيرادات'], 'rows' => collect($data['by_delegate'])->map(fn ($r) => [$r['name'], $r['orders'], $r['revenue']])],
                'طرق الدفع' => ['headers' => ['الطريقة', 'العدد', 'المبلغ'], 'rows' => collect($data['payments'])->map(fn ($r) => [self::PAYMENT_LABELS[$r['method']] ?? $r['method'], $r['count'], $r['amount']])],
            ], $name.'.xlsx'),
            default => $this->jsonResponse($data),
        };
    }

    /**
     * @OA\Get(path="/reports/custody/{delegate}", tags={"Reports"}, summary="Delegate custody statement",
     *     @OA\Parameter(name="delegate", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="all", in="query", description="1 = since the beginning", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json","pdf","xlsx"})),
     *     @OA\Response(response=200, description="Statement"))
     */
    public function custody(Request $request, int $delegate, LedgerStatement $statement)
    {
        $user = AppUser::whereKey($delegate)->whereHas('userType', fn ($q) => $q->where('name', 'delegate'))->with('delegateProfile')->firstOrFail();
        $period = Period::fromRequest($request);
        $data = $statement->build(
            CustodyEntry::with('createdBy:id,name')->where('delegate_id', $user->id),
            $period,
            self::CUSTODY_LABELS,
            (string) ($user->delegateProfile?->custody_balance ?? '0'),
        );
        $data['delegate'] = $user->only(['id', 'name', 'mobile_number']);

        return $this->statementResponse($request, $data, 'كشف عهدة المندوب', 'custody-'.$user->id.'-'.$period->slug(), 'reports.custody');
    }

    /**
     * @OA\Get(path="/reports/wallet/{wallet}", tags={"Reports"}, summary="Customer wallet statement",
     *     @OA\Parameter(name="wallet", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json","pdf","xlsx"})),
     *     @OA\Response(response=200, description="Statement"))
     */
    public function wallet(Request $request, Wallet $wallet, LedgerStatement $statement)
    {
        return $this->walletStatement($request, $wallet->load('user:id,name,mobile_number'), $statement);
    }

    // Shared by the admin wallet route and the customer app's own statement.
    public function walletStatement(Request $request, Wallet $wallet, LedgerStatement $statement)
    {
        $period = Period::fromRequest($request);
        $data = $statement->build(
            WalletTransaction::with('createdBy:id,name')->where('wallet_id', $wallet->id),
            $period,
            self::WALLET_LABELS,
            (string) $wallet->balance,
        );
        $data['wallet'] = ['id' => $wallet->id, 'is_active' => (bool) $wallet->is_active];
        $data['customer'] = $wallet->user?->only(['id', 'name', 'mobile_number']);

        return $this->statementResponse($request, $data, 'كشف حساب المحفظة', 'wallet-'.$wallet->id.'-'.$period->slug(), 'reports.wallet');
    }

    private function statementResponse(Request $request, array $data, string $title, string $name, string $view)
    {
        return match ($this->format($request)) {
            'pdf' => $this->pdf->render($view, ['title' => $title, 'report' => $data], $name.'.pdf'),
            'xlsx' => $this->xlsx->render([
                'الحركات' => ['headers' => ['التاريخ', 'النوع', 'المبلغ', 'الرصيد بعدها', 'المرجع', 'ملاحظة', 'بواسطة'], 'rows' => collect($data['entries'])->map(fn ($e) => [$e['date'], $e['label'], $e['amount'], $e['balance_after'], $e['reference'], $e['note'], $e['by']])],
                'الملخص' => ['headers' => ['البند', 'القيمة'], 'rows' => [
                    ['الفترة', $data['period']['from'].' → '.$data['period']['to']],
                    ['رصيد أول الفترة', $data['opening_balance']],
                    ['إضافات', $data['credits']],
                    ['خصومات', $data['debits']],
                    ['رصيد آخر الفترة', $data['closing_balance']],
                    ['الرصيد الحالي', $data['current_balance']],
                ]],
            ], $name.'.xlsx'),
            default => $this->jsonResponse($data),
        };
    }

    /**
     * @OA\Get(path="/reports/inventory", tags={"Reports"}, summary="Stock on hand, low stock and movements",
     *     @OA\Parameter(name="warehouse_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="low_stock_at", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="format", in="query", @OA\Schema(type="string", enum={"json","pdf","xlsx"})),
     *     @OA\Response(response=200, description="Report"))
     */
    public function inventory(Request $request, InventoryReport $inventory)
    {
        $opts = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'low_stock_at' => ['nullable', 'integer', 'min:0'],
        ]);
        $period = Period::fromRequest($request);
        $data = $inventory->build($period, isset($opts['warehouse_id']) ? (int) $opts['warehouse_id'] : null, (int) ($opts['low_stock_at'] ?? 10));
        $name = 'inventory-'.$period->slug();

        return match ($this->format($request)) {
            'pdf' => $this->pdf->render('reports.inventory', ['title' => 'تقرير المخزون', 'report' => $data], $name.'.pdf'),
            'xlsx' => $this->xlsx->render([
                'الأرصدة' => ['headers' => ['المستودع', 'المنتج', 'الحجم', 'SKU', 'الكمية', 'السعر', 'القيمة'], 'rows' => $data['levels']],
                'نواقص' => ['headers' => ['المستودع', 'المنتج', 'الحجم', 'SKU', 'الكمية', 'السعر', 'القيمة'], 'rows' => $data['low_stock']],
                'الحركات' => ['headers' => ['النوع', 'العدد', 'داخل', 'خارج'], 'rows' => collect($data['movements'])->map(fn ($m) => [$m['label'], $m['count'], $m['in'], $m['out']])],
            ], $name.'.xlsx'),
            default => $this->jsonResponse($data),
        };
    }

    /**
     * @OA\Get(path="/reports/orders", tags={"Reports"}, summary="Export orders to Excel",
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Excel download"))
     */
    public function orders(Request $request)
    {
        $period = Period::fromRequest($request);
        $status = $request->validate(['status' => ['nullable', Rule::in(OrderStatus::values())]])['status'] ?? null;

        $rows = Order::with(['user:id,name,mobile_number', 'delegate:id,name', 'items'])
            ->whereBetween('placed_at', [$period->from, $period->to])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('placed_at')
            ->lazy()
            ->map(fn (Order $o) => [
                $o->order_number,
                $o->placed_at?->toDateTimeString(),
                $o->status->label(),
                $o->user?->name,
                $o->user?->mobile_number,
                $o->delivery_city,
                $o->delivery_address_name,
                $o->delegate?->name,
                (int) $o->items->sum('quantity'),
                (float) $o->subtotal,
                (float) $o->delivery_fee,
                (float) $o->total_amount,
            ]);

        return $this->xlsx->render([
            'الطلبات' => ['headers' => ['رقم الطلب', 'التاريخ', 'الحالة', 'الزبون', 'الجوال', 'المدينة', 'العنوان', 'المندوب', 'القطع', 'المجموع', 'التوصيل', 'الإجمالي'], 'rows' => $rows],
        ], 'orders-'.$period->slug().'.xlsx');
    }

    /**
     * @OA\Get(path="/orders/{id}/invoice", tags={"Reports"}, summary="Order invoice (PDF)",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="PDF download"))
     */
    public function invoice(Order $order)
    {
        return $this->invoiceFor($order);
    }

    // Shared by the dashboard route and the customer app's own-order route.
    public function invoiceFor(Order $order)
    {
        $order->load(['user:id,name,mobile_number,email', 'user.customerProfile:user_id,business_name', 'items', 'payments' => fn ($q) => $q->where('status', 'paid'), 'delegate:id,name,mobile_number']);
        $paid = round((float) $order->payments->sum('amount'), 2);

        return $this->pdf->render('reports.invoice', [
            'title' => 'فاتورة '.$order->order_number,
            'order' => $order,
            'paid' => $paid,
            'due' => $order->status === OrderStatus::Cancelled ? 0.0 : round(max((float) $order->total_amount - $paid, 0), 2),
            'payment_labels' => self::PAYMENT_LABELS,
        ], 'invoice-'.$order->order_number.'.pdf');
    }
}
