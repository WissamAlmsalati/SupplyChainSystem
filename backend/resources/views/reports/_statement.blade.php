@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<table class="cards"><tr>
  <td><div class="l">رصيد أول الفترة</div><div class="v">{{ $money($report['opening_balance']) }}</div></td>
  <td><div class="l">إضافات</div><div class="v pos">{{ $money($report['credits']) }}</div></td>
  <td><div class="l">خصومات</div><div class="v neg">{{ $money($report['debits']) }}</div></td>
  <td><div class="l">رصيد آخر الفترة</div><div class="v">{{ $money($report['closing_balance']) }}</div></td>
</tr></table>
<p class="muted">الرصيد الحالي (الآن): <b>{{ $money($report['current_balance']) }} د.ل</b></p>

<h2>ملخص حسب النوع</h2>
<table class="grid"><thead><tr><th>النوع</th><th class="num">العدد</th><th class="num">المبلغ</th></tr></thead><tbody>
@forelse($report['totals'] as $t)<tr><td>{{ $t['label'] }}</td><td class="num">{{ $t['count'] }}</td><td class="num {{ $t['amount'] < 0 ? 'neg' : 'pos' }}">{{ $money($t['amount']) }}</td></tr>@empty<tr><td colspan="3" class="muted">لا توجد حركات في الفترة</td></tr>@endforelse
</tbody></table>

<h2>الحركات</h2>
<table class="grid">
  <thead><tr><th style="width:20%">التاريخ</th><th>النوع</th><th>المرجع / ملاحظة</th><th>بواسطة</th><th class="num" style="width:13%">المبلغ</th><th class="num" style="width:14%">الرصيد بعدها</th></tr></thead>
  <tbody>
  @forelse($report['entries'] as $e)
    <tr><td dir="ltr" class="left">{{ $e['date'] }}</td><td>{{ $e['label'] }}</td><td>{{ $e['reference'] }} {{ $e['note'] ? '— '.$e['note'] : '' }}</td><td>{{ $e['by'] ?? '—' }}</td><td class="num {{ $e['amount'] < 0 ? 'neg' : 'pos' }}">{{ $money($e['amount']) }}</td><td class="num">{{ $money($e['balance_after']) }}</td></tr>
  @empty
    <tr><td colspan="6" class="muted">لا توجد حركات في الفترة</td></tr>
  @endforelse
  </tbody>
</table>
