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

### Customer Mobile Endpoints
- ✔ Customer login returns token without permissions
- ✔ Customer cannot login with email
- ✔ Customer me returns user
- ✔ Customer profile
- ✔ Customer branches list
- ✔ Customer branch create
- ✔ Customer branch orders
- ✔ Customer orders list
- ✔ Customer order create
- ✔ Customer categories list
- ✔ Customer products list
- ✔ Customer product variants
- ✔ Customer inventory list
- ✔ Customer can list delivery zones for map

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

### Customer Registration
- ✔ Customer can register with required fields
- ✔ Customer registration accepts optional email and logo
- ✔ Unapproved customer cannot login
- ✔ Login with phone number works for approved customer
- ✔ Admin can list pending registrations
- ✔ Admin can approve customer registration
- ✔ Admin can reject customer registration
- ✔ Non-admin cannot access registration admin routes
- ✔ Registration requires mandatory fields

### Customer Mobile Enhancements
- ✔ Customer can get empty cart
- ✔ Customer can add item to cart
- ✔ Customer can update cart item quantity
- ✔ Customer can remove cart item
- ✔ Customer can clear cart
- ✔ Customer can checkout cart
- ✔ Checkout empty cart fails
- ✔ Customer cannot add item to foreign branch
- ✔ Customer can see delegate location for own order
- ✔ Customer cannot see delegate for other customer order
- ✔ Customer dashboard returns enhanced stats

### Sanity checks
- ✔ The application returns a successful response
- ✔ That true is true

## Recent changes

- `backend/app/Http/Controllers/Api/AuthController.php` — public customer self-registration (`registerCustomer`); customer users must login by phone number only; blocks inactive accounts.
- `backend/app/Http/Controllers/Api/CustomerRegistrationController.php` — admin endpoints to list, approve, and reject pending customer registrations.
- `backend/app/Http/Requests/Api/Auth/CustomerRegisterRequest.php` — validation for customer self-registration.
- `backend/database/migrations/2026_08_21_000000_add_registration_fields_to_customer_table.php` — adds `address`, `latitude`, and `longitude` to `customer`.
- `backend/database/migrations/2026_08_21_000001_make_app_user_email_nullable.php` — makes `app_user.email` nullable for phone-only customer accounts.
- `backend/routes/api.php` — public `POST /api/customer/register` and protected `GET/POST /api/customer-registrations/*` admin routes.
- `backend/tests/Feature/CustomerRegistrationTest.php` — full coverage of registration, approval, rejection, and login gating.
- `backend/app/OpenApi/Schemas.php` — added `CustomerRegisterRequest` schema and updated `AuthLoginRequest`.
- `backend/app/Http/Controllers/Api/CustomerMobileController.php` — customer cart, checkout, and delegate-location endpoints.
- `backend/app/Http/Controllers/Api/CustomerDashboardController.php` — enhanced dashboard with period stats, top products, and branch comparison.
- `backend/tests/Feature/CustomerMobileEnhancementsTest.php` — coverage for cart, checkout, delegate tracking, and dashboard analytics.
- `docs/customer-endpoints-demo.md` — documented new cart, checkout, delegate tracking, and dashboard endpoints.
- `frontend-admin/vite.config.js` + `frontend-admin/docker-compose.yml` — fixed API proxy target from non-existent `http://web` to `http://localhost` (local dev) / `http://nginx` (Docker).
- `frontend-customer/vite.config.js` + `frontend-customer/docker-compose.yml` — same proxy target fix.
- `backend/app/Console/Commands/CustomerEndpointsDemo.php` — updated base URL from `http://web/api` to `http://localhost/api`.
- `backend/routes/api.php` — wrapped all routes under `v1` prefix (`/api/v1/*`).
- `frontend-admin/src/api/client.js` + `frontend-customer/src/api/client.js` — base URL updated to `/api/v1`.
- `backend/app/OpenApi/ApiInfo.php` + `backend/app/Http/Controllers/Api/DelegateDocsController.php` — Swagger server URL updated to `/api/v1`.
- `backend/tests/Feature/*.php` + `docs/customer-endpoints-demo.md` — all endpoint URLs updated to `/api/v1/*`.
- `frontend-admin/src/index.css` + `frontend-customer/src/index.css` — switched font from Tajawal to Thmanyah Sans via jsDelivr CDN.
- `frontend-admin/src/components/ui/Skeleton.jsx` + `frontend-customer/src/components/ui/Skeleton.jsx` — added reusable skeleton/loading components (`Skeleton`, `SkeletonText`, `SkeletonCard`, `PageSkeleton`, `ListSkeleton`, `SkeletonTable`).
- `frontend-admin/src/components/DataTable.jsx` — shows `SkeletonTable` while data is loading.
- `frontend-admin/src/pages/Dashboard.jsx` — shows `DashboardSkeleton` while loading.
- `frontend-admin/src/pages/OrderDetail.jsx`, `ProductDetail.jsx`, `DeliveryZoneDetail.jsx` — show `PageSkeleton` while loading.
- `frontend-customer/src/pages/Dashboard.jsx`, `Profile.jsx`, `Branches.jsx`, `Orders.jsx`, `DelegateOrders.jsx`, `DelegateOrderDetail.jsx` — replaced plain "جاري التحميل..." text with skeleton loading placeholders.
