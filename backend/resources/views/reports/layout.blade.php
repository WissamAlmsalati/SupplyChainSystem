<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
  /* One sheet for every printed document. Ink: near-black text, one dark teal
     for the company and the rules under headings, greys for everything quiet.
     No tinted boxes and no red or green: a negative amount is written in
     brackets, the way a ledger writes it. */
  body { font-family: thmanyah, sans-serif; font-size: 9pt; color: #161616; line-height: 1.45; }
  .ltr { direction: ltr; }
  .quiet { color: #5c5c5c; }
  .small { font-size: 7.8pt; }

  .letterhead { width: 100%; border-bottom: 0.6mm solid #0b3b38; }
  .letterhead td { vertical-align: bottom; padding-bottom: 3mm; }
  .company { font-size: 13.5pt; font-weight: bold; color: #0b3b38; }
  .doc-title { font-size: 21pt; font-weight: bold; }
  .doc-sub { color: #5c5c5c; font-size: 8.5pt; }

  /* mPDF drops the styling of block elements inside table cells, so inside a
     cell everything is an inline span with its own class, broken by <br>. */
  .facts { width: 100%; margin-top: 4mm; }
  .facts td { vertical-align: top; }
  .fk { color: #5c5c5c; font-size: 7.8pt; }
  .fv { font-size: 9.5pt; }
  .fs { font-size: 10.5pt; font-weight: bold; }

  h2 { font-size: 10.5pt; font-weight: bold; margin: 7mm 0 0; padding-bottom: 1.2mm; border-bottom: 0.2mm solid #0b3b38; }

  /* Key figures: one ruled strip, not a row of boxes. */
  .figures { width: 100%; margin-top: 5mm; border-top: 0.35mm solid #161616; border-bottom: 0.2mm solid #9a9a9a; }
  .figures td { padding: 2.2mm 3mm 2.4mm; border-left: 0.2mm solid #cfcfcf; vertical-align: top; }
  .figures td.last { border-left: 0; }
  .figures tr.second td { border-top: 0.2mm solid #cfcfcf; }
  .gk { font-size: 7.8pt; color: #5c5c5c; }
  .gv { font-size: 12pt; font-weight: bold; }
  .gn { font-size: 7.5pt; color: #5c5c5c; }

  table.data { width: 100%; border-collapse: collapse; margin-top: 1.5mm; }
  table.data th { font-size: 7.8pt; font-weight: bold; color: #3d3d3d; text-align: right; padding: 1.8mm 1.5mm 1.5mm; border-bottom: 0.35mm solid #161616; }
  table.data td { padding: 1.7mm 1.5mm; border-bottom: 0.15mm solid #d5d5d5; vertical-align: top; text-align: right; }
  table.data .num { text-align: left; direction: ltr; white-space: nowrap; }
  /* A value with Arabic words in it ("3 س 20 د") sits on the left like a number but reads right to left. */
  table.data .txt { text-align: left; white-space: nowrap; }
  /* Column widths, chosen per table: few number columns can be wide, many must be narrow. */
  .w12 { width: 12%; } .w14 { width: 14%; } .w18 { width: 18%; }
  table.data tr.sum td { border-top: 0.35mm solid #161616; border-bottom: 0; font-weight: bold; }
  table.data td.empty { color: #5c5c5c; text-align: center; padding: 4mm; }

  .totals { width: 78mm; border-collapse: collapse; margin-top: 3mm; }
  .totals td { padding: 1.4mm 1.5mm; border-bottom: 0.15mm solid #d5d5d5; }
  .totals .num { text-align: left; direction: ltr; white-space: nowrap; }
  .totals tr.grand td { border-top: 0.45mm solid #161616; border-bottom: 0.45mm solid #161616; font-weight: bold; font-size: 11pt; }

  .note { margin-top: 4mm; padding: 2mm 3mm; border-right: 0.8mm solid #0b3b38; color: #2b2b2b; }
  .sign { width: 100%; margin-top: 12mm; border-collapse: collapse; }
  .sign td { font-size: 8.5pt; padding: 0; }
  .sign td.gap { width: 12mm; }
  .sign td.line { border-bottom: 0.2mm solid #161616; height: 12mm; }
</style>
</head>
<body>
<table class="letterhead"><tr>
  <td style="width:20mm;"><img src="{{ resource_path('images/logo.svg') }}" style="width:17mm;" /></td>
  <td>
    <span class="company">{{ config('company.name') }}</span><br>
    <span class="quiet small">{{ config('company.tagline') }}</span><br>
    <span class="quiet small">{{ config('company.address') }}، هاتف <span dir="ltr">{{ config('company.phone') }}</span></span>
    @if(config('company.commercial_register') || config('company.tax_number'))
    <br><span class="quiet small">@if(config('company.commercial_register'))سجل تجاري {{ config('company.commercial_register') }}@endif @if(config('company.tax_number')) الرقم الضريبي {{ config('company.tax_number') }}@endif</span>
    @endif
  </td>
  <td style="width:80mm; text-align:left;">
    <span class="doc-title">{{ $title }}</span><br>
    @if(! empty($subtitle))<span class="doc-sub">{{ $subtitle }}</span><br>@endif
    <span class="doc-sub">تاريخ الإصدار <span dir="ltr">{{ \App\Support\BusinessTime::now()->format('Y/m/d H:i') }}</span></span>
  </td>
</tr></table>
@yield('content')
</body>
</html>
