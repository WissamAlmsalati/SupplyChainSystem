<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوابة الدفع التجريبية</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5f5f4; font-family: system-ui, "Segoe UI", Tahoma, sans-serif; color: #1c1917; }
        .card { width: min(420px, calc(100vw - 32px)); background: #fff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        .badge { display: inline-block; background: #fef3c7; color: #92400e; border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
        h1 { font-size: 20px; margin: 12px 0 4px; }
        .muted { color: #78716c; font-size: 14px; }
        .amount { font-size: 36px; font-weight: 800; color: #0f766e; margin: 20px 0; }
        .row { display: flex; justify-content: space-between; font-size: 14px; padding: 6px 0; border-bottom: 1px dashed #e7e5e4; }
        form { margin-top: 12px; }
        button { width: 100%; border: 0; border-radius: 10px; padding: 12px; font-size: 15px; font-weight: 700; cursor: pointer; }
        .pay { background: #0f766e; color: #fff; }
        .fail { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .done { margin-top: 16px; text-align: center; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge">بيئة تجريبية — لا يتم خصم أموال حقيقية</span>
    <h1>شحن المحفظة</h1>
    <div class="muted">الساحل لمستلزمات المقاهي</div>
    <div class="amount">{{ number_format((float) $topup->amount, 2) }} د.ل</div>
    <div class="row"><span class="muted">الزبون</span><span>{{ $topup->user?->name }}</span></div>
    <div class="row"><span class="muted">رقم العملية</span><span dir="ltr">#{{ $topup->id }} · {{ $reference }}</span></div>

    @if ($topup->status->value === 'pending')
        <form method="POST" action="/api/v1/wallet/gateway/callback">
            <input type="hidden" name="token" value="{{ $topup->gateway_token }}">
            <input type="hidden" name="status" value="paid">
            <input type="hidden" name="reference" value="{{ $reference }}">
            <input type="hidden" name="signature" value="{{ $paidSignature }}">
            <input type="hidden" name="redirect" value="1">
            <button class="pay" type="submit">ادفع الآن</button>
        </form>
        <form method="POST" action="/api/v1/wallet/gateway/callback">
            <input type="hidden" name="token" value="{{ $topup->gateway_token }}">
            <input type="hidden" name="status" value="failed">
            <input type="hidden" name="reference" value="{{ $reference }}">
            <input type="hidden" name="signature" value="{{ $failedSignature }}">
            <input type="hidden" name="redirect" value="1">
            <button class="fail" type="submit">إلغاء / فشل الدفع</button>
        </form>
    @else
        <div class="done">تمت معالجة هذه العملية ({{ $topup->status->value }}).</div>
    @endif
</div>
</body>
</html>
