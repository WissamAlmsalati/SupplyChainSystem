<?php

namespace App\OpenApi;

/**
 * @OA\Schema(schema="CafeAddressDetail", type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=3),
 *     @OA\Property(property="name", type="string", example="فرع طرابلس الرئيسي"),
 *     @OA\Property(property="city", type="string", example="طرابلس"),
 *     @OA\Property(property="street", type="string", example="شارع الجمهورية"),
 *     @OA\Property(property="full_address", type="string", example="شارع الجمهورية، طرابلس"),
 *     @OA\Property(property="contact_phones", type="array", nullable=true, @OA\Items(type="string", example="0910000001")),
 *     @OA\Property(property="latitude", type="string", example="32.88720000"),
 *     @OA\Property(property="longitude", type="string", example="13.19130000"),
 *     @OA\Property(property="delivery_zone_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", nullable=true),
 *     @OA\Property(property="delivery_zone", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="منطقة طرابلس"),
 *         @OA\Property(property="delivery_price", type="string", example="6.00")),
 *     @OA\Property(property="delivery_price", type="number", nullable=true, example=6)
 * )
 *
 * @OA\Schema(schema="CafeOrderCard", type="object",
 *     @OA\Property(property="id", type="integer", example=39),
 *     @OA\Property(property="order_number", type="string", example="ORD-2026-00039"),
 *     @OA\Property(property="status", type="string", example="received", enum={"pending","confirmed","preparing","out_for_delivery","delivered","received","cancellation_requested","cancelled"}),
 *     @OA\Property(property="status_label", type="string", example="مستلم"),
 *     @OA\Property(property="source", type="string", enum={"app","dashboard"}),
 *     @OA\Property(property="items_count", type="integer", example=3),
 *     @OA\Property(property="subtotal", type="string", example="137.00"),
 *     @OA\Property(property="delivery_fee", type="string", example="6.00"),
 *     @OA\Property(property="total_amount", type="string", example="143.00"),
 *     @OA\Property(property="payment", type="object", nullable=true,
 *         @OA\Property(property="method", type="string", enum={"cash","card","bank_transfer","wallet"}),
 *         @OA\Property(property="status", type="string", enum={"pending","paid","failed","refunded"}),
 *         @OA\Property(property="amount", type="string", example="143.00"),
 *         @OA\Property(property="paid_at", type="string", format="date-time", nullable=true)),
 *     @OA\Property(property="delegate", type="object", nullable=true,
 *         @OA\Property(property="id", type="integer", example=8),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="mobile_number", type="string")),
 *     @OA\Property(property="can_cancel", type="boolean", description="Pending: POST /cafe/orders/{id}/cancel-request is allowed"),
 *     @OA\Property(property="can_confirm_receipt", type="boolean", description="Delivered: PUT /cafe/orders/{id}/status received is allowed"),
 *     @OA\Property(property="placed_at", type="string", format="date-time")
 * )
 *
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
 *     description="POST receives goods (adds to stock, purchase movement); PUT sets the counted on-hand quantity (adjustment movement).",
 *     @OA\Property(property="warehouse_id", type="integer", description="POST only"),
 *     @OA\Property(property="product_variant_id", type="integer", description="POST only"),
 *     @OA\Property(property="quantity", type="integer"),
 *     @OA\Property(property="note", type="string", nullable=true, maxLength=255),
 *     @OA\Property(property="unit_cost", type="number", nullable=true, description="POST only"),
 *     @OA\Property(property="manufacturing_year", type="integer", nullable=true, description="POST only"),
 *     @OA\Property(property="expiry_date", type="string", format="date", nullable=true, description="POST only"),
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
