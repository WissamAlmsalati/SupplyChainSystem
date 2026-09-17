# Customer Mobile API Endpoints Demo

Base URL: `http://localhost/api/v1`

## Step 1: Setup test data

## Step 2: POST /api/v1/login
**Request:** `POST /api/v1/login`

**Body:**

```json
{
    "email": "customer.demo@example.com",
    "password": "password"
}
```

**Response:** `200`

```json
{
    "token": "15|r7c8lp94gxAOIOtup9q6xZ3KV5mVsMGHDzLvPTFc1020710d",
    "permissions": [
        "CUSTOMER_BRANCHES_CREATE",
        "CUSTOMER_BRANCHES_DELETE",
        "CUSTOMER_BRANCHES_EDIT",
        "CUSTOMER_BRANCHES_VIEW",
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
    "name": "Customer Demo",
    "email": "customer.demo@example.com",
    "mobile_number": "0911111111",
    "user_type_id": 45,
    "customer_id": 12,
    "is_active": true,
    "created_at": "2026-08-13T20:16:33.000000Z",
    "updated_at": "2026-08-13T20:16:33.000000Z",
    "user_type": {
        "id": 45,
        "name": "customer",
        "permissions": [
            {
                "id": 93,
                "code": "CUSTOMER_BRANCHES_CREATE",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 93
                }
            },
            {
                "id": 95,
                "code": "CUSTOMER_BRANCHES_DELETE",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 95
                }
            },
            {
                "id": 94,
                "code": "CUSTOMER_BRANCHES_EDIT",
                "pivot": {
                    "user_type_id": 45,
                    "permission_id": 94
                }
            },
            {
                "id": 92,
                "code": "CUSTOMER_BRANCHES_VIEW",
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
    "customer": {
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

## Step 4: GET /api/v1/customer/profile — Get customer profile
**Request:** `GET /api/v1/customer/profile`

**Response:** `200`

```json
{
    "customer": {
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
                "customer_id": 12,
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
                "customer_id": 12,
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
                "customer_id": 12,
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
                "customer_id": 12,
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
        "name": "Customer Demo",
        "email": "customer.demo@example.com",
        "mobile_number": "0911111111"
    }
}
```

## Step 4b: PUT /api/v1/customer/profile — Update customer profile
**Request:** `PUT /api/v1/customer/profile`

**Body:**

```json
{
    "name": "مقهى اختبار",
    "contact_info": "0911111111"
}
```

**Response:** `200` — the updated customer object.

## Step 5: GET /api/v1/customer/branches — List customer branches
**Request:** `GET /api/v1/customer/branches`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 13,
            "customer_id": 12,
            "name": "فرع رئيسي",
            "city": "طرابلس",
            "street": "الشارع الرئيسي",
            "latitude": "27.000000",
            "longitude": "17.000000",
            "delivery_zone_id": 12,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "customer": {
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
            "customer_id": 12,
            "name": "فرع جديد",
            "city": "بنغازي",
            "street": "شارع جمال",
            "latitude": "27.100000",
            "longitude": "17.100000",
            "delivery_zone_id": null,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "customer": {
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
            "customer_id": 12,
            "name": "فرع جديد",
            "city": "بنغازي",
            "street": "شارع جمال",
            "latitude": "27.100000",
            "longitude": "17.100000",
            "delivery_zone_id": null,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "customer": {
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
            "customer_id": 12,
            "name": "فرع جديد",
            "city": "بنغازي",
            "street": "شارع جمال",
            "latitude": "27.100000",
            "longitude": "17.100000",
            "delivery_zone_id": null,
            "is_active": true,
            "created_at": "2026-08-13 20:09:19",
            "customer": {
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

## Step 5b: GET /api/v1/customer/branches/{id} — Get one branch
**Request:** `GET /api/v1/customer/branches/13`

**Response:** `200` — the branch with `customer` and `delivery_zone`.

## Step 5c: GET /api/v1/customer/delivery-zones — List active delivery zones
**Request:** `GET /api/v1/customer/delivery-zones`

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

## Step 6: GET /api/v1/customer/branches/13/orders — List branch orders
**Request:** `GET /api/v1/customer/branches/13/orders`

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

## Step 7: POST /api/v1/customer/branches — Create customer branch
**Request:** `POST /api/v1/customer/branches`

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

## Step 7b: PUT /api/v1/customer/branches/{id} — Update customer branch
**Request:** `PUT /api/v1/customer/branches/17`

**Body:** same fields as create (`name`, `city`, `street`, `latitude`, `longitude`, `delivery_zone_id`, `is_active`).

**Response:** `200` — the updated branch with `customer` and `delivery_zone`.

## Step 7c: DELETE /api/v1/customer/branches/{id} — Delete customer branch
**Request:** `DELETE /api/v1/customer/branches/17`

A branch that has orders cannot be deleted.

**Response:** `200`

```json
{
    "message": "تم حذف الفرع بنجاح"
}
```

> Addresses are soft-deleted. Orders keep their own copy of the delivery address, so deleting an address that has orders is allowed.

## Step 8: GET /api/v1/customer/orders — List customer orders
**Request:** `GET /api/v1/customer/orders`

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

## Step 9: POST /api/v1/customer/orders — Create customer order
**Request:** `POST /api/v1/customer/orders`

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

## Step 9b: PUT /api/v1/customer/orders/{id}/status — Confirm order receipt
**Request:** `PUT /api/v1/customer/orders/5/status`

The customer can only set `received`, and only after the delegate has marked the order as `delivered`. Every change is recorded in `order_status_log`.

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

## Step 9c: POST /api/v1/customer/orders/{id}/cancel-request — Request cancellation
**Request:** `POST /api/v1/customer/orders/5/cancel-request`

The customer cannot cancel an order by itself; it sends a cancellation request that the admin reviews (via `PUT /api/v1/orders/{id}` with `status: cancelled`).

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

## Step 10: GET /api/v1/customer/promos — List active promos
**Request:** `GET /api/v1/customer/promos`

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

## Step 11: GET /api/v1/customer/categories — List categories
**Request:** `GET /api/v1/customer/categories`

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

## Step 12: GET /api/v1/customer/products — List products
**Request:** `GET /api/v1/customer/products`

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

## Step 12b: GET /api/v1/customer/products?category_id=1 — List products by category
**Request:** `GET /api/v1/customer/products?category_id=1`

**Response:** `200` — same shape as Step 12, filtered by category.

## Step 12c: GET /api/v1/customer/products?search=قهوة — Search products
**Request:** `GET /api/v1/customer/products?search=قهوة`

Searches by product name, tag, or variant name.

**Response:** `200` — same shape as Step 12.

## Step 13: GET /api/v1/customer/products/{id} — Get product details
**Request:** `GET /api/v1/customer/products/1`

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

## Step 13b: GET /api/v1/customer/products/{id}/variants — Get product variants
**Request:** `GET /api/v1/customer/products/1/variants`

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

## Step 14: POST /api/v1/customer/register — Create a customer account (user only)
**Request:** `POST /api/v1/customer/register`

The account is created active with **no customer attached**. The owner logs in, then adds the customer from inside the app (Step 14b/14c).

```json
{
    "name": "صاحب المقهى",
    "phone_number": "0922222222",
    "email": "newcustomer@example.com",
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

## Step 14b: GET /api/v1/customer/profile — First call after login: does the user have a customer?
**Request:** `GET /api/v1/customer/profile` (Bearer token)

Login itself also returns `has_customer` for customer users: `{"token": "...", "has_customer": false}`.

**Response:** `200` (no customer yet)

```json
{
    "has_customer": false,
    "customer": null,
    "user": { "id": 21, "name": "صاحب المقهى", "email": null, "mobile_number": "0922222222" }
}
```

While `has_customer` is false, every other `/customer/*` endpoint (orders, cart, branches, dashboard) returns `403` with `"has_customer": false`. While the customer exists but is not approved yet they return `403` with `"customer_active": false`.

## Step 14c: POST /api/v1/customer/profile — Add the customer (pending admin approval)
**Request:** `POST /api/v1/customer/profile` (Bearer token)

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
        "customer": {
            "id": 20,
            "name": "مقهى جديد",
            "contact_info": "0922222222",
            "image_url": "http://localhost/storage/customers/...",
            "is_active": false
        }
    }
}
```

`409` if the user already has a customer. Admins approve or reject via `/customer-registrations/{id}/approve|reject`; rejecting deletes the customer but keeps the account so the owner can submit again.

> The account can log in immediately. Only the customer waits for admin approval; until then ordering endpoints return 403.

## Step 15: POST /api/v1/login (phone or email) for approved customers
**Request:** `POST /api/v1/login`

**Body:**

```json
{
    "phone_number": "0922222222",
    "password": "secret123"
}
```

**Response (approved customer):** `200`

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

**Response (pending customer):** `403`

```json
{
    "success": false,
    "message": "الحساب غير نشط، يرجى انتظار موافقة الإدارة"
}
```

---

# Admin Customer Registration Approval

Base URL: `http://localhost/api/v1`

These endpoints require an admin or super_admin bearer token.

## List pending registrations
**Request:** `GET /api/v1/customer-registrations/pending`

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
                    "email": "newcustomer@example.com",
                    "mobile_number": "0922222222",
                    "is_active": false
                }
            ]
        }
    ]
}
```

## Approve a registration
**Request:** `POST /api/v1/customer-registrations/{customer}/approve`

**Response:** `200`

```json
{
    "success": true,
    "message": "تمت الموافقة على الطلب بنجاح"
}
```

## Reject a registration
**Request:** `POST /api/v1/customer-registrations/{customer}/reject`

**Response:** `200`

```json
{
    "success": true,
    "message": "تم رفض الطلب بنجاح"
}
```

> Rejecting deletes the pending customer and its owner user.

---

# Customer Mobile Cart & Checkout

## GET /api/v1/customer/cart — Current cart
**Request:** `GET /api/v1/customer/cart`

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

## POST /api/v1/customer/cart/items — Add item
**Request:** `POST /api/v1/customer/cart/items`

**Body:**

```json
{
    "product_variant_id": 12,
    "quantity": 2
}
```

The cart is not tied to an address. The address is chosen at checkout:
`POST /api/v1/customer/cart/checkout` with `{ "address_id": 13 }`.

## Recurring carts — `/api/v1/customer/recurring-carts`

Named carts the customer re-orders from (the order that always repeats).

- `GET /customer/recurring-carts` — list with items and current subtotal
- `POST /customer/recurring-carts` — `{ "name": "الطلبية الأسبوعية", "items": [{ "product_variant_id": 12, "quantity": 2 }] }`
- `PUT /customer/recurring-carts/{id}` — rename and/or replace items
- `DELETE /customer/recurring-carts/{id}`
- `POST /customer/recurring-carts/{id}/order` — `{ "address_id": 13 }`; creates an order at current prices, the cart is kept

**Response:** `201`

## PUT /api/v1/customer/cart/items/{id} — Update quantity
**Request:** `PUT /api/v1/customer/cart/items/1`

**Body:**

```json
{
    "quantity": 5
}
```

**Response:** `200`

## DELETE /api/v1/customer/cart/items/{id} — Remove item
**Request:** `DELETE /api/v1/customer/cart/items/1`

**Response:** `200`

## DELETE /api/v1/customer/cart — Clear cart
**Request:** `DELETE /api/v1/customer/cart`

**Response:** `200`

```json
{
    "success": true,
    "message": "تم إفراغ السلة"
}
```

## POST /api/v1/customer/cart/checkout — Checkout
**Request:** `POST /api/v1/customer/cart/checkout`

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

# Customer Mobile Delegate Tracking

## GET /api/v1/customer/orders/{id}/delegate — Delegate location for my order
**Request:** `GET /api/v1/customer/orders/6/delegate`

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

# Customer Mobile Dashboard

## GET /api/v1/customer/dashboard — Dashboard analytics
**Request:** `GET /api/v1/customer/dashboard`

**Response:** `200`

```json
{
    "customer": {
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
