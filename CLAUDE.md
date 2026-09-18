# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A cafe supply-chain platform ("الساحل لمستلزمات المقاهي") for the Libyan market: an admin
dashboard, a customer app (the customers are cafés), and delegate (driver) endpoints, all served
by one Laravel API. The customer app was historically called "cafe"; that word now only survives
in infrastructure names (`cafe_supply_chain`, `cafe_network`, the repo path) and the company name.
The product language is Arabic — API messages, enum labels, and UI copy are Arabic, and
`AppServiceProvider::boot()` forces `app()->setLocale('ar')`.

Three independent projects, each with its own Docker setup:

- `backend/` — Laravel 12 API, MySQL, Redis, queue worker, Reverb WebSocket, Nginx
- `frontend-admin/` — admin dashboard (React 19 + Vite + Tailwind 4)
- `frontend-customer/` — customer app, also used by delegates (React 19 + Vite + Tailwind 4)

## Commands

### Backend

```bash
cd backend
cp .env.example .env            # set APP_ENV=local for dev seeding
docker compose up -d --build    # db, redis, app, reverb, worker, scheduler, nginx, phpmyadmin
```

Dev URLs: API `http://localhost:8000/api/v1`, Swagger `http://localhost:8000/docs`,
Telescope `http://localhost:8000/telescope`, phpMyAdmin `http://localhost:8080`,
MySQL `localhost:3307`.

`docker-entrypoint.sh` waits for MySQL and Redis, runs `migrate --force`, and seeds only when
`APP_ENV` is `local`/`development` and `admin@example.com` does not yet exist. Dev login is
`admin@example.com` / `password`. `CONTAINER_ROLE` selects what a container runs: `worker`
(`queue:work`), `scheduler` (`schedule:work`, needed for OTP and expired-token pruning) or web.

Tests — two configs, pick by where you run them:

```bash
docker compose exec app php artisan test                      # phpunit.xml: sqlite :memory:
docker compose exec app php artisan test --filter=WalletTest   # single test class/method
php artisan test -c phpunit.host.xml                          # on the host: file-backed sqlite
```

`phpunit.xml` sets every env var with `force="true"` on purpose: the app container exports
`DB_*`, `CACHE_STORE` and friends, and without the force the suite would run `migrate:fresh`
against the dev MySQL and count rate-limit hits in the shared Redis. PHPUnit's force only
writes `$_ENV`, while Laravel reads `$_SERVER` first, so `tests/TestCase.php` mirrors the forced
values into `$_SERVER` before booting. Keep that in place.
The container's PHP lacks JPEG support in GD, so the handful of tests that upload receipt images
(WalletTest, one CustodyTest) only pass on the host. Every test starts with roles and permission
codes seeded once per process (`AccessControlSeeder` via `$seed` in `tests/TestCase.php`), so
test setups must use `firstOrCreate` for user types and permissions.

Other backend tasks:

```bash
docker compose exec app ./vendor/bin/pint                 # PHP formatter (the only PHP linter here)
docker compose exec app php artisan l5-swagger:generate --all
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker
```

### Frontends

```bash
cd frontend-admin && docker compose up -d --build   # http://localhost:5173
cd frontend-customer && docker compose up -d --build   # http://localhost:5174
```

Or run them on the host with `npm run dev`. Vite proxies `/api` to `API_PROXY_TARGET`
(default `http://localhost`), so the frontends always call the relative path `/api/v1`.
`frontend-admin` lints with `npm run lint` (oxlint); `frontend-cafe` has no lint script.

### Combined stack

The root `docker-compose.yml` includes the backend compose file and adds one Nginx container
serving both prebuilt frontends on port 80: admin at `/`, customer at `/customer/`. Its Dockerfile
copies `frontend-*/dist` rather than building in-container, so you must build on the host first,
and the customer build needs `VITE_BASE_PATH=/customer/`. That is a documented workaround for stalling
npm fetches inside containers, not the intended design.

## Architecture

### Authorization is derived from route names, fail-closed

Every protected route sits behind `auth:sanctum` plus the `permission` middleware
(`app/Http/Middleware/CheckPermission.php`). `codeForRoute()` splits the route name on `.` into
`resource.action`, maps the action to a verb (`ACTION_MAP`: `index`/`show` → `VIEW`, `store` →
`CREATE`, `update` → `EDIT`, `destroy` → `DELETE`, plus custom actions) and requires the permission
code `RESOURCE_VERB`. `ROUTE_MAP` holds the exceptions (`orders.assign-delegate` → `ORDER_ASSIGN`,
`dashboard.*` → `DASHBOARD_VIEW`). Consequences worth remembering:

- The check is **fail-closed**: a role must hold the code, and a code nobody seeded refuses the
  request. `PermissionCoverageTest` walks every guarded route and fails when a derived code is
  missing from `PermissionSeeder`, so adding a resource means adding its module to the seeder
  (existing databases get new codes through a migration, see `2026_09_17_000017`).
- Exempt from module codes: the `customer.*`, `delegate.*` and `wallet.gateway.*` groups (their
  controllers scope by the signed-in user), the user-owned `notifications` resource, and unnamed
  routes (`login`, `logout`, `me`, `register`), which do their own checks.
- `super_admin` bypasses everything. `addresses` is remapped to the historic `CUSTOMER_BRANCHES_*`
  codes so existing roles keep working.

### Response shape

Controllers extend `BaseApiController`, which owns the envelope. Use `paginated()` for every
list so responses share one `{ data, meta }` shape; the admin's `useApiResource` carries every
meta key through to `pagination`, so endpoint-specific keys (`summary`, `status_counts`) reach the page (`meta` carries `current_page`, `per_page`,
`total`, `last_page`, `has_more`, plus any endpoint-specific keys). `jsonResponse()` wraps 201
and 4xx bodies in `{ success, message, ... }` and always emits `JSON_UNESCAPED_UNICODE` so
Arabic stays readable. Framework exceptions are converted to the same Arabic-message shape in
`bootstrap/app.php`, so do not hand-roll 401/403/404/422 responses.

`requireFeature($code)` gates an endpoint on a `PremiumFeature` flag and returns a ready 403.

### Order lifecycle is a state machine

`OrderStatus::transitions()` is the only definition of which status may follow which
(pending → confirmed → preparing → out_for_delivery → delivered → received; cancellation is
allowed up to and including delivered; rejecting a `cancellation_requested` returns to pending;
`received` and `cancelled` are final). The `Order` model enforces it in an `updating` hook and
throws a 422 with an Arabic message, so the dashboard, delegate and customer endpoints cannot
disagree. `GET /orders/{id}` and the delegate's order view return `next_statuses`, and both UIs
render only those. Delegates may set `out_for_delivery` and `delivered`; customers only
`received` and a cancellation request.

### Services own the write paths

Business invariants live in `app/Services/`, not controllers. Each of these is the *only* place
its table changes, and each wraps work in a transaction with `lockForUpdate()`:

- `StockService` — the only writer of `inventories.quantity`; every change writes a
  `stock_movements` ledger row.
- `WalletService` — the only place wallet balances change; amounts are converted to integer
  cents to avoid float drift, and every change writes a `wallet_transactions` entry.
- `CustodyService` — delegate cash held for the office (عهدة) and its settlement (تسكير); the
  only writer of `delegate_profiles.custody_balance`, also in cents.
- `OrderPlacementService` — the single path for creating orders. It resolves prices server-side,
  snapshots address and product data onto the order, deducts stock, auto-assigns a delegate, and
  notifies admins. Never build an `Order` directly.
- `DelegateAssignmentService` — nearest available delegate, where "available" means active, flag
  set, and a location updated within the last 30 minutes.

`ProductSearch` handles catalog querying, faceting, and sorting for the customer app.
`ArabicText::filter($query, $term, $columns)` is the shared Arabic-tolerant list search; admin
lists that still use a plain `LIKE` will miss spelling variants, so move them onto it when you
touch them (custody and wallet top-ups already use it).

### Reports (PDF / Excel)

`app/Services/Reports/` builds report data as arrays (`SalesReport`, `LedgerStatement` for
custody and wallet statements, `InventoryReport`) and `ReportController` serves each one as JSON,
`?format=pdf` or `?format=xlsx`. PDFs are Blade views under `resources/views/reports/` rendered by
`PdfRenderer` (mPDF, RTL, DejaVu Sans — no Chrome needed); Excel goes through `XlsxRenderer`
(OpenSpout). `Period::fromRequest()` parses `from`/`to`/`all` for every report. Invoices live at
`orders/{id}/invoice` (dashboard) and `customer/orders/{id}/invoice`; all `reports.*` routes need the
single `REPORTS_VIEW` code. Frontends download through `lib/download.js`, which fetches a blob with
the bearer token and names the file from `Content-Disposition`.

### Arabic-tolerant search

`App\Support\ArabicText` folds alef/ta-marbuta/ya variants, strips harakat and tatweel, and maps
Arabic-Indic digits, so "احمد" matches "أحمد" and "١٢٥" matches "125". It exposes both
`normalize()` for PHP strings and `sqlExpression()`, which wraps a column in nested `REPLACE()`
calls so a plain `LIKE` matches either spelling on MySQL and SQLite. Any user-facing search over
Arabic columns should go through it.

### H3 geospatial via Node subprocess

Delivery zones and warehouse coverage use Uber H3 hexes. There is no PHP H3 binding here:
`H3Service` shells out to `backend/scripts/h3.cjs` with JSON on stdin (`Process::input(...)`).
The Node script and the `h3-js` dependency in `backend/package.json` are therefore runtime
requirements of the PHP app, not just build tooling. The frontends use `h3-js` directly with
Leaflet for the map UI.

### Roles, tokens and rate limits

`UserRole` names the built-in `user_types` rows — `super_admin`, `admin`, `customer`, `delegate` —
and each maps to one profile table (`AdminProfile`, `CustomerProfile`, `DelegateProfile`).

Sanctum tokens expire after `SANCTUM_TOKEN_EXPIRATION_MINUTES` (default 30 days) and are pruned
by the scheduler. Rate limiters are defined in `AppServiceProvider` and backed by the cache store
(Redis in Docker): `api` caps every client, `auth` guards `login` per IP and per account, and
`otp` guards the registration and password-reset OTP endpoints. A 429 is rendered in the same
Arabic envelope as other API errors.

Order numbers are `ORD-YYYY-MM-DD-HH-NNN`, counted within the hour the order was placed and
rendered in `config('app.business_timezone')` (Libya) while storage stays UTC — so the number reads
as the hour the office experienced. `Order::generateOrderNumber()` takes the `placed_at` it should
describe. Numbers issued under the older `ORD-YYYY-NNNNN` format are left alone.

`OrderStatus` is the order lifecycle plus Arabic labels, and its `groups()` method defines the
active/completed/cancelled tabs the apps render. Keep tab logic there rather than in the clients.

### API surface

`routes/api.php` is the whole surface under `/api/v1`. Open endpoints: login, customer
registration and OTP flows, password reset, payment-gateway callback, the placeholder image route,
and read-only `products`/`categories`. Everything else is authenticated. The routes are organized
by client: a `customer/` group (mostly `CustomerMobileController`), a `delegate/` group, and flat
admin resources via `apiResources`. Carts, cart items, order items, status logs, and stock movements
are deliberately read-only, since their workflows write them.

Each app signs in at its own door — `customer/login`, `delegate/login`, `admin/login` — all served
by `AuthController::login()`, which reads the allowed user types from the route's `roles` default.
That exists because `users.mobile_number` is unique **per user type**, not globally: one person can
be a customer of the shop and drive for it, which are two accounts under a schema where a user has
one type. A lookup by number alone would be a coin toss, so the route supplies the type. Plain
`/login` stays as an unscoped alias for clients not yet moved over. A wrong-door attempt answers
exactly like a wrong password, so the endpoint cannot be used to discover which numbers are
registered as delegates. Every uniqueness rule on a phone number must carry the same scope — see
`AppUserRequest`, `DelegateRequest` and the two register requests.

Updates are `PATCH` — every one of them changes some fields and leaves the rest alone, which is
not what `PUT` means. `PUT` is still accepted beside it (`Route::match(['patch', 'put'], …)`, the
same pair `apiResource` registers) so clients already deployed keep working, and
`ApiConventionsTest` fails if a new update route takes only `PUT`.

`app/OpenApi/Processors/AddStandardResponses.php` gives every operation the failures it can really
produce, at generation time, from the rules the app follows: needs a token → `401` and `403`; an id
in the path → `404`; a request body → `422`; a sign-in route → `429`. It also declares `bearerAuth`
on protected operations (most never did, so the reference showed them as open) and fills in the
success body — a real captured response from `app/OpenApi/examples.json` where one exists, the
`{data, meta}` envelope for a collection, the `Created` envelope for a `201`. An operation that
documents a code itself always keeps its own wording. It is registered under
`defaults.scanOptions.processors` in `config/l5-swagger.php`; note that file has a second
`processors` key further down, and in PHP the later one wins.

Refresh `examples.json` by calling the endpoints and trimming the result — an example should be
what the endpoint really answers, not an invention, and nested lists are cut to one item so a
stranger's order history does not end up on the docs page.

`app/OpenApi/Responses.php` defines the error envelopes once — `Unauthenticated`, `Forbidden`,
`NotFound`, `ValidationError`, `TooManyRequests` — each with the body the app actually returns, so
an endpoint documents a failure with
`@OA\Response(response=422, ref="#/components/responses/ValidationError")`. Two annotations for the
same path and method make swagger-php abandon the whole file and silently keep serving a stale
spec; `OpenApiSpecTest` is the alarm for that and for anything else that breaks generation. **Every
line inside an annotation docblock needs its leading `*`** — Pint treats a line without one as not
part of the comment and deletes it, which is how a page of the app developers' guide and the whole
API overview were silently lost once. The reference page is
Scalar (`resources/views/scalar.blade.php`), configured through `data-configuration`.

`app/OpenApi/` holds the annotations for admin endpoints in one place so CRUD controllers stay
readable; cafe and delegate endpoints are annotated on their own controllers. Two Swagger
documents are configured: `default` (everything) and `customer`, which filters to tags matching
`/^Customer /`, `Auth`, and `Notifications`. The JSON under `backend/storage/api-docs/` is generated
— edit annotations, then regenerate, never edit the JSON.

### Realtime and background work

Reverb runs as its own container and broadcasts `DelegateLocationUpdated` for live driver
tracking; the admin app consumes it with laravel-echo and pusher-js. The `worker` container
runs `queue:work` against Redis, selected by `CONTAINER_ROLE=worker` in the entrypoint.

### Frontend conventions

Both apps store the bearer token in `localStorage` and attach it via an axios request
interceptor in `src/api/client.js`; a 401 response clears it and redirects to `/login`. The
admin client adds `postForm`/`putForm` helpers — `putForm` spoofs the method with `_method=PUT`
over POST because PHP only parses multipart bodies on POST, so real PUT leaves `$_FILES` empty.
Use those helpers for any upload.

The admin shell (`components/Layout.jsx`) owns the sidebar (`Nav.jsx`), the ⌘K command palette
(`CommandPalette.jsx`, pages + orders + customers search), the phone drawer and the quick-order
modal. `hooks/useLiveCounts.js` polls the one-row list endpoints for the sidebar badges (pending
orders, cancellation requests, pending top-ups, unread notifications); a new badge is a new entry
there plus a `count` key on the link in `Nav.jsx`. Sidebar colors are a measured ramp on two hues taken from the app palette (175° teal for every
surface and text level, 32° amber for one job): the `--color-sidebar*` and `--color-ember` tokens
in `index.css`, each commented with its contrast ratio. Text lands at 13.3 / 8.1 / 5.3 against the
ground so group labels, idle links and the active page differ by contrast before color, and
`--color-sidebar-edge` is the 3:1 value for component boundaries. Amber marks a count that needs a
person and nothing else — when no work is waiting the column carries no amber at all, so keep new
sidebar elements on the teal steps. The active item is drawn as a light tab joined to the page.

Notifications are a full page: the inbox plus, with `NOTIFICATIONS_SEND`, sending announcements to
customers/delegates/admins/specific users (`POST notifications/send`, one row per recipient via
`Notification::sendTo`) and a sent history grouped by batch (`GET notifications/sent`).

### Phone layout and PWA (admin)

The dashboard is used on a phone, so no page may need a sideways scroll to be read. `DataTable`
renders the same `columns` twice: a table from `md:` up, and a card per row below it, where a
column can say where it belongs with `mobile: 'title' | 'subtitle' | 'hide'`. Anything genuinely
wide (a chart, a detail table) scrolls inside its own box; `main` is `overflow-x-hidden`, so a
child that overflows is clipped rather than scrolled — give it a scroll wrapper. Filter rows in
`main > header` are stacked by rules in `index.css`, which is why headers need no per-page work.
Safe-area insets are applied inline with `calc()` at each edge element: a utility that sets
padding to `env()` alone replaces that element's padding instead of adding to it, which silently
flattens the page gutters.

The admin is an installable PWA: `public/manifest.webmanifest`, icons under `public/icons`
(rendered from `favicon.svg`), and `public/sw.js`, registered from `main.jsx` in production
builds only. The worker caches the app shell and the hashed build output, never API responses,
and bypasses `/api`, `/storage`, `/docs`, `/app` and `/customer` — the last because the customer
app is a separate build under the same origin and inside this worker's scope. Bump `VERSION` in
the worker to retire old caches. nginx serves the manifest as `application/manifest+json` and
`/sw.js` with no-store, so a release is never hidden behind a cached worker.

The admin app's `useApiResource` hook is the standard way to render a paginated list: it reads
the backend's `meta` shape and persists the current page in the URL query string by default.
State that crosses pages lives in React context (`AuthContext`, and in the customer app `CartContext`
and `FavoritesContext`).

### Migrations

Schema is grouped into domain migrations dated `2026_09_13_*` (access control, users, warehouses,
catalog, stock, carts, orders, system, wallets, custody, featured sections, favorites). Those are
the consolidated baseline — add new dated migrations rather than editing them. Data migrations
are used for stored codes too: `2026_09_17_000016` renamed the `cafe` role, permission codes,
premium-feature codes and notification types to `customer`.

## Conventions

Comments prefixed `ponytail:` mark deliberate non-obvious decisions and workarounds, with the
reason. Read them before changing the surrounding code, and follow the same style when you make
a choice the next reader would otherwise want to "fix".

Docs live in `docs/` (`customer-endpoints-demo.md`, `sequence-diagrams.md`). `bruno/` holds an API
client collection, backed by `BrunoDemoSeeder`. `PROJECT.md` tracks goals and open questions.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
