<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="CafeRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", maxLength=150),
 *     @OA\Property(property="contact_info", type="string", nullable=true, maxLength=200),
 *     @OA\Property(property="created_by_admin_id", type="integer", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="CafeBranchRequest",
 *     type="object",
 *     required={"cafe_id", "name", "latitude", "longitude"},
 *     @OA\Property(property="cafe_id", type="integer"),
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="city", type="string", nullable=true, maxLength=100),
 *     @OA\Property(property="street", type="string", nullable=true, maxLength=200),
 *     @OA\Property(property="latitude", type="number", format="float"),
 *     @OA\Property(property="longitude", type="number", format="float"),
 *     @OA\Property(property="delivery_zone_id", type="integer", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="DeliveryZoneRequest",
 *     type="object",
 *     required={"hex_id", "delivery_price"},
 *     @OA\Property(property="hex_id", type="string", maxLength=30),
 *     @OA\Property(property="name", type="string", nullable=true, maxLength=100),
 *     @OA\Property(property="delivery_price", type="number", format="float"),
 *     @OA\Property(property="is_active", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="CategoryRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="parent_category_id", type="integer", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="ProductRequest",
 *     type="object",
 *     required={"category_id", "name"},
 *     @OA\Property(property="category_id", type="integer"),
 *     @OA\Property(property="name", type="string", maxLength=150),
 *     @OA\Property(property="description", type="string", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="ProductVariantRequest",
 *     type="object",
 *     required={"product_id", "sku", "price"},
 *     @OA\Property(property="product_id", type="integer"),
 *     @OA\Property(property="sku", type="string", maxLength=50),
 *     @OA\Property(property="attribute_name", type="string", nullable=true, maxLength=50),
 *     @OA\Property(property="attribute_value", type="string", nullable=true, maxLength=50),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="is_active", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="WarehouseRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="city", type="string", nullable=true, maxLength=100),
 *     @OA\Property(property="latitude", type="number", format="float", nullable=true),
 *     @OA\Property(property="longitude", type="number", format="float", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="InventoryRequest",
 *     type="object",
 *     required={"warehouse_id", "product_variant_id", "quantity"},
 *     @OA\Property(property="warehouse_id", type="integer"),
 *     @OA\Property(property="product_variant_id", type="integer"),
 *     @OA\Property(property="quantity", type="integer"),
 * )
 *
 * @OA\Schema(
 *     schema="AuthLoginRequest",
 *     type="object",
 *     required={"password"},
 *     description="Cafe users must login with phone_number and password only. Email is allowed for admin and delegate users only.",
 *     @OA\Property(property="email", type="string", format="email", nullable=true, description="Use for admin/delegate login only. Cafe users must NOT use email."),
 *     @OA\Property(property="phone_number", type="string", nullable=true, description="Required for cafe users. Example: 0912345678"),
 *     @OA\Property(property="password", type="string", format="password"),
 * )
 *
 * @OA\Schema(
 *     schema="AuthRegisterRequest",
 *     type="object",
 *     required={"name", "email", "password"},
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="email", type="string", format="email", maxLength=150),
 *     @OA\Property(property="password", type="string", format="password", minLength=6),
 *     @OA\Property(property="mobile_number", type="string", nullable=true, maxLength=20),
 *     @OA\Property(property="cafe_id", type="integer", nullable=true),
 * )
 *
 * @OA\Schema(
 *     schema="CafeRegisterRequest",
 *     type="object",
 *     required={"name", "phone_number", "password"},
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="phone_number", type="string", maxLength=20),
 *     @OA\Property(property="email", type="string", format="email", nullable=true, maxLength=150),
 *     @OA\Property(property="password", type="string", format="password", minLength=6),
 * )
 *
 * @OA\Schema(
 *     schema="AuthResponse",
 *     type="object",
 *     description="For cafe users the response contains only the bearer token. Admin and delegate responses also include permissions.",
 *     @OA\Property(property="token", type="string", description="Bearer token to use in the Authorization header"),
 *     @OA\Property(property="permissions", type="array", nullable=true, @OA\Items(type="string"), description="Included for admin/delegate users only. Omitted for cafe users."),
 * )
 *
 * @OA\Schema(
 *     schema="AppUserRequest",
 *     type="object",
 *     required={"name", "email", "password", "user_type_id"},
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="email", type="string", format="email", maxLength=150),
 *     @OA\Property(property="mobile_number", type="string", nullable=true, maxLength=20),
 *     @OA\Property(property="password", type="string", format="password"),
 *     @OA\Property(property="user_type_id", type="integer"),
 *     @OA\Property(property="cafe_id", type="integer", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="DelegateRequest",
 *     type="object",
 *     required={"name", "email", "password"},
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="email", type="string", format="email", maxLength=150),
 *     @OA\Property(property="mobile_number", type="string", nullable=true, maxLength=20),
 *     @OA\Property(property="password", type="string", format="password", minLength=6),
 *     @OA\Property(property="cafe_id", type="integer", nullable=true),
 *     @OA\Property(property="is_active", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="OrderItemRequest",
 *     type="object",
 *     required={"product_variant_id", "quantity", "unit_price"},
 *     @OA\Property(property="product_variant_id", type="integer"),
 *     @OA\Property(property="quantity", type="integer"),
 *     @OA\Property(property="unit_price", type="number", format="float"),
 * )
 *
 * @OA\Schema(
 *     schema="OrderRequest",
 *     type="object",
 *     required={"user_id", "branch_id", "status", "total_amount"},
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="branch_id", type="integer"),
 *     @OA\Property(property="delegate_id", type="integer", nullable=true),
 *     @OA\Property(property="delivery_zone_id", type="integer", nullable=true),
 *     @OA\Property(property="delivery_fee", type="number", format="float"),
 *     @OA\Property(property="order_date", type="string", format="date-time"),
 *     @OA\Property(property="status", type="string", maxLength=30),
 *     @OA\Property(property="total_amount", type="number", format="float"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/OrderItemRequest")
 *     ),
 * )
 *
 * @OA\Schema(
 *     schema="CafeOrderRequest",
 *     type="object",
 *     required={"branch_id", "items"},
 *     @OA\Property(property="branch_id", type="integer"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/OrderItemRequest")
 *     ),
 * )
 *
 * @OA\Schema(
 *     schema="CartRequest",
 *     type="object",
 *     required={"user_id", "branch_id"},
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="branch_id", type="integer"),
 *     @OA\Property(property="status", type="string", maxLength=20),
 * )
 *
 * @OA\Schema(
 *     schema="PurchaseOrderRequest",
 *     type="object",
 *     required={"warehouse_id", "status"},
 *     @OA\Property(property="warehouse_id", type="integer"),
 *     @OA\Property(property="order_date", type="string", format="date-time"),
 *     @OA\Property(property="status", type="string", maxLength=20),
 * )
 *
 * @OA\Schema(
 *     schema="DelegateLocationRequest",
 *     type="object",
 *     required={"latitude", "longitude"},
 *     @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90),
 *     @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180),
 * )
 *
 * @OA\Schema(
 *     schema="DelegateAvailabilityRequest",
 *     type="object",
 *     required={"is_available"},
 *     @OA\Property(property="is_available", type="boolean"),
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         additionalProperties={"type": "array", "items": {"type": "string"}}
 *     ),
 * )
 */
class Schemas
{
    //
}
