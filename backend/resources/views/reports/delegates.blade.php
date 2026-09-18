@extends('reports.layout', ['subtitle' => 'الفترة '.$report['period']['from'].' → '.$report['period']['to']])
@section('content')
@php
  $money = fn ($v) => number_format((float) $v, 2);
  $dash = fn ($v, $suffix = '') => $v === null ? '—' : $v.$suffix;
  $s = $report['summary'];
@endphp
<table class="cards"><tr>
  <td><div class="l">مسلّمة / مسندة</div><div class="v">{{ $s['delivered'] }} / {{ $s['assigned'] }}</div></td>
  <td><div class="l">نسبة النجاح</div><div class="v">{{ $dash($s['success_rate'], '%') }}</div></td>
  <td><div class="l">متوسط زمن التوصيل</div><div class="v">{{ $dash($s['avg_delivery_minutes'], ' د') }}</div></td>
  <td><div class="l">عهدة عند المناديب الآن</div><div class="v">{{ $money($s['custody_held']) }}</div></td>
</tr></table>

<h2>المناديب</h2>
<table class="grid"><thead><tr>
  <th>المندوب</th><th class="num">مسندة</th><th class="num">مسلّمة</th><th class="num">ملغاة</th><th class="num">النجاح</th>
  <th class="num">التوصيل (د)</th><th class="num">الكلي (د)</th><th class="num">نقد محصّل</th><th class="num">العهدة الآن</th><th class="num">أيام بلا تسكير</th>
</tr></thead><tbody>
@forelse($report['delegates'] as $r)
<tr>
  <td>{{ $r['name'] }}@if(! $r['is_active']) <span class="muted">(موقوف)</span>@endif</td>
  <td class="num">{{ $r['assigned'] }}</td><td class="num pos">{{ $r['delivered'] }}</td><td class="num neg">{{ $r['cancelled'] }}</td>
  <td class="num">{{ $dash($r['success_rate'], '%') }}</td><td class="num">{{ $dash($r['avg_delivery_minutes']) }}</td><td class="num">{{ $dash($r['avg_total_minutes']) }}</td>
  <td class="num">{{ $money($r['cash_collected']) }}</td><td class="num">{{ $money($r['custody_balance']) }}</td><td class="num">{{ $dash($r['days_since_settlement']) }}</td>
</tr>
@empty<tr><td colspan="10" class="muted">لا يوجد مناديب</td></tr>
@endforelse
</tbody></table>
@endsection
