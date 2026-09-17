<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
  body { font-family: dejavusans, sans-serif; font-size: 9pt; color: #1c1917; }
  h1 { font-size: 16pt; margin: 0 0 2mm; }
  h2 { font-size: 11pt; margin: 6mm 0 2mm; color: #44403c; border-bottom: 1px solid #d6d3d1; padding-bottom: 1mm; }
  .brand { font-size: 13pt; font-weight: bold; color: #7c2d12; }
  .muted { color: #78716c; font-size: 8pt; }
  .head { width: 100%; border-bottom: 2px solid #7c2d12; padding-bottom: 3mm; margin-bottom: 4mm; }
  table.grid { width: 100%; border-collapse: collapse; margin-top: 2mm; }
  table.grid th, table.grid td { border: 1px solid #d6d3d1; padding: 1.6mm 2mm; text-align: right; vertical-align: top; }
  table.grid th { background: #f5f5f4; font-weight: bold; }
  table.grid td.num, table.grid th.num { text-align: left; direction: ltr; font-variant-numeric: tabular-nums; }
  .cards { width: 100%; margin: 2mm 0; }
  .cards td { width: 25%; padding: 2mm; border: 1px solid #e7e5e4; background: #fafaf9; }
  .cards .v { font-size: 12pt; font-weight: bold; }
  .cards .l { font-size: 8pt; color: #78716c; }
  .right { text-align: right; } .left { text-align: left; }
  .total td { font-weight: bold; background: #fef3c7; }
  .neg { color: #b91c1c; } .pos { color: #15803d; }
  .stamp { display: inline-block; border: 2px solid #15803d; color: #15803d; padding: 1mm 3mm; font-weight: bold; }
  .stamp.due { border-color: #b45309; color: #b45309; }
  .stamp.cancelled { border-color: #b91c1c; color: #b91c1c; }
</style>
</head>
<body>
<table class="head"><tr>
  <td class="right">
    <div class="brand">{{ config('app.name') }}</div>
    <div class="muted">{{ $subtitle ?? '' }}</div>
  </td>
  <td class="left">
    <h1>{{ $title }}</h1>
    <div class="muted">أُصدر في {{ now()->format('Y-m-d H:i') }}</div>
  </td>
</tr></table>
@yield('content')
</body>
</html>
