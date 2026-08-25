<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title>Cafe API QA Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f4;
            color: #1c1917;
            line-height: 1.6;
        }
        .container { max-width: 1300px; margin: 0 auto; padding: 24px; }
        header {
            background: #fff;
            border-bottom: 2px solid #000;
            padding: 20px 24px;
            margin: -24px -24px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        h1 { margin: 0; font-size: 1.5rem; }
        .subtitle { color: #78716c; font-size: 0.875rem; }
        button {
            background: #000;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        button:hover:not(:disabled) { opacity: 0.85; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            text-align: center;
        }
        .card .number { font-size: 2rem; font-weight: 700; }
        .card .label { color: #78716c; font-size: 0.875rem; }
        .pass { color: #16a34a; }
        .fail { color: #dc2626; }
        .pending { color: #d97706; }
        .skip { color: #78716c; }
        .expected { color: #2563eb; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        th, td {
            padding: 12px 16px;
            text-align: right;
            border-bottom: 1px solid #e7e5e4;
            vertical-align: top;
        }
        th { background: #fafaf9; font-weight: 600; color: #57534e; }
        tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pass { background: #dcfce7; color: #166534; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-expected { background: #dbeafe; color: #1e40af; }
        .badge-unexpected { background: #ffedd5; color: #9a3412; }
        .badge-skip { background: #f5f5f4; color: #57534e; }
        .badge-wait { background: #fef3c7; color: #92400e; }
        .status-code { font-family: monospace; color: #57534e; }
        .error-msg { color: #dc2626; font-size: 0.875rem; }
        .detail-btn {
            background: transparent;
            color: #000;
            border: 1px solid #e7e5e4;
            padding: 4px 10px;
            font-size: 0.8rem;
        }
        .detail-panel {
            display: none;
            background: #fafaf9;
            padding: 12px;
            margin-top: 8px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 300px;
            overflow: auto;
        }
        .detail-panel.open { display: block; }
        .endpoint-path { font-family: monospace; direction: ltr; text-align: left; display: inline-block; }
        .test-type { font-size: 0.75rem; color: #78716c; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>Cafe API QA Dashboard</h1>
                <div class="subtitle">فحص تلقائي لـ Cafe Mobile API endpoints — نجاح + فشل متوقع</div>
            </div>
            <button id="runBtn" onclick="runTests()" disabled>جاري تحميل الـ spec...</button>
        </header>

        <div class="summary">
            <div class="card">
                <div class="number" id="totalCount">0</div>
                <div class="label">إجمالي الـ endpoints</div>
            </div>
            <div class="card">
                <div class="number pass" id="passCount">0</div>
                <div class="label">ناجح</div>
            </div>
            <div class="card">
                <div class="number fail" id="failCount">0</div>
                <div class="label">فاشل</div>
            </div>
            <div class="card">
                <div class="number expected" id="expectedFailCount">0</div>
                <div class="label">فشل متوقع (صحيح)</div>
            </div>
            <div class="card">
                <div class="number pending" id="unexpectedPassCount">0</div>
                <div class="label">نجح غلط</div>
            </div>
            <div class="card">
                <div class="number skip" id="skipCount">0</div>
                <div class="label">متخطى</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>النوع</th>
                    <th>Method</th>
                    <th>Endpoint</th>
                    <th>الوصف</th>
                    <th>النتيجة</th>
                    <th>الـ Status</th>
                    <th>تفاصيل</th>
                </tr>
            </thead>
            <tbody id="resultsBody"></tbody>
        </table>
    </div>

    <script>
        const specUrl = '/docs/cafe/json';
        const credentials = { phone_number: '0911111111', password: 'password' };
        let spec = null;
        let tests = [];
        const state = { token: null };

        loadSpec();

        async function loadSpec() {
            const btn = document.getElementById('runBtn');
            try {
                const res = await fetch(specUrl);
                spec = await res.json();
                tests = buildTestsFromSpec(spec);
                btn.textContent = 'تشغيل الفحص';
                btn.disabled = false;
                renderRows();
            } catch (err) {
                btn.textContent = 'فشل تحميل الـ spec';
                document.getElementById('resultsBody').innerHTML = '<tr><td colspan="8" class="error-msg" style="text-align:center">تعذر تحميل OpenAPI spec. تأكد من تشغيل الخادم.</td></tr>';
            }
        }

        function buildTestsFromSpec(spec) {
            const positive = [];
            for (const [path, methods] of Object.entries(spec.paths || {})) {
                for (const [method, operation] of Object.entries(methods)) {
                    if (method === 'parameters') continue;
                    positive.push({
                        method: method.toUpperCase(),
                        path,
                        summary: operation.summary || operation.operationId || path,
                        operationId: operation.operationId || '',
                        parameters: operation.parameters || [],
                        requestBody: operation.requestBody || null,
                        responses: operation.responses || {},
                        negative: false,
                    });
                }
            }
            const sorted = sortTestsByDependency(positive);
            const negative = generateNegativeTests(sorted);
            return [...sorted, ...negative];
        }

        function sortTestsByDependency(list) {
            const methodOrder = { GET: 1, POST: 2, PUT: 3, PATCH: 3, DELETE: 4 };
            return list.slice().sort((a, b) => {
                if (a.path === '/login' && a.method === 'POST') return -1;
                if (b.path === '/login' && b.method === 'POST') return 1;
                const orderA = methodOrder[a.method] || 5;
                const orderB = methodOrder[b.method] || 5;
                if (orderA !== orderB) return orderA - orderB;
                const paramsA = (a.path.match(/\{/g) || []).length;
                const paramsB = (b.path.match(/\{/g) || []).length;
                return paramsA - paramsB;
            });
        }

        function generateNegativeTests(positiveTests) {
            const negative = [];
            for (const test of positiveTests) {
                if (test.path === '/login' && test.method === 'POST') {
                    // Wrong password
                    negative.push({
                        ...test,
                        negative: true,
                        summary: test.summary + ' — بيانات دخول خاطئة',
                        negativeBody: { phone_number: credentials.phone_number, password: 'wrong-password' },
                    });
                    continue;
                }

                if (test.method === 'GET' || test.method === 'DELETE' || test.method === 'PUT') {
                    const hasPathParam = test.path.includes('{');
                    if (hasPathParam) {
                        negative.push({
                            ...test,
                            negative: true,
                            summary: test.summary + ' — ID غير موجود',
                            negativePath: test.path.replace(/\{[^}]+\}/g, '999999'),
                        });
                    }
                }

                if ((test.method === 'POST' || test.method === 'PUT') && test.requestBody) {
                    const body = generateBody(test);
                    if (body && Object.keys(body).length > 0) {
                        // Create a corrupted body by zeroing/removing an ID field
                        const corrupted = corruptBody(body);
                        if (corrupted) {
                            negative.push({
                                ...test,
                                negative: true,
                                summary: test.summary + ' — بيانات غير صحيحة',
                                negativeBody: corrupted,
                            });
                        }
                    }
                }
            }
            return negative;
        }

        function corruptBody(body) {
            const corrupted = JSON.parse(JSON.stringify(body));
            const idFields = ['branch_id', 'product_variant_id', 'order_id', 'delegate_id', 'cafe_id', 'user_id', 'warehouse_id'];
            for (const key of idFields) {
                if (key in corrupted) {
                    corrupted[key] = 999999;
                    return corrupted;
                }
            }
            // If no ID field, remove first required-ish field
            const keys = Object.keys(corrupted);
            if (keys.length > 0) {
                const first = keys[0];
                if (Array.isArray(corrupted[first]) && corrupted[first].length > 0) {
                    corrupted[first] = [];
                } else if (typeof corrupted[first] === 'string') {
                    corrupted[first] = '';
                } else if (typeof corrupted[first] === 'number') {
                    corrupted[first] = -1;
                } else if (typeof corrupted[first] === 'boolean') {
                    corrupted[first] = !corrupted[first];
                }
                return corrupted;
            }
            return null;
        }

        function resolveRef(ref) {
            if (!ref) return null;
            if (typeof ref === 'object') return ref.$ref ? resolveRef(ref.$ref) : ref;
            if (typeof ref !== 'string' || !ref.startsWith('#/')) return ref;
            const parts = ref.replace('#/', '').split('/');
            let current = spec;
            for (const part of parts) {
                current = current[part];
                if (!current) return null;
            }
            return current;
        }

        function getRequestSchema(operation) {
            const body = operation.requestBody;
            if (!body || !body.content) return null;
            const jsonContent = body.content['application/json'] || body.content['multipart/form-data'];
            if (!jsonContent || !jsonContent.schema) return null;
            const ref = jsonContent.schema.$ref || jsonContent.schema;
            return resolveRef(ref);
        }

        function generateValue(schema) {
            const s = resolveRef(schema) || schema;
            if (!s || typeof s !== 'object') return null;
            if (s.example !== undefined) return s.example;
            if (s.default !== undefined) return s.default;
            if (s.enum && s.enum.length) return s.enum[0];
            switch (s.type) {
                case 'string': return s.format === 'date' ? '2026-01-01' : 'test';
                case 'integer': return 1;
                case 'number': return 1.0;
                case 'boolean': return true;
                case 'array':
                    if (s.items) {
                        return [generateValue(s.items)];
                    }
                    return [];
                case 'object':
                    const obj = {};
                    if (s.properties) {
                        for (const [key, val] of Object.entries(s.properties)) {
                            obj[key] = generateValue(val);
                        }
                    }
                    return obj;
                default: return null;
            }
        }

        function generateBody(operation) {
            const schema = getRequestSchema(operation);
            if (!schema) return null;
            const body = {};
            const props = schema.properties || {};
            const required = schema.required || [];

            const idMap = {
                branch_id: 'branchId',
                cafe_id: 'cafeId',
                product_id: 'productId',
                product_variant_id: 'variantId',
                order_id: 'orderId',
                delegate_id: 'delegateId',
                user_id: 'userId',
                warehouse_id: 'warehouseId',
            };

            for (const key of required) {
                body[key] = substituteBodyValue(key, props[key], idMap);
            }
            for (const [key, val] of Object.entries(props)) {
                if (!(key in body)) {
                    body[key] = substituteBodyValue(key, val, idMap);
                }
            }
            return body;
        }

        function substituteBodyValue(key, propSchema, idMap) {
            const stateKey = idMap[key];
            if (stateKey && state[stateKey]) {
                return state[stateKey];
            }
            const val = generateValue(propSchema);
            if (Array.isArray(val) && val.length > 0 && typeof val[0] === 'object') {
                return val.map(item => substituteIdsInObject(item, idMap));
            }
            return val;
        }

        function substituteIdsInObject(obj, idMap) {
            if (!obj || typeof obj !== 'object') return obj;
            const result = {};
            for (const [k, v] of Object.entries(obj)) {
                if (idMap[k] && state[idMap[k]]) {
                    result[k] = state[idMap[k]];
                } else if (Array.isArray(v)) {
                    result[k] = v.map(item => substituteIdsInObject(item, idMap));
                } else if (typeof v === 'object' && v !== null) {
                    result[k] = substituteIdsInObject(v, idMap);
                } else {
                    result[k] = v;
                }
            }
            return result;
        }

        function inferStateKey(path, paramName) {
            if (path.includes('/cart/items/') && paramName === 'id') {
                return 'cartItemId';
            }
            const segments = path.split('/').filter(Boolean);
            const paramIndex = segments.indexOf('{' + paramName + '}');
            if (paramIndex < 0) return null;
            const parentSegment = segments[paramIndex - 1];
            if (!parentSegment) return null;
            const map = {
                branches: 'branchId',
                products: 'productId',
                orders: 'orderId',
                delegates: 'delegateId',
                users: 'userId',
                cafes: 'cafeId',
                warehouses: 'warehouseId',
            };
            if (parentSegment === 'variants') return 'variantId';
            return map[parentSegment] || null;
        }

        function substitutePath(path, operation) {
            let missing = [];
            const substituted = path.replace(/\{([^}]+)\}/g, (match, paramName) => {
                const key = inferStateKey(path, paramName);
                if (key && state[key]) {
                    return state[key];
                }
                missing.push(paramName);
                return match;
            });
            return { path: substituted, missing };
        }

        function extractIds(path, json) {
            const cartItem = path === '/cafe/cart/items' && json.data && json.data.data;
            if (cartItem && typeof cartItem.id === 'number') {
                state.cartItemId = cartItem.id;
                return;
            }
            if (path === '/cafe/cart' && json.items && json.items[0] && typeof json.items[0].id === 'number') {
                state.cartItemId = json.items[0].id;
                return;
            }
            const segments = path.split('/').filter(Boolean);
            const lastSegment = segments[segments.length - 1];
            const data = json.data || json;
            const firstItem = Array.isArray(data) ? data[0] : data;
            if (!firstItem || typeof firstItem.id !== 'number') return;
            const map = {
                branches: 'branchId',
                products: 'productId',
                orders: 'orderId',
                variants: 'variantId',
                delegates: 'delegateId',
                users: 'userId',
                cafes: 'cafeId',
                warehouses: 'warehouseId',
            };
            const key = map[lastSegment];
            if (key && !state[key]) {
                state[key] = firstItem.id;
            }
        }

        function is2xx(status) {
            return status >= 200 && status < 300;
        }

        function is4xx(status) {
            return status >= 400 && status < 500;
        }

        function renderRows() {
            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';
            tests.forEach((test, index) => {
                const result = test.result || { status: 'wait', statusCode: '-', error: '' };
                let badgeClass = 'badge-wait';
                let badgeText = 'في الانتظار';

                if (test.negative) {
                    if (result.status === 'pass') {
                        badgeClass = 'badge-expected';
                        badgeText = 'فشل متوقع ✓';
                    } else if (result.status === 'fail') {
                        badgeClass = 'badge-unexpected';
                        badgeText = 'نجح غلط ✗';
                    }
                } else {
                    badgeClass = result.status === 'pass' ? 'badge-pass' : result.status === 'fail' ? 'badge-fail' : result.status === 'skip' ? 'badge-skip' : 'badge-wait';
                    badgeText = result.status === 'pass' ? 'ناجح' : result.status === 'fail' ? 'فاشل' : result.status === 'skip' ? 'متخطى' : 'في الانتظار';
                }

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td><span class="test-type">${test.negative ? 'فشل متوقع' : 'نجاح'}</span></td>
                    <td><strong>${test.method}</strong></td>
                    <td><span class="endpoint-path">${test.path}</span></td>
                    <td>${escapeHtml(test.summary)}</td>
                    <td><span class="badge ${badgeClass}">${badgeText}</span></td>
                    <td class="status-code">${result.statusCode}</td>
                    <td>
                        ${result.error ? '<div class="error-msg">' + escapeHtml(result.error) + '</div>' : ''}
                        ${result.body ? '<button class="detail-btn" onclick="toggleDetail(' + index + ')">عرض الرد</button><div id="detail-' + index + '" class="detail-panel">' + escapeHtml(result.body) + '</div>' : ''}
                    </td>
                `;
                tbody.appendChild(row);
            });
            updateSummary();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function toggleDetail(index) {
            const panel = document.getElementById('detail-' + index);
            panel.classList.toggle('open');
        }

        function updateSummary() {
            const total = tests.length;
            const pass = tests.filter(t => !t.negative && t.result && t.result.status === 'pass').length;
            const fail = tests.filter(t => !t.negative && t.result && t.result.status === 'fail').length;
            const expectedFail = tests.filter(t => t.negative && t.result && t.result.status === 'pass').length;
            const unexpectedPass = tests.filter(t => t.negative && t.result && t.result.status === 'fail').length;
            const skip = tests.filter(t => t.result && t.result.status === 'skip').length;
            document.getElementById('totalCount').textContent = total;
            document.getElementById('passCount').textContent = pass;
            document.getElementById('failCount').textContent = fail;
            document.getElementById('expectedFailCount').textContent = expectedFail;
            document.getElementById('unexpectedPassCount').textContent = unexpectedPass;
            document.getElementById('skipCount').textContent = skip;
        }

        async function runTests() {
            const btn = document.getElementById('runBtn');
            btn.disabled = true;
            btn.textContent = 'جاري الفحص...';
            state.token = null;
            for (const key of Object.keys(state)) {
                if (key !== 'token') delete state[key];
            }
            tests.forEach(t => delete t.result);
            renderRows();

            const loginTest = tests.find(t => t.path === '/login' && t.method === 'POST' && !t.negative);
            if (loginTest) {
                await runSingleTest(loginTest, credentials);
                renderRows();
                if (!state.token) {
                    btn.disabled = false;
                    btn.textContent = 'إعادة الفحص';
                    return;
                }
            }

            for (const test of tests) {
                if (test.path === '/login' && test.method === 'POST' && !test.negative) continue;

                const { path, missing } = substitutePath(test.negative ? (test.negativePath || test.path) : test.path, test);
                if (missing.length > 0) {
                    test.result = {
                        status: 'skip',
                        statusCode: '-',
                        error: 'يتطلب IDs: ' + missing.join(', '),
                        body: ''
                    };
                    renderRows();
                    continue;
                }

                const body = test.negative ? test.negativeBody : generateBody(test);
                await runSingleTest(test, body, path);

                if (!test.negative && test.path === '/cafe/cart/checkout' && test.result?.status === 'pass') {
                    delete state.cartItemId;
                }

                renderRows();
            }

            btn.disabled = false;
            btn.textContent = 'إعادة الفحص';
        }

        async function runSingleTest(test, body, overridePath) {
            try {
                const path = overridePath || test.path;
                const options = {
                    method: test.method,
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }
                };
                if (state.token && path !== '/login') {
                    options.headers['Authorization'] = 'Bearer ' + state.token;
                }
                if (body && ['POST', 'PUT', 'PATCH'].includes(test.method)) {
                    options.body = JSON.stringify(body);
                }

                const res = await fetch('/api/v1' + path, options);
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (e) {}

                let passed;
                if (test.negative) {
                    // Negative test passes when server returns 4xx (validation/auth/not found)
                    passed = is4xx(res.status);
                } else {
                    passed = is2xx(res.status);
                }

                test.result = {
                    status: passed ? 'pass' : 'fail',
                    statusCode: res.status,
                    error: passed ? '' : (json?.message || 'رد غير ناجح'),
                    body: text
                };

                if (!test.negative && passed && json) {
                    if (path === '/login' && json.token) {
                        state.token = json.token;
                    }
                    extractIds(test.path, json);
                }
            } catch (err) {
                test.result = { status: 'fail', statusCode: 'ERR', error: err.message, body: '' };
            }
        }
    </script>
</body>
</html>
