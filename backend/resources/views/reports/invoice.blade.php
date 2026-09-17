@extends('reports.layout', ['subtitle' => 'فاتورة طلب'])
@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<table class="cards"><tr>
  <td><div class="l">رقم الطلب</div><div class="v" dir="ltr">{{ $order->order_number }}</div></td>
  <td><div class="l">التاريخ</div><div class="v" dir="ltr">{{ $order->placed_at?->format('Y-m-d H:i') }}</div></td>
  <td><div class="l">الحالة</div><div class="v">{{ $order->status->label() }}</div></td>
  <td><div class="l">طريقة الطلب</div><div class="v">{{ $order->source?->value === 'app' ? 'التطبيق' : 'لوحة التحكم' }}</div></td>
</tr></table>

<h2>الزبون والتوصيل</h2>
<table class="grid">
  <tr><th style="width:22%">الزبون</th><td>{{ $order->user?->customerProfile?->business_name ?: $order->user?->name }} @if($order->user?->customerProfile?->business_name)<span class="muted">({{ $order->user?->name }})</span>@endif</td>
      <th style="width:18%">الجوال</th><td dir="ltr" class="left">{{ $order->user?->mobile_number }}</td></tr>
  <tr><th>العنوان</th><td>{{ $order->delivery_address_name }} — {{ $order->delivery_city }} {{ $order->delivery_street }}</td>
      <th>هواتف التواصل</th><td dir="ltr" class="left">{{ implode(' · ', (array) ($order->delivery_phones ?? [])) }}</td></tr>
  <tr><th>المندوب</th><td colspan="3">{{ $order->delegate?->name ?? '—' }} @if($order->delegate)<span dir="ltr" class="muted">{{ $order->delegate->mobile_number }}</span>@endif</td></tr>
</table>

<h2>الأصناف</h2>
<table class="grid">
  <thead><tr><th style="width:5%">#</th><th>المنتج</th><th>الحجم</th><th class="num" style="width:10%">الكمية</th><th class="num" style="width:15%">سعر الوحدة</th><th class="num" style="width:16%">الإجمالي</th></tr></thead>
  <tbody>
  @foreach($order->items as $i => $item)
    <tr><td class="num">{{ $i + 1 }}</td><td>{{ $item->product_name }}</td><td>{{ $item->variant_name }}</td><td class="num">{{ $item->quantity }}</td><td class="num">{{ $money($item->unit_price) }}</td><td class="num">{{ $money($item->quantity * $item->unit_price) }}</td></tr>
  @endforeach
    <tr><td colspan="5" class="right">المجموع</td><td class="num">{{ $money($order->subtotal) }}</td></tr>
    <tr><td colspan="5" class="right">رسوم التوصيل</td><td class="num">{{ $money($order->delivery_fee) }}</td></tr>
    <tr class="total"><td colspan="5" class="right">الإجمالي (د.ل)</td><td class="num">{{ $money($order->total_amount) }}</td></tr>
  </tbody>
</table>

<h2>الدفع</h2>
<table class="grid">
  <thead><tr><th>التاريخ</th><th>الطريقة</th><th>حصّله</th><th class="num" style="width:18%">المبلغ</th></tr></thead>
  <tbody>
  @forelse($order->payments as $p)
    <tr><td dir="ltr" class="left">{{ $p->paid_at?->format('Y-m-d H:i') }}</td><td>{{ $payment_labels[$p->method instanceof \BackedEnum ? $p->method->value : $p->method] ?? $p->method }}</td><td>{{ $p->collector?->name ?? '—' }}</td><td class="num">{{ $money($p->amount) }}</td></tr>
  @empty
    <tr><td colspan="4" class="muted">لا توجد دفعات مسجلة</td></tr>
  @endforelse
    <tr><td colspan="3" class="right">المدفوع</td><td class="num">{{ $money($paid) }}</td></tr>
    <tr class="total"><td colspan="3" class="right">المتبقي</td><td class="num">{{ $money($due) }}</td></tr>
  </tbody>
</table>
<p style="margin-top:6mm">
  @if($order->status->value === 'cancelled')<span class="stamp cancelled">ملغي</span>
  @elseif($due <= 0)<span class="stamp">مدفوعة بالكامل</span>
  @else<span class="stamp due">متبقي {{ $money($due) }} د.ل</span>@endif
</p>
@endsection
