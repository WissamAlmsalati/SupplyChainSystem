# Cafe Mobile API Endpoints Demo

Base URL: `http://localhost/api`

## Step 1: Setup test data

## Step 2: POST /login
**Request:** `POST /login`

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

## Step 3: GET /me — Get authenticated user
**Request:** `GET /me`

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

## Step 4: GET /cafe/profile — Get cafe profile
**Request:** `GET /cafe/profile`

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

## Step 5: GET /cafe/branches — List cafe branches
**Request:** `GET /cafe/branches`

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

## Step 6: GET /cafe/branches/13/orders — List branch orders
**Request:** `GET /cafe/branches/13/orders`

**Response:** `200`

```json
{
    "data": []
}
```

## Step 7: POST /cafe/branches — Create cafe branch
**Request:** `POST /cafe/branches`

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

## Step 8: GET /cafe/orders — List cafe orders
**Request:** `GET /cafe/orders`

**Response:** `200`

```json
{
    "data": []
}
```

## Step 9: POST /cafe/orders — Create cafe order
**Request:** `POST /cafe/orders`

**Body:**

```json
{
    "branch_id": 13,
    "items": [
        {
            "product_variant_id": 12,
            "quantity": 2,
            "unit_price": 10
        }
    ]
}
```

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

## Step 10: GET /cafe/categories — List categories
**Request:** `GET /cafe/categories`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 12,
            "name": "تصنيف اختبار",
            "parent_category_id": null,
            "parent_category": null
        }
    ]
}
```

## Step 11: GET /cafe/products — List products
**Request:** `GET /cafe/products`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 12,
            "name": "منتج اختبار",
            "description": "وصف المنتج",
            "image": null,
            "image_url": null
        }
    ]
}
```

## Step 12: GET /cafe/products?category_id=12 — List products by category
**Request:** `GET /cafe/products?category_id=12`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 12,
            "name": "منتج اختبار",
            "description": "وصف المنتج",
            "image": null,
            "image_url": null
        }
    ]
}
```

## Step 13: GET /cafe/products/12/variants — Get product variants
**Request:** `GET /cafe/products/12/variants`

**Response:** `200`

```json
{
    "data": [
        {
            "id": 12,
            "product_id": 12,
            "sku": "DEMO-001",
            "attribute_name": null,
            "attribute_value": "افتراضي",
            "price": "10.00",
            "is_active": true
        }
    ]
}
```
