<?php

namespace App\OpenApi;

/**
 * @OA\Schema(
 *     schema="AddressRequest",
 *     type="object",
 *     required={"name", "latitude", "longitude"},
 *     @OA\Property(property="user_id", type="integer", description="Required for admin requests; customers always create addresses for themselves."),
 *     @OA\Property(property="name", type="string", maxLength=100),
 *     @OA\Property(property="city", type="string", nullable=true, maxLength=100),
 *     @OA\Property(property="street", type="string", nullable=true, maxLength=200),
 *     @OA\Property(property="latitude", type="number", format="float"),
 *     @OA\Property(property="longitude", type="number", format="float"),
 *     @OA\Property(property="delivery_zone_id", type="integer", nullable=true),
 *     @OA\Property(property="is_default", type="boolean"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="contact_phones", type="array", nullable=true, @OA\Items(type="string", maxLength=20)),
 * )
 *
 * @OA\Schema(
 *     schema="DeliveryZoneRequest",
 *     type="object",
 *     required={"hex_id", "delivery_price"},
 *     @OA\Property(property="warehouse_id", type="integer", nullable=true),
 *     @OA\Property(property="hex_id", type="string", maxLength=40),
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
 *     @OA\Property(property="brand", type="string", nullable=true, maxLength=100),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="tags", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="image", type="string", format="binary", nullable=true, description="Stored as the product's primary image"),
 * )
 *
 * @OA\Schema(
 *     schema="ProductVariantRequest",
 *     type="object",
 *     required={"product_id", "name", "price"},
 *     description="A sellable size of a product. Stock lives in inventories; expiry dates on purchase order items.",
 *     @OA\Property(property="product_id", type="integer"),
 *     @OA\Property(property="name", type="string", maxLength=100, example="500 جم"),
 *     @OA\Property(property="sku", type="string", nullable=true, maxLength=50, description="Generated when empty"),
 *     @OA\Property(property="barcode", type="string", nullable=true, maxLength=100),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="cost_price", type="number", format="float", nullable=true),
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
 *     description="POST adds quantity to stock; PUT sets the counted on-hand quantity. Both write a stock movement.",
 *     @OA\Property(property="warehouse_id", type="integer", description="POST only"),
 *     @OA\Property(property="product_variant_id", type="integer", description="POST only"),
 *     @OA\Property(property="quantity", type="integer"),
 *     @OA\Property(property="note", type="string", nullable=true, maxLength=255),
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
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="latitude", type="number", nullable=true, description="Stored on delegate_profiles"),
 *     @OA\Property(property="longitude", type="number", nullable=true, description="Stored on delegate_profiles"),
 *     @OA\Property(property="is_available", type="boolean", description="Stored on delegate_profiles"),
 * )
 *
 * @OA\Schema(
 *     schema="OrderItemRequest",
 *     type="object",
 *     required={"product_variant_id", "quantity"},
 *     description="Unit price is always taken from the variant on the server.",
 *     @OA\Property(property="product_variant_id", type="integer"),
 *     @OA\Property(property="quantity", type="integer", minimum=1),
 * )
 *
 * @OA\Schema(
 *     schema="OrderRequest",
 *     type="object",
 *     required={"user_id", "address_id", "items"},
 *     description="Totals, delivery fee and the delivery address snapshot are computed on the server.",
 *     @OA\Property(property="user_id", type="integer", description="The customer the order is for"),
 *     @OA\Property(property="address_id", type="integer", description="Must belong to the customer"),
 *     @OA\Property(property="delegate_id", type="integer", nullable=true),
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
 *     required={"address_id", "items"},
 *     @OA\Property(property="address_id", type="integer"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/OrderItemRequest")
 *     ),
 * )
 *
 * @OA\Schema(
 *     schema="RecurringCartRequest",
 *     type="object",
 *     required={"name", "items"},
 *     @OA\Property(property="name", type="string", maxLength=100, example="الطلبية الأسبوعية"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         description="On update, replaces all items",
 *         @OA\Items(ref="#/components/schemas/OrderItemRequest")
 *     ),
 * )
 *
 * @OA\Schema(
 *     schema="PurchaseOrderRequest",
 *     type="object",
 *     required={"warehouse_id"},
 *     description="Created as draft. POST /purchase-orders/{id}/receive adds the items to stock.",
 *     @OA\Property(property="warehouse_id", type="integer"),
 *     @OA\Property(property="note", type="string", nullable=true, maxLength=255),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         description="On update, replaces all items",
 *         @OA\Items(
 *             type="object",
 *             required={"product_variant_id", "quantity", "unit_cost"},
 *             @OA\Property(property="product_variant_id", type="integer"),
 *             @OA\Property(property="quantity", type="integer"),
 *             @OA\Property(property="unit_cost", type="number", format="float"),
 *             @OA\Property(property="manufacturing_year", type="integer", nullable=true),
 *             @OA\Property(property="expiry_date", type="string", format="date", nullable=true)
 *         )
 *     ),
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
