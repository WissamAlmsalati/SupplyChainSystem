@use('App\Support\ReportFormat', 'F')
@extends('reports.layout')
@section('content')
@php $s = $report['summary']; @endphp
@include('reports._period', ['period' => $report['period'], 'facts' => ['المستودع' => $report['warehouse']['name'] ?? 'كل المستودعات']])
@include('reports._figures', ['figures' => [
  ['أصناف في المخزون', F::count($s['lines'])],
  ['وحدات', F::count($s['units'])],
  ['قيمة المخزون بسعر البيع', F::money($s['value'])],
  ['أصناف عند الحد أو تحته', $s['low_stock'], 'الحد '.$report['low_stock_at'].'، منها '.$s['out_of_stock'].' نافدة'],
]])

<h2>حركة المخزون في الفترة</h2>
<table class="data"><thead><tr><th>نوع الحركة</th><th class="num w14">عدد الحركات</th><th class="num w14">وارد</th><th class="num w14">صادر</th></tr></thead><tbody>
@forelse($report['movements'] as $m)<tr><td>{{ $m['label'] }}</td><td class="num">{{ $m['count'] }}</td><td class="num">{{ F::count($m['in']) }}</td><td class="num">{{ F::count($m['out']) }}</td></tr>
@empty<tr><td colspan="4" class="empty">لا توجد حركات في هذه الفترة</td></tr>@endforelse
</tbody></table>

<h2>أصناف تحتاج تزويداً</h2>
<table class="data"><thead><tr><th>المستودع</th><th>المنتج</th><th class="num w14">الكمية</th></tr></thead><tbody>
@forelse($report['low_stock'] as $r)<tr><td>{{ $r['warehouse'] }}</td><td>{{ $r['product'] }} <span class="quiet">{{ $r['variant'] }}</span></td><td class="num">{{ $r['quantity'] }}</td></tr>
@empty<tr><td colspan="3" class="empty">كل الأصناف فوق الحد</td></tr>@endforelse
</tbody></table>

<h2>أرصدة المخزون</h2>
<table class="data"><thead><tr><th>المستودع</th><th>المنتج</th><th>الرمز</th><th class="num w14">الكمية</th><th class="num w14">السعر</th><th class="num w14">القيمة</th></tr></thead><tbody>
@forelse($report['levels'] as $r)<tr><td>{{ $r['warehouse'] }}</td><td>{{ $r['product'] }} <span class="quiet">{{ $r['variant'] }}</span></td><td dir="ltr" style="text-align:right;" class="small">{{ $r['sku'] }}</td><td class="num">{{ $r['quantity'] }}</td><td class="num">{{ F::money($r['price']) }}</td><td class="num">{{ F::money($r['value']) }}</td></tr>
@empty<tr><td colspan="6" class="empty">لا يوجد مخزون</td></tr>@endforelse
@if(count($report['levels']) > 0)<tr class="sum"><td colspan="3">المجموع</td><td class="num">{{ F::count($s['units']) }}</td><td></td><td class="num">{{ F::money($s['value']) }}</td></tr>@endif
</tbody></table>
@endsection
