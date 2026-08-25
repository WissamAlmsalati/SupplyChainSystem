# Backend Endpoint Test Results

## Run environment

- **Date:** 2026-08-21
- **PHP:** 8.2.32
- **PHPUnit:** 11.5.56
- **Database:** SQLite (`:memory:`)
- **Run command:**

```bash
cd backend
php artisan test
```

> `phpunit.xml` now uses SQLite in-memory by default, so `php artisan test` runs without Docker. The old `phpunit.host.xml` is still available if a file-based SQLite DB is preferred.

## Result

```
OK (70 tests, 231 assertions)
```

All tests passed.

## Test coverage

### Cafe Mobile Endpoints
- ✔ Cafe login returns token without permissions
- ✔ Cafe cannot login with email
- ✔ Cafe me returns user
- ✔ Cafe profile
- ✔ Cafe branches list
- ✔ Cafe branch create
- ✔ Cafe branch orders
- ✔ Cafe orders list
- ✔ Cafe order create
- ✔ Cafe categories list
- ✔ Cafe products list
- ✔ Cafe product variants
- ✔ Cafe inventory list
- ✔ Cafe can list delivery zones for map

### Delegate Admin Endpoints
- ✔ Admin can list delegates
- ✔ Admin can create delegate
- ✔ Admin can update delegate
- ✔ Admin can delete delegate
- ✔ Admin can toggle delegate active status

### Delegate Mobile Endpoints
- ✔ Delegate can update location
- ✔ Delegate can set availability
- ✔ Order auto assigns nearest delegate
- ✔ Delegate location update broadcasts event
- ✔ Delegate can list assigned orders
- ✔ Delegate can filter assigned orders by status
- ✔ Delegate can view assigned order detail
- ✔ Delegate cannot view unassigned order detail
- ✔ Delegate can update assigned order status
- ✔ Delegate cannot update unassigned order status
- ✔ Delegate cannot set status other than delivered

### Order Assignment
- ✔ Admin can assign delegate to order
- ✔ Cannot assign inactive delegate

### Premium Features
- ✔ Lists only active premium features
- ✔ Cannot create inventory when feature inactive
- ✔ Can create inventory when feature active
- ✔ Cannot create role when feature inactive
- ✔ Can create role when feature active

### Warehouse Hex Expansion
- ✔ Warehouse hex is computed from lat lng resolution
- ✔ Expand hex creates child delivery zones

### Cafe Registration
- ✔ Cafe can register with required fields
- ✔ Cafe registration accepts optional email and logo
- ✔ Unapproved cafe cannot login
- ✔ Login with phone number works for approved cafe
- ✔ Admin can list pending registrations
- ✔ Admin can approve cafe registration
- ✔ Admin can reject cafe registration
- ✔ Non-admin cannot access registration admin routes
- ✔ Registration requires mandatory fields

### Cafe Mobile Enhancements
- ✔ Cafe can get empty cart
- ✔ Cafe can add item to cart
- ✔ Cafe can update cart item quantity
- ✔ Cafe can remove cart item
- ✔ Cafe can clear cart
- ✔ Cafe can checkout cart
- ✔ Checkout empty cart fails
- ✔ Cafe cannot add item to foreign branch
- ✔ Cafe can see delegate location for own order
- ✔ Cafe cannot see delegate for other cafe order
- ✔ Cafe dashboard returns enhanced stats

### Sanity checks
- ✔ The application returns a successful response
- ✔ That true is true

## Recent changes

- `backend/app/Http/Controllers/Api/AuthController.php` — public cafe self-registration (`registerCafe`); cafe users must login by phone number only; blocks inactive accounts.
- `backend/app/Http/Controllers/Api/CafeRegistrationController.php` — admin endpoints to list, approve, and reject pending cafe registrations.
- `backend/app/Http/Requests/Api/Auth/CafeRegisterRequest.php` — validation for cafe self-registration.
- `backend/database/migrations/2026_08_21_000000_add_registration_fields_to_cafe_table.php` — adds `address`, `latitude`, and `longitude` to `cafe`.
- `backend/database/migrations/2026_08_21_000001_make_app_user_email_nullable.php` — makes `app_user.email` nullable for phone-only cafe accounts.
- `backend/routes/api.php` — public `POST /api/cafe/register` and protected `GET/POST /api/cafe-registrations/*` admin routes.
- `backend/tests/Feature/CafeRegistrationTest.php` — full coverage of registration, approval, rejection, and login gating.
- `backend/app/OpenApi/Schemas.php` — added `CafeRegisterRequest` schema and updated `AuthLoginRequest`.
- `backend/app/Http/Controllers/Api/CafeMobileController.php` — cafe cart, checkout, and delegate-location endpoints.
- `backend/app/Http/Controllers/Api/CafeDashboardController.php` — enhanced dashboard with period stats, top products, and branch comparison.
- `backend/tests/Feature/CafeMobileEnhancementsTest.php` — coverage for cart, checkout, delegate tracking, and dashboard analytics.
- `docs/cafe-endpoints-demo.md` — documented new cart, checkout, delegate tracking, and dashboard endpoints.
- `frontend-admin/vite.config.js` + `frontend-admin/docker-compose.yml` — fixed API proxy target from non-existent `http://web` to `http://localhost` (local dev) / `http://nginx` (Docker).
- `frontend-cafe/vite.config.js` + `frontend-cafe/docker-compose.yml` — same proxy target fix.
- `backend/app/Console/Commands/CafeEndpointsDemo.php` — updated base URL from `http://web/api` to `http://localhost/api`.
- `backend/routes/api.php` — wrapped all routes under `v1` prefix (`/api/v1/*`).
- `frontend-admin/src/api/client.js` + `frontend-cafe/src/api/client.js` — base URL updated to `/api/v1`.
- `backend/app/OpenApi/ApiInfo.php` + `backend/app/Http/Controllers/Api/DelegateDocsController.php` — Swagger server URL updated to `/api/v1`.
- `backend/tests/Feature/*.php` + `docs/cafe-endpoints-demo.md` — all endpoint URLs updated to `/api/v1/*`.
- `frontend-admin/src/index.css` + `frontend-cafe/src/index.css` — switched font from Tajawal to Thmanyah Sans via jsDelivr CDN.
- `frontend-admin/src/components/ui/Skeleton.jsx` + `frontend-cafe/src/components/ui/Skeleton.jsx` — added reusable skeleton/loading components (`Skeleton`, `SkeletonText`, `SkeletonCard`, `PageSkeleton`, `ListSkeleton`, `SkeletonTable`).
- `frontend-admin/src/components/DataTable.jsx` — shows `SkeletonTable` while data is loading.
- `frontend-admin/src/pages/Dashboard.jsx` — shows `DashboardSkeleton` while loading.
- `frontend-admin/src/pages/OrderDetail.jsx`, `ProductDetail.jsx`, `DeliveryZoneDetail.jsx` — show `PageSkeleton` while loading.
- `frontend-cafe/src/pages/Dashboard.jsx`, `Profile.jsx`, `Branches.jsx`, `Orders.jsx`, `DelegateOrders.jsx`, `DelegateOrderDetail.jsx` — replaced plain "جاري التحميل..." text with skeleton loading placeholders.
