{{-- The line under a report's title: what it covers. --}}
@php $d = fn (string $ymd) => str_replace('-', '/', $ymd); @endphp
<table class="facts"><tr>
  <td>
    <span class="fk">الفترة</span><br>
    {{-- "all" is sent as a start in the year 2000; say what it means instead. --}}
    @if(str_starts_with($period['from'], '2000-'))
    <span class="fs">منذ فتح الحساب حتى <span dir="ltr">{{ $d($period['to']) }}</span></span>
    @else
    <span class="fs">من <span dir="ltr">{{ $d($period['from']) }}</span> إلى <span dir="ltr">{{ $d($period['to']) }}</span></span>
    @endif
  </td>
  @foreach($facts ?? [] as $label => $value)
  <td style="text-align:left;">
    <span class="fk">{{ $label }}</span><br>
    <span class="fs">{{ $value }}</span>
  </td>
  @endforeach
</tr></table>
