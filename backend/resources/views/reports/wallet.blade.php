@extends('reports.layout')
@section('content')
@include('reports._period', ['period' => $report['period'], 'facts' => [
  'صاحب المحفظة' => $report['customer']['name'] ?? '-',
  'الجوال' => $report['customer']['mobile_number'] ?? '-',
  'رقم المحفظة' => $report['wallet']['id'].($report['wallet']['is_active'] ? '' : ' (موقوفة)'),
]])
@include('reports._statement')
@endsection
