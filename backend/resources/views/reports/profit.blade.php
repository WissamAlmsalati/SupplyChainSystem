@extends('reports.layout', ['subtitle' => 'الفترة '.$report['period']['from'].' → '.$report['period']['to']])
@section('content')
@php
  $money = fn ($v) => number_format((float) $v, 2);
  $pct = fn ($v) => $v === null ? '—' : $v.'%';
  $s = $report['summary'];
@endphp
<table class="cards"><tr>
  <td><div class="l">إيراد البضاعة</div><div class="v">{{ $money($s['revenue']) }}</div></td>
  <td><div class="l">تكلفة البضاعة</div><div class="v">{{ $money($s['cost']) }}</div></td>
  <td><div class="l">مجمل الربح</div><div class="v {{ $s['gross_profit'] < 0 ? 'neg' : 'pos' }}">{{ $money($s['gross_profit']) }}</div></td>
  <td><div class="l">هامش الربح</div><div class="v">{{ $pct($s['margin_pct']) }}</div></td>
</tr><tr>
  <td><div class="l">الطلبات / الوحدات</div><div class="v">{{ $s['orders'] }} / {{ $s['units'] }}</div></td>
  <td><div class="l">قيمة المرتجعات</div><div class="v">{{ $money($s['returned_value']) }}</div></td>
  <td><div class="l">خسارة التالف (بالتكلفة)</div><div class="v neg">{{ $money($s['damaged_loss']) }}</div></td>
  <td><div class="l">رسوم التوصيل (خارج الربح)</div><div class="v">{{ $money($s['delivery_fees']) }}</div></td>
</tr></table>
@if($s['uncosted_lines'] > 0)
<p class="muted">{{ $s['uncosted_lines'] }} سطر بيع بلا تكلفة مسجّلة، إيرادها {{ $money($s['uncosted_revenue']) }} د.ل. هذه السطور خارج حساب التكلفة والربح والهامش.</p>
@endif

@foreach([['by_product', 'المنتجات'], ['by_category', 'التصنيفات'], ['by_customer', 'الزبائن'], ['by_city', 'المدن']] as [$key, $heading])
<h2>{{ $heading }}</h2>
<table class="grid"><thead><tr>
  <th>{{ $key === 'by_product' ? 'المنتج' : 'الاسم' }}</th>@if($key === 'by_product')<th>الحجم</th>@endif
  <th class="num">الوحدات</th><th class="num">الإيراد</th><th class="num">التكلفة</th><th class="num">الربح</th><th class="num">الهامش</th>
</tr></thead><tbody>
@forelse($report[$key] as $r)
<tr>
  <td>{{ $r['product'] ?? $r['name'] }}@if(! $r['cost_known']) <span class="muted">(تكلفة ناقصة)</span>@endif</td>@if($key === 'by_product')<td>{{ $r['variant'] }}</td>@endif
  <td class="num">{{ $r['units'] }}</td><td class="num">{{ $money($r['revenue']) }}</td><td class="num">{{ $money($r['cost']) }}</td>
  <td class="num {{ $r['profit'] < 0 ? 'neg' : 'pos' }}">{{ $money($r['profit']) }}</td><td class="num">{{ $pct($r['margin_pct']) }}</td>
</tr>
@empty<tr><td colspan="7" class="muted">لا توجد مبيعات في الفترة</td></tr>
@endforelse
</tbody></table>
@endforeach
@endsection
