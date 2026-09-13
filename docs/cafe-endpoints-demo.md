# Cafe Mobile API Endpoints Demo

Base URL: `http://localhost/api/v1`

## Step 1: Setup test data

## Step 2: POST /api/v1/login
**Request:** `POST /api/v1/login`

**Body:**

```json
{
    "email": "cafe.demo@example.com",
    "password": "password"
}
```

**Response:** `200`

```json
{
    "token": "15|r7c8lp94gxAOIOtup9q6xZ3KV5mVsMGHDzLvPTFc1020710d",
    "permissions": [
        "CAFE_BRANCHES_CREATE",
        "CAFE_BRANCHES_DELETE",
        "CAFE_BRANCHES_EDIT",
        "CAFE_BRANCHES_VIEW",
        "INVENTORY_VIEW",
        "ORDERS_CREATE",
        "ORDERS_EDIT",
        "ORDERS_VIEW"
    ]
}
```

## Step 3: GET /api/v1/me — Get authenticated user
**Request:** `GET /api/v1/me`

**Response:** `200`

```json
{
    "id": 15,
    "name": "Cafe Demo",
    "email": "cafe.demo@example.com",
    "mobile_number": "0911111111",
    "user_type_id": 45,
    "cafe_id": 12,
    "is_active": true,
    "created_at": "2026-08-13T20:16:33.000000Z",
    "updated_at": "2026-08-13T20:16:33.000000Z",
    "user_type": {
        "id": 45,
        "name": "cafe",
        "permissions": [
            {
                "id": 93,
                "code": "CAFE_BRANCHES_CREATE",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 93
                }
            },
            {
                "id": 95,
                "code": "CAFE_BRANCHES_DELETE",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 95
                }
            },
            {
                "id": 94,
                "code": "CAFE_BRANCHES_EDIT",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 94
                }
            },
            {
                "id": 92,
                "code": "CAFE_BRANCHES_VIEW",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 92
                }
            },
            {
                "id": 96,
                "code": "INVENTORY_VIEW",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 96
                }
            },
            {
                "id": 91,
                "code": "ORDERS_CREATE",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 91
                }
            },
            {
                "id": 90,
                "code": "ORDERS_EDIT",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 90
                }
            },
            {
                "id": 89,
                "code": "ORDERS_VIEW",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 89
                }
            }
        ]
    },
    "cafe": {
        "id": 12,
        "name": "مقهى اختبار",
        "contact_info": "0911111111",
        "image": null,
        "created_by_admin_id": null,
        "is_active": true,
        "created_at": 1786651759,
        "image_url": null
    }
}
```

## Step 4: GET /api/v1/cafe/profile — Get cafe profile
**Request:** `GET /api/v1/cafe/profile`

**Response:** `200`

```json
{
    "cafe": {
        "id": 12,
        "name": "مقهى اختبار",
        "contact_info": "0911111111",
        "image": null,
        "created_by_admin_id": null,
        "is_active": true,
        "created_at": 1786651759,
        "image_url": null,
        "branches": [
            {
                "id": 13,
                "cafe_id": 12,
                "name": "فرع رئيسي",
                "city": "طرابلس",
                "street": "الشارع الرئيسي",
                "latitude": "27.000000",
                "longitude": "17.000000",
                "delivery_zone_id": 12,
                "is_active": true,
                "created_at": "2026-08-13 20:09:19"
            },
            {
                "id": 14,
                "cafe_id": 12,
                "name": "فرع جديد",
                "city": "بنغازي",
                "street": "شارع جمال",
                "latitude": "27.100000",
                "longitude": "17.100000",
                "delivery_zone_id": null,
                "is_active": true,
                "created_at": "2026-08-13 20:09:19"
            },
            {
                "id": 15,
                "cafe_id": 12,
                "name": "فرع جديد",
                "city": "بنغازي",
                "street": "شارع جمال",
                "latitude": "27.100000",
                "longitude": "17.100000",
                "delivery_zone_id": null,
                "is_active": true,
                "created_at": "2026-08-13 20:09:19"
            },
            {
                "id": 16,
                "cafe_id": 12,
                "name": "فرع جديد",
                "city": "بنغازي",
                "street": "شارع جمال",
                "latitude": "27.100000",
                "longitude": "17.100000",
                "delivery_zone_id": null,
                "is_active": true,
                "created_at": "2026-08-13 20:09:19"
            }
        ]
    },
    "user": {
        "id": 15,
        "name": "Cafe Demo",
        "email": "cafe.demo@example.com",
        "mobile_number": "0911111111"
    }
}
```

## Step 4b: PUT /api/v1/cafe/profile — Update cafe profile
**Request:** `PUT /api/v1/cafe/profile`

**Body:**

```json
{
    "name": "مقهى اختبار",
    "contact_info": "0911111111"
}
```

**Response:** `200` — the updated cafe object.

## Step 5: GET /api/v1/cafe/branches — List cafe branches
**Request:** `GET /api/v1/cafe/branches`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 13,
            "cafe_id": 12,
            "name": "فرع رئيسي",
            "city": "طرابلس",
            "street": "الشارع الرئيسي",
            "latitude": "27.000000",
            "longitude": "17.000000",
            "delivery_zone_id": 12,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "cafe": {
                "id": 12,
                "name": "مقهى اختبار",
                "contact_info": "0911111111",
                "image": null,
                "created_by_admin_id": null,
                "is_active": true,
                "created_at": 1786651759,
                "image_url": null
            },
            "delivery_zone": {
                "id": 12,
                "hex_id": "842da29ffffffff",
                "name": "منطقة اختبار",
                "delivery_price": "5.00",
                "latitude": "27.00000000",
                "longitude": "17.00000000",
                "is_active": true,
                "created_at": "2026-08-13T20:09:28.000000Z",
                "updated_at": "2026-08-13T20:09:28.000000Z"
            }
        },
        {
            "id": 14,
            "cafe_id": 12,
            "name": "فرع جديد",
            "city": "بنغازي",
            "street": "شارع جمال",
            "latitude": "27.100000",
            "longitude": "17.100000",
            "delivery_zone_id": null,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "cafe": {
                "id": 12,
                "name": "مقهى اختبار",
                "contact_info": "0911111111",
                "image": null,
                "created_by_admin_id": null,
                "is_active": true,
                "created_at": 1786651759,
                "image_url": null
            },
            "delivery_zone": null
        },
        {
            "id": 15,
            "cafe_id": 12,
            "name": "فرع جديد",
            "city": "بنغازي",
            "street": "شارع جمال",
            "latitude": "27.100000",
            "longitude": "17.100000",
            "delivery_zone_id": null,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "cafe": {
                "id": 12,
                "name": "مقهى اختبار",
                "contact_info": "0911111111",
                "image": null,
                "created_by_admin_id": null,
                "is_active": true,
                "created_at": 1786651759,
                "image_url": null
            },
            "delivery_zone": null
        },
        {
            "id": 16,
            "cafe_id": 12,
            "name": "فرع جديد",
            "city": "بنغازي",
            "street": "شارع جمال",
            "latitude": "27.100000",
            "longitude": "17.100000",
            "delivery_zone_id": null,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "cafe": {
                "id": 12,
                "name": "مقهى اختبار",
                "contact_info": "0911111111",
                "image": null,
                "created_by_admin_id": null,
                "is_active": true,
                "created_at": 1786651759,
                "image_url": null
            },
            "delivery_zone": null
        }
    ]
}
```

## Step 5b: GET /api/v1/cafe/branches/{id} — Get one branch
**Request:** `GET /api/v1/cafe/branches/13`

**Response:** `200` — the branch with `cafe` and `delivery_zone`.

## Step 5c: GET /api/v1/cafe/delivery-zones — List active delivery zones
**Request:** `GET /api/v1/cafe/delivery-zones`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 12,
            "hex_id": "842da29ffffffff",
            "name": "منطقة اختبار",
            "delivery_price": "5.00",
            "latitude": "27.00000000",
            "longitude": "17.00000000"
        }
    ]
}
```

## Step 6: GET /api/v1/cafe/branches/13/orders — List branch orders
**Request:** `GET /api/v1/cafe/branches/13/orders`

**Query params (optional):** `status`, `page`, `per_page`

**Response:** `200` (paginated)

```json
{
    "data": [],
    "current_page": 1,
    "per_page": 15,
    "total": 0
}
```

## Step 7: POST /api/v1/cafe/branches — Create cafe branch
**Request:** `POST /api/v1/cafe/branches`

**Body:**

```json
{
    "name": "فرع جديد",
    "city": "بنغازي",
    "street": "شارع جمال",
    "latitude": 27.1,
    "longitude": 17.1,
    "is_active": true
}
```

**Response:** `201`

```json
{
    "success": true,
    "message": "تم إنشاء الفرع بنجاح",
    "data": {
        "id": 17,
        "name": "فرع جديد"
    }
}
```

## Step 7b: PUT /api/v1/cafe/branches/{id} — Update cafe branch
**Request:** `PUT /api/v1/cafe/branches/17`

**Body:** same fields as create (`name`, `city`, `street`, `latitude`, `longitude`, `delivery_zone_id`, `is_active`).

**Response:** `200` — the updated branch with `cafe` and `delivery_zone`.

## Step 7c: DELETE /api/v1/cafe/branches/{id} — Delete cafe branch
**Request:** `DELETE /api/v1/cafe/branches/17`

A branch that has orders cannot be deleted.

**Response:** `200`

```json
{
    "message": "تم حذف الفرع بنجاح"
}
```

> Addresses are soft-deleted. Orders keep their own copy of the delivery address, so deleting an address that has orders is allowed.

## Step 8: GET /api/v1/cafe/orders — List cafe orders
**Request:** `GET /api/v1/cafe/orders`

**Query params (optional):** `status`, `address_id`, `from` (YYYY-MM-DD), `to` (YYYY-MM-DD), `page`, `per_page`

**Response:** `200` (paginated)

```json
{
    "data": [],
    "current_page": 1,
    "per_page": 15,
    "total": 0
}
```

## Step 9: POST /api/v1/cafe/orders — Create cafe order
**Request:** `POST /api/v1/cafe/orders`

**Body:**

```json
{
    "address_id": 13,
    "items": [
        {
            "product_variant_id": 12,
            "quantity": 2
        }
    ]
}
```

> `unit_price` is always taken server-side from the product variant price; client-sent prices are ignored.

**Response:** `201`

```json
{
    "success": true,
    "message": "تم إنشاء الطلب بنجاح",
    "data": {
        "id": 5,
        "status": "pending",
        "total_amount": "25.00"
    }
}
```

## Step 9b: PUT /api/v1/cafe/orders/{id}/status — Confirm order receipt
**Request:** `PUT /api/v1/cafe/orders/5/status`

The cafe can only set `received`, and only after the delegate has marked the order as `delivered`. Every change is recorded in `order_status_log`.

**Body:**

```json
{
    "status": "received"
}
```

**Response:** `200`

```json
{
    "id": 5,
    "status": "received",
    ...
}
```

**Response (order not delivered yet):** `422`

```json
{
    "success": false,
    "message": "لا يمكن تأكيد الاستلام إلا بعد التسليم"
}
```

## Step 9c: POST /api/v1/cafe/orders/{id}/cancel-request — Request cancellation
**Request:** `POST /api/v1/cafe/orders/5/cancel-request`

The cafe cannot cancel an order by itself; it sends a cancellation request that the admin reviews (via `PUT /api/v1/orders/{id}` with `status: cancelled`).

Only `pending` orders can be requested for cancellation.

**Response:** `200`

```json
{
    "id": 5,
    "status": "cancellation_requested",
    "message": "تم إرسال طلب الإلغاء، سيتم مراجعته من الإدارة"
}
```

**Response (order already processed):** `422`

```json
{
    "success": false,
    "message": "لا يمكن طلب الإلغاء إلا للطلبات قيد الانتظار"
}
```

## Step 10: GET /api/v1/cafe/promos — List active promos
**Request:** `GET /api/v1/cafe/promos`

**Response:** `200`

```json
[
    {
        "id": 1,
        "image": "promos/banner-1.png",
        "description": "خصم 20% على جميع المشروبات",
        "link": "/products",
        "show_description": true,
        "is_active": true,
        "image_url": "/storage/promos/banner-1.png",
        "created_at": "2026-09-12T10:00:00.000000Z",
        "updated_at": "2026-09-12T10:00:00.000000Z"
    }
]
```

> `link` can be an internal deep-link path (e.g. `/products`) or a full external URL. `show_description` tells the mobile app whether to display the promo description text.

## Step 11: GET /api/v1/cafe/categories — List categories
**Request:** `GET /api/v1/cafe/categories`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 1,
            "name": "مشروبات ساخنة",
            "parent_category_id": null,
            "parent_category": null
        },
        {
            "id": 2,
            "name": "مشروبات باردة",
            "parent_category_id": null,
            "parent_category": null
        }
    ]
}
```

## Step 12: GET /api/v1/cafe/products — List products
**Request:** `GET /api/v1/cafe/products`

**Query params (optional):** `category_id`, `search`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 1,
            "name": "قهوة تركية",
            "image_url": null,
            "min_price": "4.00",
            "category_id": 1,
            "default_variant_id": 1
        },
        {
            "id": 2,
            "name": "كابتشينو",
            "image_url": null,
            "min_price": "5.50",
            "category_id": 1,
            "default_variant_id": 3
        }
    ]
}
```

## Step 12b: GET /api/v1/cafe/products?category_id=1 — List products by category
**Request:** `GET /api/v1/cafe/products?category_id=1`

**Response:** `200` — same shape as Step 12, filtered by category.

## Step 12c: GET /api/v1/cafe/products?search=قهوة — Search products
**Request:** `GET /api/v1/cafe/products?search=قهوة`

Searches by product name, tag, or variant name.

**Response:** `200` — same shape as Step 12.

## Step 13: GET /api/v1/cafe/products/{id} — Get product details
**Request:** `GET /api/v1/cafe/products/1`

**Response:** `200`

```json
{
    "id": 1,
    "name": "قهوة تركية",
    "description": "قهوة تركية من أفضل المنتجات",
    "image_url": null,
    "category": {
        "id": 1,
        "name": "مشروبات ساخنة",
        "parent_category_id": null
    }
}
```

## Step 13b: GET /api/v1/cafe/products/{id}/variants — Get product variants
**Request:** `GET /api/v1/cafe/products/1/variants`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 1,
            "product_id": 1,
            "name": "250 جم",
            "sku": "PRD-0001-01",
            "barcode": null,
            "price": "12.00",
            "cost_price": "8.40",
            "is_active": true,
            "in_stock": 320,
            "images": []
        }
    ]
}
```

## Step 14: POST /api/v1/cafe/register — Create a cafe account (user only)
**Request:** `POST /api/v1/cafe/register`

The account is created active with **no cafe attached**. The owner logs in, then adds the cafe from inside the app (Step 14b/14c).

```json
{
    "name": "صاحب المقهى",
    "phone_number": "0922222222",
    "email": "newcafe@example.com",
    "password": "secret123"
}
```

**Response:** `201`

```json
{
    "success": true,
    "message": "تم إنشاء الحساب بنجاح، يمكنك تسجيل الدخول الآن",
    "data": {
        "user": {
            "name": "صاحب المقهى",
            "phone_number": "0922222222"
        }
    }
}
```

## Step 14b: GET /api/v1/cafe/profile — First call after login: does the user have a cafe?
**Request:** `GET /api/v1/cafe/profile` (Bearer token)

Login itself also returns `has_cafe` for cafe users: `{"token": "...", "has_cafe": false}`.

**Response:** `200` (no cafe yet)

```json
{
    "has_cafe": false,
    "cafe": null,
    "user": { "id": 21, "name": "صاحب المقهى", "email": null, "mobile_number": "0922222222" }
}
```

While `has_cafe` is false, every other `/cafe/*` endpoint (orders, cart, branches, dashboard) returns `403` with `"has_cafe": false`. While the cafe exists but is not approved yet they return `403` with `"cafe_active": false`.

## Step 14c: POST /api/v1/cafe/profile — Add the cafe (pending admin approval)
**Request:** `POST /api/v1/cafe/profile` (Bearer token)

**Body:** multipart/form-data. `contact_info` defaults to the user's phone number.

```json
{
    "name": "مقهى جديد",
    "contact_info": "0922222222",
    "logo": "<image file>"
}
```

**Response:** `201`

```json
{
    "success": true,
    "message": "تم إرسال طلب التسجيل بنجاح، سيتم التواصل معك بعد الموافقة",
    "data": {
        "cafe": {
            "id": 20,
            "name": "مقهى جديد",
            "contact_info": "0922222222",
            "image_url": "http://localhost/storage/cafes/...",
            "is_active": false
        }
    }
}
```

`409` if the user already has a cafe. Admins approve or reject via `/cafe-registrations/{id}/approve|reject`; rejecting deletes the cafe but keeps the account so the owner can submit again.

> The account can log in immediately. Only the cafe waits for admin approval; until then ordering endpoints return 403.

## Step 15: POST /api/v1/login (phone or email) for approved cafes
**Request:** `POST /api/v1/login`

**Body:**

```json
{
    "phone_number": "0922222222",
    "password": "secret123"
}
```

**Response (approved cafe):** `200`

```json
{
    "token": "...",
    "permissions": [
        "ORDERS_VIEW",
        "ORDERS_EDIT",
        ...
    ]
}
```

**Response (pending cafe):** `403`

```json
{
    "success": false,
    "message": "الحساب غير نشط، يرجى انتظار موافقة الإدارة"
}
```

---

# Admin Cafe Registration Approval

Base URL: `http://localhost/api/v1`

These endpoints require an admin or super_admin bearer token.

## List pending registrations
**Request:** `GET /api/v1/cafe-registrations/pending`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 20,
            "name": "مقهى جديد",
            "contact_info": "0922222222",
            "address": "طرابلس، شارع الرشيد",
            "latitude": "32.88720000",
            "longitude": "13.19130000",
            "is_active": false,
            "app_users": [
                {
                    "id": 25,
                    "name": "مقهى جديد",
                    "email": "newcafe@example.com",
                    "mobile_number": "0922222222",
                    "is_active": false
                }
            ]
        }
    ]
}
```

## Approve a registration
**Request:** `POST /api/v1/cafe-registrations/{cafe}/approve`

**Response:** `200`

```json
{
    "success": true,
    "message": "تمت الموافقة على الطلب بنجاح"
}
```

## Reject a registration
**Request:** `POST /api/v1/cafe-registrations/{cafe}/reject`

**Response:** `200`

```json
{
    "success": true,
    "message": "تم رفض الطلب بنجاح"
}
```

> Rejecting deletes the pending cafe and its owner user.

---

# Cafe Mobile Cart & Checkout

## GET /api/v1/cafe/cart — Current cart
**Request:** `GET /api/v1/cafe/cart`

**Response:** `200`

```json
{
    "data": {
        "id": 1,
        "user_id": 15,
        "type": "shopping",
        "name": null,
        "subtotal": 20,
        "items": [
            {
                "id": 1,
                "product_variant_id": 12,
                "quantity": 2,
                "product_variant": {
                    "id": 12,
                    "sku": "DEMO-001",
                    "name": "افتراضي",
                    "price": "10.00",
                    "product": {
                        "id": 12,
                        "name": "منتج اختبار"
                    }
                }
            }
        ]
    }
}
```

## POST /api/v1/cafe/cart/items — Add item
**Request:** `POST /api/v1/cafe/cart/items`

**Body:**

```json
{
    "product_variant_id": 12,
    "quantity": 2
}
```

The cart is not tied to an address. The address is chosen at checkout:
`POST /api/v1/cafe/cart/checkout` with `{ "address_id": 13 }`.

## Recurring carts — `/api/v1/cafe/recurring-carts`

Named carts the customer re-orders from (the order that always repeats).

- `GET /cafe/recurring-carts` — list with items and current subtotal
- `POST /cafe/recurring-carts` — `{ "name": "الطلبية الأسبوعية", "items": [{ "product_variant_id": 12, "quantity": 2 }] }`
- `PUT /cafe/recurring-carts/{id}` — rename and/or replace items
- `DELETE /cafe/recurring-carts/{id}`
- `POST /cafe/recurring-carts/{id}/order` — `{ "address_id": 13 }`; creates an order at current prices, the cart is kept

**Response:** `201`

## PUT /api/v1/cafe/cart/items/{id} — Update quantity
**Request:** `PUT /api/v1/cafe/cart/items/1`

**Body:**

```json
{
    "quantity": 5
}
```

**Response:** `200`

## DELETE /api/v1/cafe/cart/items/{id} — Remove item
**Request:** `DELETE /api/v1/cafe/cart/items/1`

**Response:** `200`

## DELETE /api/v1/cafe/cart — Clear cart
**Request:** `DELETE /api/v1/cafe/cart`

**Response:** `200`

```json
{
    "success": true,
    "message": "تم إفراغ السلة"
}
```

## POST /api/v1/cafe/cart/checkout — Checkout
**Request:** `POST /api/v1/cafe/cart/checkout`

**Response:** `201`

```json
{
    "success": true,
    "message": "تم إنشاء الطلب بنجاح",
    "data": {
        "id": 6,
        "status": "pending",
        "total_amount": "25.00",
        "delegate_id": null
    }
}
```

---

# Cafe Mobile Delegate Tracking

## GET /api/v1/cafe/orders/{id}/delegate — Delegate location for my order
**Request:** `GET /api/v1/cafe/orders/6/delegate`

**Response:** `200`

```json
{
    "data": {
        "id": 5,
        "name": "مندوب التوصيل",
        "latitude": "27.10000000",
        "longitude": "17.10000000",
        "is_available": true,
        "location_updated_at": "2026-08-21 14:30:00"
    }
}
```

> For real-time updates the mobile app can listen to the Reverb channel `delegates.locations` event `delegate.location.updated`.

---

# Cafe Mobile Dashboard

## GET /api/v1/cafe/dashboard — Dashboard analytics
**Request:** `GET /api/v1/cafe/dashboard`

**Response:** `200`

```json
{
    "cafe": {
        "id": 12,
        "name": "مقهى اختبار",
        "contact_info": "0911111111"
    },
    "stats": {
        "orders": 15,
        "branches": 3,
        "revenue": "1,250.00",
        "pending_orders": 2
    },
    "periodStats": {
        "today": {
            "orders": 2,
            "revenue": "150.00"
        },
        "this_week": {
            "orders": 8,
            "revenue": "650.00"
        },
        "this_month": {
            "orders": 15,
            "revenue": "1,250.00"
        }
    },
    "ordersByStatus": {
        "pending": 2,
        "delivered": 12,
        "cancelled": 1
    },
    "recentOrders": [...],
    "monthlyRevenue": [...],
    "topProducts": [
        {
            "product_variant_id": 12,
            "product_name": "منتج اختبار",
            "variant_value": "افتراضي",
            "total_quantity": 42
        }
    ],
    "branchesComparison": [
        {
            "id": 13,
            "name": "فرع رئيسي",
            "orders_count": 10,
            "revenue": "800.00"
        }
    ]
}
```
