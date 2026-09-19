<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\CustodyEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\Reports\DelegatePerformanceReport;
use App\Services\Reports\InventoryReport;
use App\Services\Reports\LedgerStatement;
use App\Services\Reports\Period;
use App\Services\Reports\ProfitReport;
use App\Services\Reports\SalesReport;
use App\Support\MoneyInWords;
use App\Support\ReportFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every printed document is one family: same letterhead, same way of writing
// numbers, the reader's own words.
class PrintedDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_amounts_are_written_out_the_way_an_arabic_invoice_closes(): void
    {
        $this->assertSame('فقط ثلاثمائة وخمسة وستون ديناراً لا غير', MoneyInWords::dinars(365));
        $this->assertSame('فقط ألف وثلاثمائة وخمسة وستون ديناراً وخمسمائة درهم لا غير', MoneyInWords::dinars('1365.50'));
        $this->assertSame('فقط دينار واحد لا غير', MoneyInWords::dinars(1));
        $this->assertSame('فقط ديناران لا غير', MoneyInWords::dinars(2));
        $this->assertSame('فقط عشرة دنانير لا غير', MoneyInWords::dinars(10));
        $this->assertSame('فقط أحد عشر ديناراً لا غير', MoneyInWords::dinars(11));
        $this->assertSame('فقط مائة دينار لا غير', MoneyInWords::dinars(100));
        $this->assertSame('فقط سبعمائة وخمسون درهماً لا غير', MoneyInWords::dinars(0.75));
    }

    public function test_numbers_are_written_one_way(): void
    {
        $this->assertSame('1,250.50', ReportFormat::money(1250.5));
        $this->assertSame('(468.00)', ReportFormat::signed(-468));
        $this->assertSame('30%', ReportFormat::percent(30.0));
        $this->assertSame('94.1%', ReportFormat::percent(94.1));
        $this->assertSame('-', ReportFormat::percent(null));
        $this->assertSame('45 د', ReportFormat::minutes(45));
        $this->assertSame('3 س 20 د', ReportFormat::minutes(200));
        $this->assertSame('42 يوم 18 س', ReportFormat::minutes(42 * 1440 + 18 * 60 + 7));
        $this->assertSame('6,921', ReportFormat::count(6921));
    }

    public function test_every_document_carries_the_letterhead_and_speaks_plainly(): void
    {
        $delegate = AppUser::factory()->delegate()->create(['name' => 'سالم']);
        $order = Order::factory()->create(['status' => 'delivered', 'delegate_id' => $delegate->id, 'subtotal' => 360, 'delivery_fee' => 5, 'total_amount' => 365, 'customer_note' => 'الباب الخلفي']);
        OrderItem::create(['order_id' => $order->id, 'product_variant_id' => ProductVariant::factory()->create()->id, 'product_name' => 'بن عربي', 'variant_name' => '1 كجم', 'quantity' => 9, 'unit_price' => 40]);
        Payment::create(['order_id' => $order->id, 'amount' => 365, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now(), 'collected_by' => $delegate->id]);
        CustodyEntry::create(['delegate_id' => $delegate->id, 'type' => 'order_collection', 'amount' => 365, 'balance_after' => 365, 'reference_type' => $order->getMorphClass(), 'reference_id' => $order->id]);

        $order->load(['user.customerProfile', 'items', 'payments.collector', 'delegate', 'warehouse']);
        $invoice = view('reports.invoice', ['title' => 'فاتورة', 'order' => $order, 'paid' => 365.0, 'due' => 0.0, 'returned' => 0.0, 'refunded' => 0.0, 'payment_labels' => ['cash' => 'نقداً']])->render();

        $this->assertStringContainsString(config('company.name'), $invoice);
        $this->assertStringContainsString('INV-'.substr($order->order_number, 4), $invoice);
        $this->assertStringContainsString('فقط ثلاثمائة وخمسة وستون ديناراً لا غير', $invoice);
        $this->assertStringContainsString('مسددة بالكامل', $invoice);
        $this->assertStringContainsString('الباب الخلفي', $invoice);
        $this->assertStringContainsString('استلمتُ البضاعة', $invoice);

        $period = new Period(now()->subDays(30), now());
        $documents = [
            'invoice' => $invoice,
            'sales' => view('reports.sales', ['title' => 'تقرير المبيعات', 'report' => app(SalesReport::class)->build($period), 'payment_labels' => ['cash' => 'نقداً']])->render(),
            'profit' => view('reports.profit', ['title' => 'تقرير الأرباح', 'report' => app(ProfitReport::class)->build($period)])->render(),
            'delegates' => view('reports.delegates', ['title' => 'أداء المناديب', 'report' => app(DelegatePerformanceReport::class)->build($period)])->render(),
            'inventory' => view('reports.inventory', ['title' => 'تقرير المخزون', 'report' => app(InventoryReport::class)->build($period)])->render(),
        ];

        $statement = app(LedgerStatement::class)->build(CustodyEntry::where('delegate_id', $delegate->id), $period, ['order_collection' => 'تحصيل طلب'], '365');
        $this->assertSame('طلب رقم '.$order->id, $statement['entries'][0]['reference']);
        $documents['custody'] = view('reports.custody', ['title' => 'كشف عهدة المندوب', 'report' => $statement + ['delegate' => ['name' => 'سالم', 'mobile_number' => '0920000000']]])->render();

        foreach ($documents as $name => $html) {
            $this->assertStringContainsString('class="letterhead"', $html, $name);
            $this->assertStringContainsString(config('company.tagline'), $html, $name);
            // The reader's own words and an accountant's signs: no arrows, no long dashes, no class names.
            foreach (['→', '—', 'Order #', 'App\\Models'] as $foreign) {
                $this->assertStringNotContainsString($foreign, strip_tags($html), "{$name} contains {$foreign}");
            }
        }
    }
}
