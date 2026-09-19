@use('App\Support\ReportFormat', 'F')
@extends('reports.layout')
@section('content')
@php $s = $report['summary']; @endphp
@include('reports._period', ['period' => $report['period']])
@include('reports._figures', ['figures' => [
  ['طلبات مسلَّمة', $s['delivered'], 'من '.$s['assigned'].' مسندة، '.$s['cancelled'].' ملغاة'],
  ['نسبة النجاح', F::percent($s['success_rate']), 'من الطلبات المنتهية'],
  ['متوسط زمن التوصيل', F::minutes($s['avg_delivery_minutes']), 'من الخروج إلى التسليم'],
  ['عهدة عند المناديب اليوم', F::money($s['custody_held']), 'محصَّل في الفترة '.F::money($s['cash_collected'])],
]])

<h2>أداء كل مندوب</h2>
<table class="data"><thead><tr>
  <th style="width:19%;">المندوب</th><th class="num">مسندة</th><th class="num">مسلَّمة</th><th class="num">ملغاة</th><th class="num">النجاح</th>
  <th class="txt">زمن التوصيل</th><th class="txt">من الطلب للتسليم</th><th class="num">نقد محصَّل</th><th class="num">العهدة اليوم</th><th class="num">أيام بلا تسكير</th>
</tr></thead><tbody>
@forelse($report['delegates'] as $r)
<tr>
  <td>{{ $r['name'] }}@if(! $r['is_active']) <span class="quiet small">(موقوف)</span>@endif</td>
  <td class="num">{{ $r['assigned'] }}</td><td class="num">{{ $r['delivered'] }}</td><td class="num">{{ $r['cancelled'] }}</td>
  <td class="num">{{ F::percent($r['success_rate']) }}</td><td class="txt">{{ F::minutes($r['avg_delivery_minutes']) }}</td><td class="txt">{{ F::minutes($r['avg_total_minutes']) }}</td>
  <td class="num">{{ F::money($r['cash_collected']) }}</td><td class="num">{{ F::money($r['custody_balance']) }}</td><td class="num">{{ F::dash($r['days_since_settlement']) }}</td>
</tr>
@empty<tr><td colspan="10" class="empty">لا يوجد مناديب</td></tr>
@endforelse
</tbody></table>
<p class="quiet small" style="margin-top:3mm;">الأزمنة محسوبة من سجل حالات الطلب. العهدة وأيامها هي وضع اليوم وليست وضع الفترة.</p>
@endsection
