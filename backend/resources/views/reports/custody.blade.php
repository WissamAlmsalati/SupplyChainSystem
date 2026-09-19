@extends('reports.layout')
@section('content')
@include('reports._period', ['period' => $report['period'], 'facts' => ['المندوب' => $report['delegate']['name'], 'الجوال' => $report['delegate']['mobile_number'] ?? '-']])
@include('reports._statement')
<table class="sign">
  <tr><td>المندوب: {{ $report['delegate']['name'] }}</td><td class="gap"></td><td>المحاسب</td></tr>
  <tr><td class="line"></td><td class="gap"></td><td class="line"></td></tr>
  <tr><td class="quiet small" style="padding-top:1mm;">أقرّ بصحة الرصيد أعلاه. التوقيع والتاريخ</td><td class="gap"></td><td class="quiet small" style="padding-top:1mm;">التوقيع والتاريخ</td></tr>
</table>
@endsection
