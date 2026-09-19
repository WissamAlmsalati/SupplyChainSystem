{{-- Key figures as one ruled strip. $figures: rows of [label, value, note?]; four to a row. --}}
<table class="figures" cellspacing="0">
@foreach(array_chunk($figures, 4) as $r => $row)
  <tr class="{{ $r > 0 ? 'second' : '' }}">
  @foreach($row as $c => $f)
    <td class="{{ $c === count($row) - 1 ? 'last' : '' }}" style="width:25%;">
      <span class="gk">{{ $f[0] }}</span><br>
      <span class="gv" dir="{{ preg_match('/\p{Arabic}/u', (string) $f[1]) ? 'rtl' : 'ltr' }}">{{ $f[1] }}</span>
      @if(! empty($f[2]))<br><span class="gn">{{ $f[2] }}</span>@endif
    </td>
  @endforeach
  @for($i = count($row); $i < 4; $i++)<td class="last" style="width:25%;"></td>@endfor
  </tr>
@endforeach
</table>
