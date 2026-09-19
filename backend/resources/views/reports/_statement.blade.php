@use('App\Support\ReportFormat', 'F')
{{-- A ledger statement, laid out the way a bank writes one: what came in and what
     went out in their own columns, and the balance after every line. --}}
@include('reports._figures', ['figures' => [
  ['رصيد أول الفترة', F::signed($report['opening_balance'])],
  ['إجمالي الإضافات', F::money($report['credits'])],
  ['إجمالي الخصومات', F::money(abs($report['debits']))],
  ['رصيد آخر الفترة', F::signed($report['closing_balance']), 'الرصيد الآن '.F::signed($report['current_balance'])],
]])

<h2>ملخص الحركات</h2>
<table class="data"><thead><tr><th>نوع الحركة</th><th class="num w18">العدد</th><th class="num w18">المبلغ</th></tr></thead><tbody>
@forelse($report['totals'] as $t)<tr><td>{{ $t['label'] }}</td><td class="num">{{ $t['count'] }}</td><td class="num">{{ F::signed($t['amount']) }}</td></tr>
@empty<tr><td colspan="3" class="empty">لا توجد حركات في هذه الفترة</td></tr>@endforelse
</tbody></table>

<h2>كشف الحركات</h2>
<table class="data">
  <thead><tr><th style="width:19%;">التاريخ</th><th>البيان</th><th style="width:13%;">بواسطة</th><th class="num" style="width:12%;">إضافة</th><th class="num" style="width:12%;">خصم</th><th class="num" style="width:13%;">الرصيد</th></tr></thead>
  <tbody>
  @if(count($report['entries']) > 0)<tr><td colspan="5" class="quiet">رصيد أول الفترة</td><td class="num">{{ F::signed($report['opening_balance']) }}</td></tr>@endif
  @forelse($report['entries'] as $e)
    <tr>
      <td dir="ltr" style="text-align:right;">{{ str_replace('-', '/', substr((string) $e['date'], 0, 16)) }}</td>
      {{-- The note usually names the order already; the bare reference is the fallback. --}}
      <td>{{ $e['label'] }}@if($e['note'] || $e['reference'])<br><span class="quiet small">{{ $e['note'] ?: $e['reference'] }}</span>@endif</td>
      <td class="small">{{ F::dash($e['by'] ?? null) }}</td>
      <td class="num">{{ $e['amount'] > 0 ? F::money($e['amount']) : '' }}</td>
      <td class="num">{{ $e['amount'] < 0 ? F::money(abs($e['amount'])) : '' }}</td>
      <td class="num">{{ F::signed($e['balance_after']) }}</td>
    </tr>
  @empty
    <tr><td colspan="6" class="empty">لا توجد حركات في هذه الفترة</td></tr>
  @endforelse
  @if(count($report['entries']) > 0)<tr class="sum"><td colspan="3">المجموع ورصيد آخر الفترة</td><td class="num">{{ F::money($report['credits']) }}</td><td class="num">{{ F::money(abs($report['debits'])) }}</td><td class="num">{{ F::signed($report['closing_balance']) }}</td></tr>@endif
  </tbody>
</table>
