<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f7f5;
            color: #1c1917;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 720px;
            width: 100%;
            text-align: center;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        p {
            color: #78716c;
            margin-bottom: 2rem;
        }
        .cards {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .card {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 14px;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            transition: box-shadow 0.2s, transform 0.2s;
            text-align: right;
        }
        .card:hover {
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.06);
            transform: translateY(-2px);
        }
        .card h2 {
            font-size: 1.125rem;
            margin: 0 0 0.5rem;
            color: #0f766e;
        }
        .card span {
            font-size: 0.875rem;
            color: #78716c;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="/favicon.svg" alt="logo" style="width: 80px; height: 80px; margin-bottom: 1rem;">
        <h1>الساحل لمستلزمات المقاهي</h1>
        <p>توثيق الـ APIs — اختر المنصة اللي تبي تشوف دوكومنتيشنها</p>
        <div class="cards">
            <a class="card" href="/docs/admin">
                <h2>Admin Dashboard</h2>
                <span>لوحة تحكم الأدمن</span>
            </a>
            <a class="card" href="/docs/cafe">
                <h2>Cafe Mobile App</h2>
                <span>تطبيق المقاهي</span>
            </a>
            <a class="card" href="/docs/delegate-scalar">
                <h2>Delegate Mobile App</h2>
                <span>تطبيق المناديب</span>
            </a>
        </div>
    </div>
</body>
</html>
