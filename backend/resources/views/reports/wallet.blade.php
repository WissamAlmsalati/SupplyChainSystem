@extends('reports.layout', ['subtitle' => 'الزبون: '.($report['customer']['name'] ?? '').' · '.$report['period']['from'].' → '.$report['period']['to']])
@section('content')
<p>الزبون: <b>{{ $report['customer']['name'] ?? '—' }}</b> <span class="muted" dir="ltr">{{ $report['customer']['mobile_number'] ?? '' }}</span> · محفظة #{{ $report['wallet']['id'] }} {{ $report['wallet']['is_active'] ? '' : '(موقوفة)' }}</p>
@include('reports._statement')
@endsection
