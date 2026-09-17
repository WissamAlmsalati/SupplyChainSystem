@extends('reports.layout', ['subtitle' => 'الفترة: '.$report['period']['from'].' → '.$report['period']['to']])
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); $s = $report['summary']; @endphp
<table class="cards">
  <tr>
    <td><div class="l">الطلبات</div><div class="v">{{ $s['orders'] }}</div></td>
    <td><div class="l">الإيرادات (د.ل)</div><div class="v">{{ $money($s['revenue']) }}</div></td>
    <td><div class="l">متوسط الطلب</div><div class="v">{{ $money($s['avg_order']) }}</div></td>
    <td><div class="l">القطع المباعة</div><div class="v">{{ $s['items_sold'] }}</div></td>
  </tr>
  <tr>
    <td><div class="l">المحصّل</div><div class="v pos">{{ $money($s['collected']) }}</div></td>
    <td><div class="l">المتبقي</div><div class="v neg">{{ $money($s['outstanding']) }}</div></td>
    <td><div class="l">رسوم التوصيل</div><div class="v">{{ $money($s['delivery_fees']) }}</div></td>
    <td><div class="l">طلبات ملغية</div><div class="v">{{ $s['cancelled'] }}</div></td>
  </tr>
</table>

<h2>{{ $report['group_by'] === 'month' ? 'حسب الشهر' : 'حسب اليوم' }}</h2>
<table class="grid"><thead><tr><th>الفترة</th><th class="num">الطلبات</th><th class="num">الإيرادات</th></tr></thead><tbody>
@forelse($report['series'] as $r)<tr><td dir="ltr" class="left">{{ $r['bucket'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ $money($r['revenue']) }}</td></tr>@empty<tr><td colspan="3" class="muted">لا توجد طلبات في الفترة</td></tr>@endforelse
</tbody></table>

<h2>حالات الطلبات</h2>
<table class="grid"><thead><tr><th>الحالة</th><th class="num">الطلبات</th><th class="num">المبلغ</th></tr></thead><tbody>
@foreach($report['by_status'] as $r)<tr><td>{{ $r['label'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ $money($r['amount']) }}</td></tr>@endforeach
</tbody></table>

<h2>أعلى المنتجات مبيعاً</h2>
<table class="grid"><thead><tr><th>المنتج</th><th>الحجم</th><th class="num">الكمية</th><th class="num">الإيرادات</th></tr></thead><tbody>
@foreach($report['top_products'] as $r)<tr><td>{{ $r['product'] }}</td><td>{{ $r['variant'] }}</td><td class="num">{{ $r['quantity'] }}</td><td class="num">{{ $money($r['revenue']) }}</td></tr>@endforeach
</tbody></table>

<h2>أعلى الزبائن</h2>
<table class="grid"><thead><tr><th>الزبون</th><th class="num">الطلبات</th><th class="num">الإيرادات</th></tr></thead><tbody>
@foreach($report['top_customers'] as $r)<tr><td>{{ $r['name'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ $money($r['revenue']) }}</td></tr>@endforeach
</tbody></table>

<h2>المناديب</h2>
<table class="grid"><thead><tr><th>المندوب</th><th class="num">الطلبات</th><th class="num">الإيرادات</th></tr></thead><tbody>
@forelse($report['by_delegate'] as $r)<tr><td>{{ $r['name'] }}</td><td class="num">{{ $r['orders'] }}</td><td class="num">{{ $money($r['revenue']) }}</td></tr>@empty<tr><td colspan="3" class="muted">—</td></tr>@endforelse
</tbody></table>

<h2>طرق الدفع</h2>
<table class="grid"><thead><tr><th>الطريقة</th><th class="num">العدد</th><th class="num">المبلغ</th></tr></thead><tbody>
@forelse($report['payments'] as $r)<tr><td>{{ $payment_labels[$r['method']] ?? $r['method'] }}</td><td class="num">{{ $r['count'] }}</td><td class="num">{{ $money($r['amount']) }}</td></tr>@empty<tr><td colspan="3" class="muted">—</td></tr>@endforelse
</tbody></table>
@endsection
