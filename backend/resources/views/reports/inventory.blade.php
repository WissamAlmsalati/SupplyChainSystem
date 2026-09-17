@extends('reports.layout', ['subtitle' => ($report['warehouse']['name'] ?? 'كل المستودعات').' · حركات الفترة '.$report['period']['from'].' → '.$report['period']['to']])
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); $s = $report['summary']; @endphp
<table class="cards"><tr>
  <td><div class="l">أصناف</div><div class="v">{{ $s['lines'] }}</div></td>
  <td><div class="l">وحدات في المخزون</div><div class="v">{{ $s['units'] }}</div></td>
  <td><div class="l">قيمة المخزون (بسعر البيع)</div><div class="v">{{ $money($s['value']) }}</div></td>
  <td><div class="l">نواقص (≤ {{ $report['low_stock_at'] }})</div><div class="v neg">{{ $s['low_stock'] }} <span class="muted">منها {{ $s['out_of_stock'] }} صفر</span></div></td>
</tr></table>

<h2>حركات الفترة</h2>
<table class="grid"><thead><tr><th>النوع</th><th class="num">العدد</th><th class="num">داخل</th><th class="num">خارج</th></tr></thead><tbody>
@forelse($report['movements'] as $m)<tr><td>{{ $m['label'] }}</td><td class="num">{{ $m['count'] }}</td><td class="num pos">{{ $m['in'] }}</td><td class="num neg">{{ $m['out'] }}</td></tr>@empty<tr><td colspan="4" class="muted">لا توجد حركات</td></tr>@endforelse
</tbody></table>

<h2>نواقص</h2>
<table class="grid"><thead><tr><th>المستودع</th><th>المنتج</th><th>الحجم</th><th class="num">الكمية</th></tr></thead><tbody>
@forelse($report['low_stock'] as $r)<tr><td>{{ $r['warehouse'] }}</td><td>{{ $r['product'] }}</td><td>{{ $r['variant'] }}</td><td class="num neg">{{ $r['quantity'] }}</td></tr>@empty<tr><td colspan="4" class="muted">لا توجد نواقص</td></tr>@endforelse
</tbody></table>

<h2>الأرصدة</h2>
<table class="grid"><thead><tr><th>المستودع</th><th>المنتج</th><th>الحجم</th><th>SKU</th><th class="num">الكمية</th><th class="num">السعر</th><th class="num">القيمة</th></tr></thead><tbody>
@foreach($report['levels'] as $r)<tr><td>{{ $r['warehouse'] }}</td><td>{{ $r['product'] }}</td><td>{{ $r['variant'] }}</td><td dir="ltr" class="left">{{ $r['sku'] }}</td><td class="num">{{ $r['quantity'] }}</td><td class="num">{{ $money($r['price']) }}</td><td class="num">{{ $money($r['value']) }}</td></tr>@endforeach
</tbody></table>
@endsection
