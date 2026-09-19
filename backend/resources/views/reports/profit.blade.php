@use('App\Support\ReportFormat', 'F')
@extends('reports.layout')
@section('content')
@php $s = $report['summary']; @endphp
@include('reports._period', ['period' => $report['period']])
@include('reports._figures', ['figures' => [
  ['إيراد البضاعة', F::money($s['revenue']), F::count($s['orders']).' طلب، '.F::count($s['units']).' وحدة'],
  ['تكلفة البضاعة', F::money($s['cost'])],
  ['مجمل الربح', F::signed($s['gross_profit'])],
  ['هامش الربح', F::percent($s['margin_pct'])],
  ['قيمة المرتجعات', F::money($s['returned_value'])],
  ['خسارة التالف بالتكلفة', F::money($s['damaged_loss'])],
  ['رسوم التوصيل', F::money($s['delivery_fees']), 'دخل خارج ربح البضاعة'],
]])
@if($s['uncosted_lines'] > 0)
<table style="width:100%; border-collapse:collapse; margin-top:4mm;"><tr><td style="border-right:0.8mm solid #0b3b38; padding:1mm 3mm;">
  <span class="fv">{{ $s['uncosted_lines'] }} سطر بيع بلا تكلفة مسجلة، إيرادها {{ F::money($s['uncosted_revenue']) }} {{ config('company.currency') }}. هذه السطور محسوبة في الإيراد وخارج التكلفة والربح والهامش.</span>
</td></tr></table>
@endif

@foreach([['by_product', 'الربح حسب المنتج', 'المنتج'], ['by_category', 'الربح حسب التصنيف', 'التصنيف'], ['by_customer', 'الربح حسب الزبون', 'الزبون'], ['by_city', 'الربح حسب المدينة', 'المدينة']] as [$key, $heading, $column])
<h2>{{ $heading }}</h2>
<table class="data"><thead><tr>
  <th>{{ $column }}</th><th class="num w12">الوحدات</th><th class="num w12">الإيراد</th><th class="num w12">التكلفة</th><th class="num w12">الربح</th><th class="num w12">الهامش</th>
</tr></thead><tbody>
@forelse($report[$key] as $r)
<tr>
  <td>{{ $r['product'] ?? $r['name'] }}@if(! empty($r['variant'])) <span class="quiet">{{ $r['variant'] }}</span>@endif @if(! $r['cost_known'])<span class="quiet small">(تكلفة ناقصة)</span>@endif</td>
  <td class="num">{{ $r['units'] }}</td><td class="num">{{ F::money($r['revenue']) }}</td><td class="num">{{ F::money($r['cost']) }}</td>
  <td class="num">{{ F::signed($r['profit']) }}</td><td class="num">{{ F::percent($r['margin_pct']) }}</td>
</tr>
@empty<tr><td colspan="6" class="empty">لا توجد مبيعات في هذه الفترة</td></tr>
@endforelse
</tbody></table>
@endforeach
@endsection
