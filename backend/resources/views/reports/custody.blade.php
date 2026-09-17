@extends('reports.layout', ['subtitle' => 'المندوب: '.$report['delegate']['name'].' · '.$report['period']['from'].' → '.$report['period']['to']])
@section('content')
<p>المندوب: <b>{{ $report['delegate']['name'] }}</b> <span class="muted" dir="ltr">{{ $report['delegate']['mobile_number'] }}</span></p>
@include('reports._statement')
@endsection
