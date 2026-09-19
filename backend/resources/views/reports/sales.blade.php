@use('App\Support\ReportFormat', 'F')
@extends('reports.layout', ['subtitle' => $report['group_by'] === 'month' ? 'تجميع شهري' : 'تجميع يومي'])
@section('content')
@php $s = $report['summary']; @endphp
@include('reports._period', ['period' => $report['period']])
@include('reports._figures', ['figures' => [
  ['الطلبات', F::count($s['orders']), $s['cancelled'].' ملغاة خارج الحساب'],
  ['الإيرادات', F::money($s['revenue']), 'منها توصيل '.F::money($s['delivery_fees'])],
  ['المرتجعات', F::money($s['returned'] ?? 0), 'صافي الإيراد '.F::money($s['net_revenue'] ?? $s['revenue'])],
  ['متوسط الطلب', F::money($s['avg_order']), F::count($s['items_sold']).' قطعة مباعة'],
  ['المحصَّل', F::money($s['collected'])],
  ['المسترد للزبائن', F::money($s['refunded'] ?? 0)],
  ['المتبقي على الزبائن', F::money($s['outstanding'])],
]])

<h2>{{ $report['group_by'] === 'month' ? 'المبيعات حسب الشهر' : 'المبيعات حسب اليوم' }}</h2>
<table class="data"><thead><tr><th>الفترة</th><th class="num w18">الطلبات</th><th class="num w18">الإيرادات</th></tr></thead><tbody>
@forelse($report['series'] as $r)<tr><td dir="ltr" style="text-align:right;">{{ str_replace('-', '/', $r['bucket']) }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ F::money($r['revenue']) }}</td></tr>
@empty<tr><td colspan="3" class="empty">لا توجد طلبات في هذه الفترة</td></tr>@endforelse
@if(count($report['series']) > 1)<tr class="sum"><td>المجموع</td><td class="num">{{ $s['orders'] }}</td><td class="num">{{ F::money($s['revenue']) }}</td></tr>@endif
</tbody></table>

<h2>الطلبات حسب الحالة</h2>
<table class="data"><thead><tr><th>الحالة</th><th class="num w18">الطلبات</th><th class="num w18">المبلغ</th></tr></thead><tbody>
@forelse($report['by_status'] as $r)<tr><td>{{ $r['label'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ F::money($r['amount']) }}</td></tr>
@empty<tr><td colspan="3" class="empty">لا توجد طلبات</td></tr>@endforelse
</tbody></table>

<h2>أعلى المنتجات مبيعاً</h2>
<table class="data"><thead><tr><th style="width:7%;">م</th><th>المنتج</th><th class="num w18">الكمية</th><th class="num w18">الإيرادات</th></tr></thead><tbody>
@forelse($report['top_products'] as $i => $r)<tr><td>{{ $i + 1 }}</td><td>{{ $r['product'] }} <span class="quiet">{{ $r['variant'] }}</span></td><td class="num">{{ $r['quantity'] }}</td><td class="num">{{ F::money($r['revenue']) }}</td></tr>
@empty<tr><td colspan="4" class="empty">لا توجد مبيعات</td></tr>@endforelse
</tbody></table>

<h2>أعلى الزبائن</h2>
<table class="data"><thead><tr><th style="width:7%;">م</th><th>الزبون</th><th class="num w18">الطلبات</th><th class="num w18">الإيرادات</th></tr></thead><tbody>
@forelse($report['top_customers'] as $i => $r)<tr><td>{{ $i + 1 }}</td><td>{{ $r['name'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ F::money($r['revenue']) }}</td></tr>
@empty<tr><td colspan="4" class="empty">لا يوجد زبائن في هذه الفترة</td></tr>@endforelse
</tbody></table>

<h2>المناديب</h2>
<table class="data"><thead><tr><th>المندوب</th><th class="num w18">الطلبات</th><th class="num w18">الإيرادات</th></tr></thead><tbody>
@forelse($report['by_delegate'] as $r)<tr><td>{{ $r['name'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ F::money($r['revenue']) }}</td></tr>
@empty<tr><td colspan="3" class="empty">لم تُسند طلبات لمناديب في هذه الفترة</td></tr>@endforelse
</tbody></table>

<h2>التحصيل حسب طريقة الدفع</h2>
<table class="data"><thead><tr><th>الطريقة</th><th class="num w18">عدد الدفعات</th><th class="num w18">المبلغ</th></tr></thead><tbody>
@forelse($report['payments'] as $r)<tr><td>{{ $payment_labels[$r['method']] ?? $r['method'] }}</td><td class="num">{{ $r['count'] }}</td><td class="num">{{ F::money($r['amount']) }}</td></tr>
@empty<tr><td colspan="3" class="empty">لا توجد دفعات</td></tr>@endforelse
@if(count($report['payments']) > 1)<tr class="sum"><td>المجموع</td><td class="num">{{ collect($report['payments'])->sum('count') }}</td><td class="num">{{ F::money($s['collected']) }}</td></tr>@endif
</tbody></table>
@endsection
