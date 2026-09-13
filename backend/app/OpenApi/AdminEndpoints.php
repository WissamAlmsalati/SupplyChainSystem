<?php

namespace App\OpenApi;

/**
 * OpenAPI paths for the dashboard (admin) resources. Kept in one place so the
 * CRUD controllers stay readable; customer and delegate endpoints are
 * annotated on their controllers.
 *
 * @OA\Tag(name="Users", description="User accounts (profiles are created per user type)")
 * @OA\Tag(name="Dashboard", description="Statistics")
 * @OA\Tag(name="Catalog", description="Products, sizes (variants) and images")
 * @OA\Tag(name="Stock", description="Inventory balances, stock ledger and purchase orders")
 * @OA\Tag(name="Payments", description="Payments recorded against orders")
 * @OA\Tag(name="Access Control", description="Roles, permissions and activity log")
 * @OA\Tag(name="Promos", description="App banners")
 * @OA\Tag(name="Carts & Order Lines", description="Read-only views written by the ordering workflows")
 *
 * ---------------- Users ----------------
 * @OA\Get(path="/users", tags={"Users"}, summary="List users", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="user_type", in="query", description="Comma-separated type names, e.g. cafe,delegate", @OA\Schema(type="string")),
 *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
 *     @OA\Response(response=200, description="Paginated users"))
 * @OA\Post(path="/users", tags={"Users"}, summary="Create a user (matching profile row is created automatically)", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AppUserRequest")),
 *     @OA\Response(response=201, description="User created"), @OA\Response(response=422, description="Validation error"))
 * @OA\Get(path="/users/{id}", tags={"Users"}, summary="User with addresses, orders and profile", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="User"))
 * @OA\Put(path="/users/{id}", tags={"Users"}, summary="Update a user", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AppUserRequest")), @OA\Response(response=200, description="User updated"))
 * @OA\Delete(path="/users/{id}", tags={"Users"}, summary="Soft delete a user and revoke tokens", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Post(path="/delegates/{id}/toggle-active", tags={"Delegates"}, summary="Activate/deactivate a delegate", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Delegate with delegate_profile"))
 * @OA\Put(path="/delegates/{id}/location", tags={"Delegates"}, summary="Set a delegate's location (delegate_profiles)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         @OA\Property(property="latitude", type="number"), @OA\Property(property="longitude", type="number"), @OA\Property(property="is_available", type="boolean"))),
 *     @OA\Response(response=200, description="Delegate with delegate_profile"))
 *
 * ---------------- Dashboard ----------------
 * @OA\Get(path="/dashboard", tags={"Dashboard"}, summary="Admin dashboard stats", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Stats"))
 * @OA\Get(path="/dashboard/monthly/{year}/{month}", tags={"Dashboard"}, summary="Stats for one month", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="year", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="month", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Stats"))
 * @OA\Get(path="/cafe/dashboard", tags={"Cafe Mobile"}, summary="Customer dashboard stats", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Stats"))
 *
 * ---------------- Catalog ----------------
 * @OA\Get(path="/products", tags={"Catalog"}, summary="List products (public)",
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")), @OA\Response(response=200, description="Paginated products with image_url"))
 * @OA\Post(path="/products", tags={"Catalog"}, summary="Create a product (optional image becomes the primary product image)", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(ref="#/components/schemas/ProductRequest"))),
 *     @OA\Response(response=201, description="Product created"))
 * @OA\Get(path="/products/{id}", tags={"Catalog"}, summary="Product with images, variants and stock (public)",
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Product"))
 * @OA\Put(path="/products/{id}", tags={"Catalog"}, summary="Update a product", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductRequest")), @OA\Response(response=200, description="Product updated"))
 * @OA\Delete(path="/products/{id}", tags={"Catalog"}, summary="Soft delete a product", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Get(path="/product-variants", tags={"Catalog"}, summary="List sizes", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="product_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="search", in="query", description="Name, SKU, barcode or product name", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Paginated variants"))
 * @OA\Post(path="/product-variants", tags={"Catalog"}, summary="Create a size (SKU generated when empty)", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductVariantRequest")), @OA\Response(response=201, description="Variant created"))
 * @OA\Get(path="/product-variants/{id}", tags={"Catalog"}, summary="Size with stock per warehouse and images", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Variant"))
 * @OA\Put(path="/product-variants/{id}", tags={"Catalog"}, summary="Update a size", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductVariantRequest")), @OA\Response(response=200, description="Variant updated"))
 * @OA\Delete(path="/product-variants/{id}", tags={"Catalog"}, summary="Soft delete a size", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Get(path="/product-images", tags={"Catalog"}, summary="List images", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="product_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="product_variant_id", in="query", @OA\Schema(type="integer")), @OA\Response(response=200, description="Paginated images"))
 * @OA\Post(path="/product-images", tags={"Catalog"}, summary="Add an image; omit product_variant_id for a product-level image", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
 *         @OA\Property(property="product_id", type="integer"), @OA\Property(property="product_variant_id", type="integer"),
 *         @OA\Property(property="image", type="string", format="binary"), @OA\Property(property="url", type="string", description="Instead of a file"),
 *         @OA\Property(property="is_primary", type="boolean"), @OA\Property(property="sort_order", type="integer")))),
 *     @OA\Response(response=201, description="Image created"))
 * @OA\Get(path="/product-images/{id}", tags={"Catalog"}, summary="Image", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Image"))
 * @OA\Put(path="/product-images/{id}", tags={"Catalog"}, summary="Replace file/URL or change is_primary / sort_order", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Image updated"))
 * @OA\Delete(path="/product-images/{id}", tags={"Catalog"}, summary="Delete an image", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * ---------------- Stock ----------------
 * @OA\Get(path="/inventory", tags={"Stock"}, summary="Stock balances per warehouse and size", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="warehouse_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="product_variant_id", in="query", @OA\Schema(type="integer")), @OA\Response(response=200, description="Paginated balances"))
 * @OA\Post(path="/inventory", tags={"Stock"}, summary="Add stock (sums with the balance; writes an adjustment movement)", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/InventoryRequest")), @OA\Response(response=201, description="Balance"))
 * @OA\Get(path="/inventory/{id}", tags={"Stock"}, summary="Balance row", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Balance"))
 * @OA\Put(path="/inventory/{id}", tags={"Stock"}, summary="Set the counted quantity (difference written as an adjustment movement)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="quantity", type="integer"), @OA\Property(property="note", type="string"))),
 *     @OA\Response(response=200, description="Balance"))
 * @OA\Delete(path="/inventory/{id}", tags={"Stock"}, summary="Delete an empty balance row", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=204, description="Deleted"), @OA\Response(response=422, description="Row still has quantity"))
 *
 * @OA\Get(path="/stock-movements", tags={"Stock"}, summary="Stock ledger (read-only)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="warehouse_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="product_variant_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"purchase","sale","return","adjustment"})),
 *     @OA\Parameter(name="reference_type", in="query", description="e.g. App\Models\Order (use with reference_id)", @OA\Schema(type="string")),
 *     @OA\Parameter(name="reference_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Paginated movements"))
 * @OA\Get(path="/stock-movements/{id}", tags={"Stock"}, summary="One movement with its reference", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Movement"))
 *
 * @OA\Get(path="/purchase-orders", tags={"Stock"}, summary="List purchase orders", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="warehouse_id", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"draft","received","cancelled"})),
 *     @OA\Response(response=200, description="Paginated purchase orders with items_count"))
 * @OA\Post(path="/purchase-orders", tags={"Stock"}, summary="Create a draft purchase order", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/PurchaseOrderRequest")), @OA\Response(response=201, description="Draft created"))
 * @OA\Get(path="/purchase-orders/{id}", tags={"Stock"}, summary="Purchase order with items", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Purchase order"))
 * @OA\Put(path="/purchase-orders/{id}", tags={"Stock"}, summary="Edit a draft (items are replaced when sent)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/PurchaseOrderRequest")),
 *     @OA\Response(response=200, description="Updated"), @OA\Response(response=422, description="Not a draft"))
 * @OA\Delete(path="/purchase-orders/{id}", tags={"Stock"}, summary="Delete a draft or cancelled purchase order", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=204, description="Deleted"), @OA\Response(response=422, description="Already received"))
 * @OA\Post(path="/purchase-orders/{id}/receive", tags={"Stock"}, summary="Receive: add all items to warehouse stock (purchase movements)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Received"), @OA\Response(response=422, description="Not a draft or has no items"))
 * @OA\Post(path="/purchase-orders/{id}/cancel", tags={"Stock"}, summary="Cancel a draft", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\Response(response=200, description="Cancelled"), @OA\Response(response=422, description="Not a draft"))
 * @OA\Get(path="/purchase-order-items", tags={"Stock"}, summary="List purchase order items", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Paginated items"))
 * @OA\Post(path="/purchase-order-items", tags={"Stock"}, summary="Add an item to a draft", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(
 *         required={"purchase_order_id","product_variant_id","quantity","unit_cost"},
 *         @OA\Property(property="purchase_order_id", type="integer"), @OA\Property(property="product_variant_id", type="integer"),
 *         @OA\Property(property="quantity", type="integer"), @OA\Property(property="unit_cost", type="number"),
 *         @OA\Property(property="manufacturing_year", type="integer"), @OA\Property(property="expiry_date", type="string", format="date"))),
 *     @OA\Response(response=201, description="Item added"), @OA\Response(response=422, description="Purchase order is not a draft"))
 * @OA\Get(path="/purchase-order-items/{id}", tags={"Stock"}, summary="Purchase order item", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item"))
 * @OA\Put(path="/purchase-order-items/{id}", tags={"Stock"}, summary="Update an item of a draft", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item updated"))
 * @OA\Delete(path="/purchase-order-items/{id}", tags={"Stock"}, summary="Remove an item from a draft", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Post(path="/warehouses/{id}/expand-hex", tags={"Warehouses"}, summary="Create child delivery zones inside the warehouse hex", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="child_resolution", type="integer"), @OA\Property(property="default_price", type="number"))),
 *     @OA\Response(response=201, description="Zones created"))
 *
 *
 * @OA\Get(path="/payments", tags={"Payments"}, summary="List payments", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="List"))
 * @OA\Post(path="/payments", tags={"Payments"}, summary="Create payments", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"order_id","amount","method","status"},
 *         @OA\Property(property="order_id", type="integer"), @OA\Property(property="amount", type="number"),
 *         @OA\Property(property="method", type="string", enum={"cash","card","bank_transfer"}),
 *         @OA\Property(property="status", type="string", enum={"pending","paid","failed","refunded"}),
 *         @OA\Property(property="paid_at", type="string", format="date-time", nullable=true))),
 *     @OA\Response(response=201, description="Created"), @OA\Response(response=422, description="Validation error"))
 * @OA\Get(path="/payments/{id}", tags={"Payments"}, summary="Show payments", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item"))
 * @OA\Put(path="/payments/{id}", tags={"Payments"}, summary="Update payments", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Updated"))
 * @OA\Delete(path="/payments/{id}", tags={"Payments"}, summary="Delete payments", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Get(path="/permissions", tags={"Access Control"}, summary="List permissions", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="List"))
 * @OA\Post(path="/permissions", tags={"Access Control"}, summary="Create permissions", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="code", type="string"))),
 *     @OA\Response(response=201, description="Created"), @OA\Response(response=422, description="Validation error"))
 * @OA\Get(path="/permissions/{id}", tags={"Access Control"}, summary="Show permissions", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item"))
 * @OA\Put(path="/permissions/{id}", tags={"Access Control"}, summary="Update permissions", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Updated"))
 * @OA\Delete(path="/permissions/{id}", tags={"Access Control"}, summary="Delete permissions", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Get(path="/user-types", tags={"Access Control"}, summary="List user types (roles)", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="List"))
 * @OA\Post(path="/user-types", tags={"Access Control"}, summary="Create user types (roles)", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="name", type="string"), @OA\Property(property="permission_ids", type="array", @OA\Items(type="integer")))),
 *     @OA\Response(response=201, description="Created"), @OA\Response(response=422, description="Validation error"))
 * @OA\Get(path="/user-types/{id}", tags={"Access Control"}, summary="Show user types (roles)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item"))
 * @OA\Put(path="/user-types/{id}", tags={"Access Control"}, summary="Update user types (roles)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Updated"))
 * @OA\Delete(path="/user-types/{id}", tags={"Access Control"}, summary="Delete user types (roles)", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Get(path="/promos", tags={"Promos"}, summary="List promo banners", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="List"))
 * @OA\Post(path="/promos", tags={"Promos"}, summary="Create promo banners", security={{"bearerAuth":{}}},
 *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
 *         @OA\Property(property="image", type="string", format="binary"), @OA\Property(property="description", type="string"),
 *         @OA\Property(property="link", type="string"), @OA\Property(property="show_description", type="boolean"), @OA\Property(property="is_active", type="boolean")))),
 *     @OA\Response(response=201, description="Created"), @OA\Response(response=422, description="Validation error"))
 * @OA\Get(path="/promos/{id}", tags={"Promos"}, summary="Show promo banners", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item"))
 * @OA\Put(path="/promos/{id}", tags={"Promos"}, summary="Update promo banners", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Updated"))
 * @OA\Delete(path="/promos/{id}", tags={"Promos"}, summary="Delete promo banners", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=204, description="Deleted"))
 *
 * @OA\Get(path="/activity-logs", tags={"Access Control"}, summary="Activity log", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="action", in="query", @OA\Schema(type="string")), @OA\Parameter(name="entity_type", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")), @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Paginated log"))
 * @OA\Post(path="/logout", tags={"Auth"}, summary="Revoke the current token", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Logged out"))
 * @OA\Get(path="/cafe/premium-features", tags={"Cafe Mobile"}, summary="Feature flags for the app UI", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Features"))
 * @OA\Get(path="/cafe/promos", tags={"Cafe Mobile"}, summary="Active promo banners", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Promos"))
 * ---------------- Read-only workflow tables ----------------
 * @OA\Get(path="/carts", tags={"Carts & Order Lines"}, summary="Customer carts", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"shopping","recurring"})),
 *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")), @OA\Response(response=200, description="Paginated carts"))
 * @OA\Get(path="/carts/{id}", tags={"Carts & Order Lines"}, summary="Cart with items", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Cart"))
 * @OA\Get(path="/cart-items", tags={"Carts & Order Lines"}, summary="Cart items", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Paginated"))
 * @OA\Get(path="/cart-items/{id}", tags={"Carts & Order Lines"}, summary="Cart item", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Item"))
 * @OA\Get(path="/order-items", tags={"Carts & Order Lines"}, summary="Order lines (with product/size snapshot)", security={{"bearerAuth":{}}}, @OA\Response(response=200, description="Paginated"))
 * @OA\Get(path="/order-items/{id}", tags={"Carts & Order Lines"}, summary="Order line", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Line"))
 * @OA\Get(path="/order-status-logs", tags={"Carts & Order Lines"}, summary="Order status history", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="order_id", in="query", @OA\Schema(type="integer")), @OA\Response(response=200, description="Paginated logs"))
 * @OA\Get(path="/order-status-logs/{id}", tags={"Carts & Order Lines"}, summary="Status log entry", security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")), @OA\Response(response=200, description="Log"))
 */
class AdminEndpoints
{
    //
}
