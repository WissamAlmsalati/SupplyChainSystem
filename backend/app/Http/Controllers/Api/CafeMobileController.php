<?php

namespace App\Http\Controllers\Api;

use App\Enums\CartType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Api\AddressRequest;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\FeaturedSection;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PremiumFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\H3Service;
use App\Services\OrderPlacementService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CafeMobileController extends BaseApiController
{
    protected function orderScope()
    {
        return Order::with(['user', 'address', 'deliveryZone'])
            ->where('user_id', auth()->id());
    }

    protected function addressScope()
    {
        return Address::with('deliveryZone')
            ->where('user_id', auth()->id());
    }

    /**
     * @OA\Get(path="/cafe/orders", tags={"Cafe Orders"}, summary="List own orders",
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="address_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Paginated orders"))
     */
    public function orders(Request $request): JsonResponse
    {
        $query = $this->orderScope()->orderByDesc('placed_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('address_id')) {
            $query->where('address_id', $request->integer('address_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('placed_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('placed_at', '<=', $request->input('to'));
        }

        return $this->jsonResponse($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(path="/cafe/addresses/{id}/orders", tags={"Cafe Addresses"}, summary="Orders delivered to one address",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated orders"))
     */
    public function addressOrders(Request $request, int $id): JsonResponse
    {
        $address = $this->addressScope()->findOrFail($id);
        $query = $this->orderScope()
            ->where('address_id', $address->id)
            ->orderByDesc('placed_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->jsonResponse($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(path="/cafe/orders/{id}", tags={"Cafe Orders"}, summary="Own order details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order"))
     */
    public function showOrder(int $id): JsonResponse
    {
        $order = $this->orderScope()
            ->with(['items.productVariant', 'payments', 'statusLogs'])
            ->findOrFail($id);

        return $this->jsonResponse($order);
    }

    /**
     * @OA\Put(path="/cafe/orders/{id}/status", tags={"Cafe Orders"}, summary="Confirm receipt of a delivered order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="status", type="string", enum={"received"}))),
     *     @OA\Response(response=200, description="Order updated"),
     *     @OA\Response(response=422, description="Order is not delivered yet"))
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'string', Rule::in([OrderStatus::Received->value])]]);

        $order = $this->orderScope()->findOrFail($id);

        if ($order->status !== OrderStatus::Delivered) {
            return $this->jsonResponse(['message' => 'لا يمكن تأكيد الاستلام إلا بعد التسليم'], 422);
        }

        $order->update(['status' => OrderStatus::Received]);

        return $this->jsonResponse($order->load(['user', 'address', 'deliveryZone']));
    }

    /**
     * @OA\Post(path="/cafe/orders/{id}/cancel-request", tags={"Cafe Orders"}, summary="Ask the admins to cancel a pending order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cancellation requested"),
     *     @OA\Response(response=422, description="Order is not pending"))
     */
    public function requestCancellation(int $id): JsonResponse
    {
        $order = $this->orderScope()->findOrFail($id);

        if ($order->status !== OrderStatus::Pending) {
            return $this->jsonResponse(['message' => 'لا يمكن طلب الإلغاء إلا للطلبات قيد الانتظار'], 422);
        }

        $order->update(['status' => OrderStatus::CancellationRequested]);

        Notification::notifyAdmins(
            'طلب إلغاء',
            "طلب إلغاء للطلب رقم {$order->order_number}",
            "/orders/{$order->id}",
            'cancellation'
        );

        return $this->jsonResponse([
            'id' => $order->id,
            'status' => $order->status,
            'message' => 'تم إرسال طلب الإلغاء، سيتم مراجعته من الإدارة',
        ]);
    }

    /**
     * @OA\Post(path="/cafe/orders", tags={"Cafe Orders"}, summary="Place an order directly (without the cart)",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeOrderRequest")),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=409, description="Insufficient stock"))
     */
    public function storeOrder(Request $request, OrderPlacementService $placement): JsonResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Wallet->value])],
        ]);

        $address = $this->addressScope()->find($data['address_id']);
        if (! $address) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        // ponytail: prices always come from the variant; client-sent prices are ignored
        $order = $placement->place(auth()->user(), $address, $data['items'], OrderSource::App, null, null, PaymentMethod::from($data['payment_method'] ?? 'cash'));

        return $this->orderCreatedResponse($order);
    }

    /**
     * @OA\Get(path="/cafe/addresses", tags={"Cafe Addresses"}, summary="List own addresses",
     *     @OA\Response(response=200, description="Addresses and the delivery price at the customer's registered location"))
     */
    public function addresses(Request $request): JsonResponse
    {
        $addresses = $this->addressScope()->with('deliveryZone:id,name,delivery_price')->get();

        return $this->jsonResponse(['data' => [
            'addresses' => $addresses,
            'delivery_price' => $this->userZonePrice(),
        ]]);
    }

    // Delivery price for the customer's registered location — used when the
    // customer has no addresses yet.
    // ponytail: delivery zones are drawn on the res-4 map grid, so one cell
    // lookup covers all of them; other resolutions would need one call per res.
    private function userZonePrice(): ?float
    {
        $profile = auth()->user()->customerProfile;

        if ($profile?->latitude === null || $profile?->longitude === null) {
            return null;
        }

        $cell = H3Service::latLngToCell((float) $profile->latitude, (float) $profile->longitude, 4);

        $zone = DeliveryZone::where('is_active', true)
            ->where('hex_id', $cell)
            ->first(['delivery_price']);

        return $zone ? (float) $zone->delivery_price : null;
    }

    /**
     * @OA\Get(path="/cafe/addresses/{id}", tags={"Cafe Addresses"}, summary="Own address details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Address"))
     */
    public function showAddress(int $id): JsonResponse
    {
        return $this->jsonResponse($this->addressScope()->findOrFail($id));
    }

    /**
     * @OA\Post(path="/cafe/addresses", tags={"Cafe Addresses"}, summary="Create an address",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *     @OA\Response(response=201, description="Address created"))
     */
    public function storeAddress(AddressRequest $request): JsonResponse
    {
        if (! PremiumFeature::isActive('cafe_branches')) {
            return $this->jsonResponse(['message' => 'إضافة عناوين غير متاحة — الميزة معطلة'], 403);
        }

        $address = Address::create($request->validated() + ['user_id' => auth()->id()]);

        return $this->jsonResponse([
            'id' => $address->id,
            'name' => $address->name,
            'message' => 'تم إنشاء العنوان بنجاح',
        ], 201);
    }

    /**
     * @OA\Put(path="/cafe/addresses/{id}", tags={"Cafe Addresses"}, summary="Update an address",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *     @OA\Response(response=200, description="Address updated"))
     */
    public function updateAddress(AddressRequest $request, int $id): JsonResponse
    {
        $address = $this->addressScope()->findOrFail($id);
        $address->update($request->validated());

        return $this->jsonResponse($address->load('deliveryZone'));
    }

    /**
     * @OA\Delete(path="/cafe/addresses/{id}", tags={"Cafe Addresses"}, summary="Delete an address",
     *     description="Soft delete; past orders keep their own copy of the delivery address.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Address deleted"))
     */
    public function destroyAddress(int $id): JsonResponse
    {
        $this->addressScope()->findOrFail($id)->delete();

        return $this->jsonResponse(['message' => 'تم حذف العنوان بنجاح']);
    }

    /**
     * @OA\Get(path="/cafe/profile", tags={"Cafe Profile"}, summary="Customer profile",
     *     @OA\Response(response=200, description="User fields merged with the customer profile, plus addresses"))
     */
    public function profile(): JsonResponse
    {
        return $this->jsonResponse([
            'user' => $this->profilePayload(),
            'addresses' => $this->addressScope()->with('deliveryZone:id,name,delivery_price')->get(),
        ]);
    }

    /**
     * @OA\Put(path="/cafe/profile", tags={"Cafe Profile"}, summary="Update customer profile",
     *     @OA\RequestBody(@OA\JsonContent(
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="mobile_number", type="string"),
     *         @OA\Property(property="business_name", type="string", nullable=true),
     *         @OA\Property(property="latitude", type="number", nullable=true),
     *         @OA\Property(property="longitude", type="number", nullable=true))),
     *     @OA\Response(response=200, description="Updated profile"))
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'mobile_number' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('users', 'mobile_number')->ignore($user->id)],
            'business_name' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $user->update(collect($data)->only(['name', 'mobile_number'])->all());
            $user->customerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                collect($data)->only(['business_name', 'latitude', 'longitude'])->all()
            );
        });

        $user->unsetRelation('customerProfile');

        return $this->jsonResponse($this->profilePayload());
    }

    private function profilePayload(): array
    {
        $user = auth()->user()->loadMissing('customerProfile');
        $profile = $user->customerProfile;

        return $user->only(['id', 'name', 'email', 'mobile_number']) + [
            'business_name' => $profile?->business_name,
            'latitude' => $profile?->latitude,
            'longitude' => $profile?->longitude,
        ];
    }

    /**
     * @OA\Get(path="/cafe/delivery-zones", tags={"Cafe Addresses"}, summary="Active delivery zones for the map",
     *     @OA\Response(response=200, description="Zones"))
     */
    public function deliveryZones(Request $request): JsonResponse
    {
        $zones = DeliveryZone::where('is_active', true)
            ->select(['id', 'hex_id', 'name', 'delivery_price', 'latitude', 'longitude'])
            ->get();

        return $this->jsonResponse(['data' => $zones]);
    }

    /**
     * @OA\Get(path="/cafe/categories", tags={"Cafe Products"}, summary="List categories",
     *     @OA\Response(response=200, description="Categories"))
     */
    public function categories(Request $request): JsonResponse
    {
        return $this->jsonResponse(['data' => Category::with('parentCategory')->get()]);
    }

    /**
     * @OA\Get(path="/cafe/products", tags={"Cafe Products"}, summary="List active products (cards)",
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Products"))
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::with([
            'allImages',
            'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
            'variants.images',
        ])->where('is_active', true);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            return $this->jsonResponse(['data' => $this->searchProducts($request, $query)]);
        }

        $favorites = $this->favoriteIds();

        return $this->jsonResponse(['data' => $query->get()->map(fn (Product $product) => self::productCard($product, $favorites->contains($product->id)))]);
    }

    // Product ids the signed-in customer has favorited.
    private function favoriteIds()
    {
        return auth()->user()->favoriteProducts()->pluck('products.id');
    }

    // ponytail: mobile list cards only need name/price/image — full description
    // and variants live in show() and /variants (quick-add uses default_variant_id).
    public static function productCard(Product $product, bool $isFavorite = false): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'image_url' => $product->image_url,
            'min_price' => $product->variants->min('price'),
            'category_id' => $product->category_id,
            'default_variant_id' => $product->variants->first()?->id,
            'is_favorite' => $isFavorite,
        ];
    }

    /**
     * @OA\Get(path="/cafe/featured-sections", tags={"Cafe Products"}, summary="Curated product sections chosen by the business (title + products in one object)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Active sections in display order; each has id, title and products (same card shape as /cafe/products). Sections without available products are omitted.",
     *         @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="title", type="string", example="الأكثر طلباً"),
     *             @OA\Property(property="products", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="image_url", type="string", nullable=true), @OA\Property(property="min_price", type="string"),
     *                 @OA\Property(property="category_id", type="integer"), @OA\Property(property="default_variant_id", type="integer"),
     *                 @OA\Property(property="is_favorite", type="boolean")))
     *         )))))
     */
    public function featuredSections(): JsonResponse
    {
        $sections = FeaturedSection::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['products' => fn ($q) => $q
                ->where('is_active', true)
                ->whereHas('variants', fn ($v) => $v->where('is_active', true))
                ->with(['allImages', 'variants' => fn ($v) => $v->where('is_active', true)->orderBy('id')]),
            ])
            ->get()
            ->filter(fn (FeaturedSection $section) => $section->products->isNotEmpty());
        $favorites = $this->favoriteIds();

        $sections = $sections
            ->map(fn (FeaturedSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'products' => $section->products->map(fn (Product $product) => self::productCard($product, $favorites->contains($product->id)))->values(),
            ])
            ->values();

        return $this->jsonResponse(['data' => $sections]);
    }

    private function searchProducts(Request $request, $query): array
    {
        $search = trim((string) $request->input('search'));
        $results = [];
        $favorites = $this->favoriteIds();

        foreach ($query->get() as $product) {
            $variantHit = false;
            foreach ($product->variants as $variant) {
                if (mb_stripos((string) $variant->name, $search) !== false) {
                    $variantHit = true;
                    $results[] = [
                        'id' => $product->id,
                        'variant_id' => $variant->id,
                        'name' => $product->name . ' — ' . $variant->name,
                        'image_url' => $variant->images->first()?->image_url ?: $product->image_url,
                        'min_price' => $variant->price,
                        'category_id' => $product->category_id,
                        'default_variant_id' => $variant->id,
                        'is_favorite' => $favorites->contains($product->id),
                    ];
                }
            }
            if (! $variantHit && (mb_stripos($product->name, $search) !== false || in_array($search, $product->tags ?? []))) {
                $results[] = self::productCard($product, $favorites->contains($product->id));
            }
        }

        return $results;
    }

    /**
     * @OA\Get(path="/cafe/products/{id}", tags={"Cafe Products"}, summary="Product details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product with its own images"))
     */
    public function showProduct(int $id): JsonResponse
    {
        $product = Product::with(['category', 'allImages'])->where('is_active', true)->findOrFail($id);

        return $this->jsonResponse([
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'description' => $product->description,
            'image_url' => $product->image_url,
            'images' => $product->allImages->whereNull('product_variant_id')->values(),
            'category' => $product->category,
            'is_favorite' => $this->favoriteIds()->contains($product->id),
        ]);
    }

    /**
     * @OA\Get(path="/cafe/products/{id}/variants", tags={"Cafe Products"}, summary="Active sizes of a product",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Variants with images and stock"))
     */
    public function productVariants(int $id, StockService $stock): JsonResponse
    {
        $product = Product::findOrFail($id);
        $variants = $product->variants()->where('is_active', true)->with('images')->orderBy('id')->get();
        $levels = $stock->levels($variants->pluck('id')->all());

        $variants->each(fn (ProductVariant $v) => $v->setAttribute('in_stock', $levels[$v->id]['total'] ?? 0));

        return $this->jsonResponse(['data' => $variants]);
    }

    /**
     * @OA\Get(path="/cafe/cart", tags={"Cafe Cart"}, summary="Current shopping cart (null when none)",
     *     @OA\Response(response=200, description="Cart"))
     */
    public function cart(): JsonResponse
    {
        $cart = $this->shoppingCart();

        return $this->jsonResponse(['data' => $cart ? $this->cartPayload($cart) : null]);
    }

    /**
     * @OA\Get(path="/cafe/cart/check-stock", tags={"Cafe Cart"}, summary="Stock availability for the cart items",
     *     @OA\Response(response=200, description="Per-item availability"),
     *     @OA\Response(response=400, description="Cart is empty"))
     */
    public function checkStock(StockService $stock): JsonResponse
    {
        $cart = $this->shoppingCart();
        if (! $cart || $cart->items->isEmpty()) {
            return $this->jsonResponse(['message' => 'السلة فارغة'], 400);
        }

        $cart->loadMissing('items.productVariant.product');
        $levels = $stock->levels($cart->items->pluck('product_variant_id')->all());

        $items = $cart->items->map(function (CartItem $item) use ($levels) {
            $level = $levels[$item->product_variant_id] ?? ['total' => 0, 'warehouses' => []];

            return [
                'cart_item_id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->productVariant?->label() ?? 'منتج',
                'requested' => $item->quantity,
                'in_stock' => $level['total'],
                'available' => $level['total'] >= $item->quantity,
                'warehouses' => $level['warehouses'],
            ];
        });

        return $this->jsonResponse([
            'data' => $items,
            'all_available' => $items->every(fn ($i) => $i['available']),
        ]);
    }

    /**
     * @OA\Post(path="/cafe/cart/items", tags={"Cafe Cart"}, summary="Add an item to the shopping cart (sets its quantity)",
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="product_variant_id", type="integer"),
     *         @OA\Property(property="quantity", type="integer"))),
     *     @OA\Response(response=201, description="Item saved"))
     */
    public function addCartItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = Cart::firstOrCreate(['user_id' => auth()->id(), 'type' => CartType::Shopping]);

        $item = $cart->items()->updateOrCreate(
            ['product_variant_id' => $data['product_variant_id']],
            ['quantity' => $data['quantity']]
        );

        return $this->jsonResponse([
            'data' => $item->load('productVariant.product'),
            'cart' => $this->cartPayload($cart),
        ], 201);
    }

    /**
     * @OA\Put(path="/cafe/cart/items/{id}", tags={"Cafe Cart"}, summary="Change a cart item quantity",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="quantity", type="integer"))),
     *     @OA\Response(response=200, description="Cart"))
     */
    public function updateCartItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $cart = $this->shoppingCart();
        if (! $cart) {
            return $this->jsonResponse(['message' => 'السلة غير موجودة'], 404);
        }

        $cart->items()->findOrFail($id)->update(['quantity' => $data['quantity']]);

        return $this->jsonResponse($this->cartPayload($cart));
    }

    /**
     * @OA\Delete(path="/cafe/cart/items/{id}", tags={"Cafe Cart"}, summary="Remove a cart item",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cart"))
     */
    public function removeCartItem(int $id): JsonResponse
    {
        $cart = $this->shoppingCart();
        if (! $cart) {
            return $this->jsonResponse(['message' => 'السلة غير موجودة'], 404);
        }

        $cart->items()->findOrFail($id)->delete();

        return $this->jsonResponse($this->cartPayload($cart));
    }

    /**
     * @OA\Delete(path="/cafe/cart", tags={"Cafe Cart"}, summary="Empty the shopping cart",
     *     @OA\Response(response=200, description="Cart emptied"))
     */
    public function clearCart(): JsonResponse
    {
        $this->shoppingCart()?->items()->delete();

        return $this->jsonResponse(['message' => 'تم إفراغ السلة']);
    }

    /**
     * @OA\Post(path="/cafe/cart/checkout", tags={"Cafe Cart"}, summary="Turn the shopping cart into an order",
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"address_id"},
     *         @OA\Property(property="address_id", type="integer"),
     *         @OA\Property(property="payment_method", type="string", enum={"cash","wallet"}, default="cash", description="wallet = pay the full total from the wallet now"))),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=400, description="Cart is empty"),
     *     @OA\Response(response=422, description="Insufficient wallet balance"),
     *     @OA\Response(response=409, description="Insufficient stock"))
     */
    public function checkout(Request $request, OrderPlacementService $placement): JsonResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer'],
            'payment_method' => ['nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Wallet->value])],
        ]);

        $cart = $this->shoppingCart();
        if (! $cart || $cart->items->isEmpty()) {
            return $this->jsonResponse(['message' => 'السلة فارغة'], 400);
        }

        $address = $this->addressScope()->findOrFail($data['address_id']);

        $order = DB::transaction(function () use ($placement, $cart, $address, $data) {
            $order = $placement->place(auth()->user(), $address, $cart->items->toArray(), OrderSource::App, $cart, null, PaymentMethod::from($data['payment_method'] ?? 'cash'));
            $cart->items()->delete();

            return $order;
        });

        return $this->orderCreatedResponse($order);
    }

    /**
     * @OA\Get(path="/cafe/orders/{id}/delegate", tags={"Cafe Orders"}, summary="Live location of the order's delegate",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Delegate location"),
     *     @OA\Response(response=404, description="No delegate assigned"))
     */
    public function orderDelegate(int $id): JsonResponse
    {
        $order = $this->orderScope()->with('delegate.delegateProfile')->findOrFail($id);

        if (! $order->delegate) {
            return $this->jsonResponse(['message' => 'لا يوجد مندوب مخصص لهذا الطلب'], 404);
        }

        $profile = $order->delegate->delegateProfile;

        return $this->jsonResponse([
            'data' => [
                'id' => $order->delegate->id,
                'name' => $order->delegate->name,
                'latitude' => $profile?->latitude,
                'longitude' => $profile?->longitude,
                'is_available' => (bool) $profile?->is_available,
                'location_updated_at' => $profile?->location_updated_at?->toDateTimeString(),
            ],
        ]);
    }

    private function shoppingCart(): ?Cart
    {
        return Cart::shopping()->with('items')->where('user_id', auth()->id())->first();
    }

    private function cartPayload(Cart $cart): Cart
    {
        $cart->load('items.productVariant.product');
        $cart->setAttribute('subtotal', $cart->subtotal());

        return $cart;
    }

    private function orderCreatedResponse(Order $order): JsonResponse
    {
        return $this->jsonResponse([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'delegate_id' => $order->delegate_id,
            'payment_method' => $order->payments()->where('method', PaymentMethod::Wallet->value)->exists() ? 'wallet' : 'cash',
            'wallet_balance' => auth()->user()->wallet()->value('balance'),
            'message' => 'تم إنشاء الطلب بنجاح',
        ], 201);
    }
}
