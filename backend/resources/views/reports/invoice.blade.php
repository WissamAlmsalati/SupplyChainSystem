@use('App\Support\ReportFormat', 'F')
@use('App\Support\BusinessTime')
@use('App\Support\MoneyInWords')
@php
  $invoiceNo = str_starts_with($order->order_number, 'ORD-') ? 'INV-'.substr($order->order_number, 4) : 'INV-'.$order->order_number;
  $business = $order->user?->customerProfile?->business_name;
  $cancelled = $order->status->value === 'cancelled';
  $owed = max((float) $order->total_amount - (float) ($returned ?? 0), 0);
  $state = $cancelled ? 'ملغاة' : ($due <= 0 ? 'مسددة بالكامل' : ($paid - ($refunded ?? 0) > 0 ? 'مسددة جزئياً' : 'غير مسددة'));
@endphp
@extends('reports.layout', ['title' => 'فاتورة', 'subtitle' => 'رقم '.$invoiceNo])
@section('content')
<table class="facts"><tr>
  <td style="width:33%;">
    <span class="fk">فاتورة إلى</span><br>
    <span class="fs">{{ $business ?: $order->user?->name }}</span><br>
    @if($business && $business !== $order->user?->name)<span class="fv">{{ $order->user?->name }}</span><br>@endif
    <span class="fv" dir="ltr">{{ $order->user?->mobile_number }}</span>
  </td>
  <td style="width:35%;">
    <span class="fk">التوصيل إلى</span><br>
    <span class="fs">{{ $order->delivery_address_name ?: '-' }}</span><br>
    <span class="fv">{{ implode('، ', array_filter([$order->delivery_street, $order->delivery_city])) }}</span>
    @if(! empty($order->delivery_phones))<br><span class="fv" dir="ltr">{{ implode('  ', (array) $order->delivery_phones) }}</span>@endif
  </td>
  <td style="width:32%;">
    <table style="width:100%; border-collapse:collapse;">
      <tr><td class="fk">رقم الطلب</td><td class="fv" dir="ltr" style="text-align:left;">{{ $order->order_number }}</td></tr>
      <tr><td class="fk">تاريخ الطلب</td><td class="fv" dir="ltr" style="text-align:left;">{{ BusinessTime::format($order->placed_at, 'Y/m/d H:i') }}</td></tr>
      <tr><td class="fk">حالة الطلب</td><td class="fv" style="text-align:left;">{{ $order->status->label() }}</td></tr>
      <tr><td class="fk">المندوب</td><td class="fv" style="text-align:left;">{{ $order->delegate?->name ?? '-' }}</td></tr>
      @if($order->warehouse)<tr><td class="fk">يُجهَّز من</td><td class="fv" style="text-align:left;">{{ $order->warehouse->name }}</td></tr>@endif
    </table>
  </td>
</tr></table>

<table class="data" style="margin-top:6mm;">
  <thead><tr>
    <th style="width:7%;">م</th><th>الصنف</th><th class="num" style="width:11%;">الكمية</th><th class="num" style="width:17%;">سعر الوحدة</th><th class="num" style="width:18%;">الإجمالي</th>
  </tr></thead>
  <tbody>
  @foreach($order->items as $i => $item)
    <tr>
      <td>{{ $i + 1 }}</td>
      <td>{{ $item->product_name }}@if($item->variant_name) <span class="quiet">{{ $item->variant_name }}</span>@endif</td>
      <td class="num">{{ $item->quantity }}</td>
      <td class="num">{{ F::money($item->unit_price) }}</td>
      <td class="num">{{ F::money($item->quantity * $item->unit_price) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

<table style="width:100%; margin-top:3mm;"><tr>
  <td style="vertical-align:top; padding-left:10mm;">
    @unless($cancelled)
    <table style="width:100%; border-collapse:collapse;"><tr><td style="padding:2mm 0 3mm;">
      <span class="fk">المبلغ المستحق كتابةً</span><br><span class="fv">{{ MoneyInWords::dinars($owed) }}</span>
    </td></tr></table>
    @endunless

    @if($order->payments->isNotEmpty())
    <table class="data">
      <thead><tr><th colspan="3">الدفعات المستلمة</th></tr></thead>
      @foreach($order->payments as $p)
      <tr>
        <td dir="ltr" style="text-align:right; width:34%;">{{ BusinessTime::format($p->paid_at, 'Y/m/d H:i') }}</td>
        <td>{{ $payment_labels[$p->method instanceof \BackedEnum ? $p->method->value : $p->method] ?? $p->method }}@if($p->collector)<span class="quiet">، استلمها {{ $p->collector->name }}</span>@endif</td>
        <td class="num">{{ F::money($p->amount) }}</td>
      </tr>
      @endforeach
    </table>
    @endif

    @if($order->customer_note)
    <table style="width:100%; border-collapse:collapse; margin-top:4mm;"><tr><td style="border-right:0.8mm solid #0b3b38; padding:1mm 3mm;">
      <span class="fk">ملاحظة الزبون</span><br><span class="fv">{{ $order->customer_note }}</span>
    </td></tr></table>
    @endif
  </td>
  <td style="vertical-align:top; width:78mm;">
    <table class="totals" style="margin-top:0;">
      <tr><td>مجموع الأصناف</td><td class="num">{{ F::money($order->subtotal) }}</td></tr>
      <tr><td>رسوم التوصيل</td><td class="num">{{ F::money($order->delivery_fee) }}</td></tr>
      <tr class="grand"><td>الإجمالي ({{ config('company.currency') }})</td><td class="num">{{ F::money($order->total_amount) }}</td></tr>
      @if(($returned ?? 0) > 0)<tr><td>مرتجعات</td><td class="num">{{ F::signed(-$returned) }}</td></tr>@endif
      <tr><td>المدفوع</td><td class="num">{{ F::money($paid) }}</td></tr>
      @if(($refunded ?? 0) > 0)<tr><td>مسترد للزبون</td><td class="num">{{ F::money($refunded) }}</td></tr>@endif
      <tr><td><b>المتبقي</b></td><td class="num"><b>{{ F::money($due) }}</b></td></tr>
      <tr><td style="border-bottom:0;">حالة السداد</td><td style="border-bottom:0; text-align:left;"><b>{{ $state }}</b></td></tr>
    </table>
  </td>
</tr></table>

<table class="sign">
  <tr><td>استلمتُ البضاعة المذكورة كاملة وبحالة سليمة</td><td class="gap"></td><td>المندوب: {{ $order->delegate?->name ?? '' }}</td></tr>
  <tr><td class="line"></td><td class="gap"></td><td class="line"></td></tr>
  <tr><td class="quiet small" style="padding-top:1mm;">اسم المستلم وتوقيعه والتاريخ</td><td class="gap"></td><td class="quiet small" style="padding-top:1mm;">التوقيع</td></tr>
</table>

@if(config('company.invoice_terms'))<p class="quiet small" style="margin-top:6mm;">{{ config('company.invoice_terms') }}</p>@endif
@endsection
