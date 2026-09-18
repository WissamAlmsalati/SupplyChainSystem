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
use App\Services\ProductSearch;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerMobileController extends BaseApiController
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
     * @OA\Get(path="/customer/orders", tags={"Customer Orders"}, summary="List own orders",
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="address_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *
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

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(path="/customer/addresses/{id}/orders", tags={"Customer Addresses"}, summary="Orders of one address (branch), paginated",
     *     description="Light order cards for one address. Address details are at GET /customer/addresses/{id}. meta.counts gives the number per tab (all / active / completed / cancelled) and per status; it honours from, to and q but not status or group, so tab badges stay stable. Open GET /customer/orders/{id} for the full order.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="group", in="query", description="Tab: active (pending, confirmed, preparing, out_for_delivery, cancellation_requested), completed (delivered, received), cancelled", @OA\Schema(type="string", enum={"active","completed","cancelled"})),
     *     @OA\Parameter(name="status", in="query", description="One or more statuses, comma-separated", @OA\Schema(type="string", example="pending,confirmed")),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="q", in="query", description="Order number search", @OA\Schema(type="string", example="00039")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=50)),
     *
     *     @OA\Response(response=200, description="data + meta",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CustomerOrderCard")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=6),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="counts", type="object",
     *                     @OA\Property(property="all", type="integer", example=6),
     *                     @OA\Property(property="active", type="integer", example=2),
     *                     @OA\Property(property="completed", type="integer", example=3),
     *                     @OA\Property(property="cancelled", type="integer", example=1),
     *                     @OA\Property(property="by_status", type="object", example={"pending": 1, "confirmed": 1, "received": 3, "cancelled": 1})),
     *                 @OA\Property(property="applied", type="object")))),
     *
     *     @OA\Response(response=404, description="Address not found or not yours"))
     */
    public function addressOrders(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'group' => ['nullable', Rule::in(array_keys(OrderStatus::groups()))],
            'status' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $address = $this->addressScope()->findOrFail($id);

        $base = Order::where('user_id', auth()->id())
            ->where('address_id', $address->id)
            ->when($request->filled('from'), fn ($q) => $q->whereDate('placed_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('placed_at', '<=', $request->input('to')))
            ->when($request->filled('q'), fn ($q) => $q->where('order_number', 'like', '%'.$request->input('q').'%'));

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $counts = ['all' => $byStatus->sum()];
        foreach (OrderStatus::groups() as $group => $statuses) {
            $counts[$group] = $byStatus->only($statuses)->sum();
        }
        $counts['by_status'] = (object) $byStatus->all();

        $statuses = $request->filled('status')
            ? array_values(array_intersect(array_map('trim', explode(',', $request->input('status'))), OrderStatus::values()))
            : [];

        $page = (clone $base)
            ->with(['delegate:id,name,mobile_number', 'payments'])
            ->withCount('items')
            ->when($request->filled('group'), fn ($q) => $q->whereIn('status', OrderStatus::groups()[$request->input('group')]))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse([
            'data' => $page->getCollection()->map(fn (Order $order) => $this->orderCard($order))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMorePages(),
                'counts' => $counts,
                'applied' => (object) array_filter([
                    'group' => $request->input('group'),
                    'status' => $statuses ?: null,
                    'from' => $request->input('from'),
                    'to' => $request->input('to'),
                    'q' => $request->input('q'),
                ]),
            ],
        ]);
    }

    /** Light order row for lists; the full order lives at GET /customer/orders/{id}. */
    private function orderCard(Order $order): array
    {
        $payment = $order->payments->sortByDesc('id')->first();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'source' => $order->source?->value,
            'items_count' => (int) $order->items_count,
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'total_amount' => $order->total_amount,
            'payment' => $payment ? [
                'method' => $payment->method?->value,
                'status' => $payment->status?->value,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
            ] : null,
            'delegate' => $order->delegate?->only(['id', 'name', 'mobile_number']),
            'can_cancel' => $order->status === OrderStatus::Pending,
            'can_confirm_receipt' => $order->status === OrderStatus::Delivered,
            'placed_at' => $order->placed_at,
        ];
    }

    /** Address fields plus its delivery zone and price. Orders live at GET /customer/addresses/{id}/orders. */
    private function addressDetails($addresses)
    {
        return $addresses->map(function (Address $address) {
            $zone = $address->deliveryZone;

            return $address->makeHidden(['delivery_zone', 'deliveryZone'])->toArray() + [
                'full_address' => collect([$address->street, $address->city])->filter()->implode('، '),
                'delivery_zone' => $zone?->only(['id', 'name', 'delivery_price']),
                'delivery_price' => $zone ? (float) $zone->delivery_price : null,
            ];
        })->values();
    }

    /**
     * @OA\Get(path="/customer/orders/{id}", tags={"Customer Orders"}, summary="Own order details",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     * @OA\Patch(path="/customer/orders/{id}/status", tags={"Customer Orders"}, summary="Confirm receipt of a delivered order",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="status", type="string", enum={"received"}))),
     *
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
     * @OA\Post(path="/customer/orders/{id}/cancel-request", tags={"Customer Orders"}, summary="Ask the admins to cancel a pending order",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     * @OA\Post(path="/customer/orders", tags={"Customer Orders"}, summary="Place an order directly (without the cart)",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CustomerOrderRequest")),
     *
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
     * @OA\Get(path="/customer/addresses", tags={"Customer Addresses"}, summary="List own addresses (branches)",
     *     description="Address details with delivery zone and price. Orders of an address are a separate call: GET /customer/addresses/{id}/orders. data.delivery_price is the price at the customer's registered location (used when there are no addresses yet).",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Addresses",
     *
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *
     *             @OA\Property(property="addresses", type="array", @OA\Items(ref="#/components/schemas/CustomerAddressDetail")),
     *             @OA\Property(property="delivery_price", type="number", nullable=true, example=6)))))
     */
    public function addresses(Request $request): JsonResponse
    {
        $addresses = $this->addressDetails(
            $this->addressScope()->orderBy('id')->get()
        );

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
     * @OA\Get(path="/customer/addresses/{id}", tags={"Customer Addresses"}, summary="Own address details",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Address with delivery zone and price", @OA\JsonContent(ref="#/components/schemas/CustomerAddressDetail")),
     *     @OA\Response(response=404, description="Address not found or not yours"))
     */
    public function showAddress(int $id): JsonResponse
    {
        return $this->jsonResponse($this->addressDetails(collect([$this->addressScope()->findOrFail($id)]))->first());
    }

    /**
     * @OA\Post(path="/customer/addresses", tags={"Customer Addresses"}, summary="Create an address",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *
     *     @OA\Response(response=201, description="Address created"))
     */
    public function storeAddress(AddressRequest $request): JsonResponse
    {
        if (! PremiumFeature::isActive('customer_branches')) {
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
     * @OA\Patch(path="/customer/addresses/{id}", tags={"Customer Addresses"}, summary="Update an address",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *
     *     @OA\Response(response=200, description="Address updated"))
     */
    public function updateAddress(AddressRequest $request, int $id): JsonResponse
    {
        $address = $this->addressScope()->findOrFail($id);
        $address->update($request->validated());

        return $this->jsonResponse($address->load('deliveryZone'));
    }

    /**
     * @OA\Delete(path="/customer/addresses/{id}", tags={"Customer Addresses"}, summary="Delete an address",
     *     description="Soft delete; past orders keep their own copy of the delivery address.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Address deleted"))
     */
    public function destroyAddress(int $id): JsonResponse
    {
        $this->addressScope()->findOrFail($id)->delete();

        return $this->jsonResponse(['message' => 'تم حذف العنوان بنجاح']);
    }

    /**
     * @OA\Get(path="/customer/profile", tags={"Customer Profile"}, summary="Customer profile",
     *
     *     @OA\Response(response=200, description="User fields merged with the customer profile, plus addresses. user.has_addresses is true once the customer has at least one address (branch); user.addresses_count gives the number.",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=3),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="mobile_number", type="string", example="0910000001"),
     *                 @OA\Property(property="business_name", type="string", nullable=true),
     *                 @OA\Property(property="has_addresses", type="boolean", example=true),
     *                 @OA\Property(property="addresses_count", type="integer", example=2)),
     *             @OA\Property(property="addresses", type="array", @OA\Items(type="object")))))
     */
    public function profile(): JsonResponse
    {
        return $this->jsonResponse([
            'user' => $this->profilePayload(),
            'addresses' => $this->addressScope()->with('deliveryZone:id,name,delivery_price')->get(),
        ]);
    }

    /**
     * @OA\Patch(path="/customer/profile", tags={"Customer Profile"}, summary="Update customer profile",
     *
     *     @OA\RequestBody(@OA\JsonContent(
     *
     *         @OA\Property(property="name", type="string"),
     *         @OA\Property(property="mobile_number", type="string"),
     *         @OA\Property(property="business_name", type="string", nullable=true),
     *         @OA\Property(property="latitude", type="number", nullable=true),
     *         @OA\Property(property="longitude", type="number", nullable=true))),
     *
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
        $addressesCount = $user->addresses()->count();

        return $user->only(['id', 'name', 'email', 'mobile_number']) + [
            'business_name' => $profile?->business_name,
            'latitude' => $profile?->latitude,
            'longitude' => $profile?->longitude,
            'has_addresses' => $addressesCount > 0,
            'addresses_count' => $addressesCount,
        ];
    }

    /**
     * @OA\Get(path="/customer/delivery-zones", tags={"Customer Addresses"}, summary="Active delivery zones for the map",
     *
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
     * @OA\Get(path="/customer/categories", tags={"Customer Products"}, summary="List categories",
     *     description="Each category carries image_url (its own picture, or the shared default) and image_type (uploaded / placeholder).",
     *
     *     @OA\Response(response=200, description="Categories"))
     */
    public function categories(Request $request): JsonResponse
    {
        return $this->jsonResponse(['data' => Category::with('parentCategory')->get()]);
    }

    /**
     * @OA\Get(path="/customer/products", tags={"Customer Products"}, summary="Search and filter products (paginated)",
     *     description="Text search covers product name, brand, description, tags, category and size name / SKU / barcode. Filters combine with AND; list filters accept comma-separated values. Use GET /customer/products/filters for the available options and counts.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="q", in="query", description="Search text (alias: search)", @OA\Schema(type="string", example="قهوة")),
     *     @OA\Parameter(name="category_id", in="query", description="One or more category ids, e.g. 1,4 (sub-categories included)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="brand", in="query", description="One or more brands, comma-separated", @OA\Schema(type="string")),
     *     @OA\Parameter(name="min_price", in="query", description="Products with at least one size at or above this price", @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price", in="query", description="Products with at least one size at or below this price", @OA\Schema(type="number")),
     *     @OA\Parameter(name="in_stock", in="query", description="Only products with stock", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="favorites", in="query", description="Only my favorites", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort", in="query", description="Default: relevance when q is given, otherwise newest", @OA\Schema(type="string", enum={"relevance","newest","price_asc","price_desc","name_asc","popular"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
     *
     *     @OA\Response(response=200, description="data: product cards; meta: pagination + applied filters",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="brand", type="string", nullable=true), @OA\Property(property="image_url", type="string", nullable=true),
     *                 @OA\Property(property="min_price", type="string"), @OA\Property(property="max_price", type="string"),
     *                 @OA\Property(property="in_stock", type="boolean"), @OA\Property(property="category_id", type="integer"),
     *                 @OA\Property(property="default_variant_id", type="integer", description="The matched size when the query matched a size/SKU/barcode"),
     *                 @OA\Property(property="matched_variant", type="object", nullable=true, @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="price", type="string")),
     *                 @OA\Property(property="is_favorite", type="boolean"))),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"), @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="applied", type="object")))))
     */
    public function products(Request $request): JsonResponse
    {
        $search = new ProductSearch($request, auth()->id());
        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $page = $search->results()->paginate($perPage);
        $favorites = $this->favoriteIds();

        $cards = $page->getCollection()->map(function (Product $product) use ($search, $favorites) {
            $card = self::productCard($product, $favorites->contains($product->id));
            if ($variant = $search->matchedVariant($product)) {
                $card['default_variant_id'] = $variant->id;
                $card['matched_variant'] = ['id' => $variant->id, 'name' => $variant->name, 'price' => $variant->price];
            }

            return $card;
        });

        return $this->jsonResponse([
            'data' => $cards,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMorePages(),
                'applied' => $search->applied(),
            ],
        ]);
    }

    /**
     * @OA\Get(path="/customer/products/filters", tags={"Customer Products"}, summary="Filter options with counts for the current search",
     *     description="Accepts the same parameters as GET /customer/products. Each facet is counted as if its own filter were not applied, so users can switch between options.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="brand", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="min_price", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="max_price", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="in_stock", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="favorites", in="query", @OA\Schema(type="boolean")),
     *
     *     @OA\Response(response=200, description="Facets",
     *
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="in_stock_count", type="integer"),
     *             @OA\Property(property="categories", type="array", @OA\Items(@OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"), @OA\Property(property="parent_category_id", type="integer", nullable=true), @OA\Property(property="count", type="integer"))),
     *             @OA\Property(property="brands", type="array", @OA\Items(@OA\Property(property="name", type="string"), @OA\Property(property="count", type="integer"))),
     *             @OA\Property(property="price", type="object", @OA\Property(property="min", type="number"), @OA\Property(property="max", type="number")),
     *             @OA\Property(property="sort_options", type="array", @OA\Items(@OA\Property(property="value", type="string"), @OA\Property(property="label", type="string"))),
     *             @OA\Property(property="applied", type="object")))))
     */
    public function productFilters(Request $request): JsonResponse
    {
        return $this->jsonResponse(['data' => (new ProductSearch($request, auth()->id()))->facets()]);
    }

    // ponytail: mobile list cards only need name/price/image — full description
    // and variants live in show() and /variants (quick-add uses default_variant_id).
    public static function productCard(Product $product, bool $isFavorite = false): array
    {
        $stock = $product->getAttribute('stock_quantity');
        if ($stock === null) {
            $stock = $product->variants->sum(fn ($v) => $v->relationLoaded('inventories') ? $v->inventories->sum('quantity') : $v->inventories()->sum('quantity'));
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'image_url' => $product->image_url,
            'image_type' => $product->image_type,
            'min_price' => $product->variants->min('price'),
            'max_price' => $product->variants->max('price'),
            'in_stock' => (int) $stock > 0,
            'category_id' => $product->category_id,
            'default_variant_id' => $product->variants->first()?->id,
            'is_favorite' => $isFavorite,
        ];
    }

    // Product ids the signed-in customer has favorited.
    private function favoriteIds()
    {
        return auth()->user()->favoriteProducts()->pluck('products.id');
    }

    /**
     * @OA\Get(path="/customer/featured-sections", tags={"Customer Products"}, summary="Home-screen product sections (paginated): title + first products in one object",
     *     description="Each section shows up to its products_limit products. Sections are either hand-picked by the admin (source=manual, admin order) or rule-based (source=filter, e.g. sort=popular for best sellers or price_asc for cheapest). Sections without available products are left out of the page.
     *
     * **View all:** when `has_more` is `true`, show a «عرض الكل (products_total)» button that opens `GET /customer/featured-sections/{id}/products` (see the guide at the top of Customer Products).",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Sections per page", @OA\Schema(type="integer", default=10, maximum=50)),
     *     @OA\Response(response=200, description="Sections",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string", example="الأكثر مبيعاً"),
     *                 @OA\Property(property="source", type="string", enum={"manual","filter"}),
     *                 @OA\Property(property="sort", type="string", nullable=true, enum={"popular","price_asc","price_desc","newest","name_asc"}),
     *                 @OA\Property(property="products_total", type="integer"),
     *                 @OA\Property(property="has_more", type="boolean"),
     *                 @OA\Property(property="products", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer"), @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="brand", type="string", nullable=true), @OA\Property(property="image_url", type="string", nullable=true),
     *                     @OA\Property(property="min_price", type="string"), @OA\Property(property="max_price", type="string"),
     *                     @OA\Property(property="in_stock", type="boolean"), @OA\Property(property="category_id", type="integer"),
     *                     @OA\Property(property="default_variant_id", type="integer"), @OA\Property(property="is_favorite", type="boolean"))))),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"), @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"), @OA\Property(property="last_page", type="integer")))))
     */
    public function featuredSections(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, $request->integer('per_page', 10)));
        $page = FeaturedSection::where('is_active', true)->orderBy('sort_order')->orderBy('id')->paginate($perPage);
        $favorites = $this->favoriteIds();

        $sections = $page->getCollection()
            ->map(function (FeaturedSection $section) use ($favorites) {
                $query = $section->productsQuery(auth()->id());
                $total = (clone $query)->reorder()->count();

                return [
                    'id' => $section->id,
                    'title' => $section->title,
                    'source' => $section->source->value,
                    'sort' => $section->source->value === 'filter' ? $section->sort : null,
                    'products_total' => $total,
                    'has_more' => $total > $section->products_limit,
                    'products' => $query->limit($section->products_limit)->get()
                        ->map(fn (Product $product) => self::productCard($product, $favorites->contains($product->id)))->values(),
                ];
            })
            ->filter(fn (array $section) => $section['products_total'] > 0)
            ->values();

        return $this->jsonResponse([
            'data' => $sections,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    /**
     * @OA\Get(path="/customer/featured-sections/{id}/products", tags={"Customer Products"}, summary="All products of a section (paginated) for 'view all'",
     *     description="Screen opened from a section's «عرض الكل» button.
     *
     * 1. Request `page=1` and show `section.title` and `meta.total`.
     * 2. On scroll end, while `meta.current_page < meta.last_page`, request the next `page` and append the results.
     *
     * Page 1 starts from the beginning of the section (it repeats the products already shown on the home screen, in the same order) — render the response as-is. The order is fixed by the section type. Returns 404 when the section is hidden or deleted.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20, maximum=100)),
     *     @OA\Response(response=200, description="section {id,title,source,sort}, data: product cards in the section's order, meta: pagination"),
     *     @OA\Response(response=404, description="Section not found or hidden"))
     */
    public function featuredSectionProducts(Request $request, int $id): JsonResponse
    {
        $section = FeaturedSection::where('is_active', true)->findOrFail($id);
        $page = $section->productsQuery(auth()->id())->paginate(max(1, min(100, $request->integer('per_page', 20))));
        $favorites = $this->favoriteIds();

        return $this->jsonResponse([
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'source' => $section->source->value,
                'sort' => $section->source->value === 'filter' ? $section->sort : null,
            ],
            'data' => $page->getCollection()->map(fn (Product $product) => self::productCard($product, $favorites->contains($product->id)))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    /**
     * @OA\Get(path="/customer/products/{id}", tags={"Customer Products"}, summary="Product details",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     * @OA\Get(path="/customer/products/{id}/variants", tags={"Customer Products"}, summary="Active sizes of a product",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     * @OA\Get(path="/customer/cart", tags={"Customer Cart"}, summary="Current shopping cart (null when none)",
     *
     *     @OA\Response(response=200, description="Cart"))
     */
    public function cart(): JsonResponse
    {
        $cart = $this->shoppingCart();

        return $this->jsonResponse(['data' => $cart ? $this->cartPayload($cart) : null]);
    }

    /**
     * @OA\Get(path="/customer/cart/check-stock", tags={"Customer Cart"}, summary="Stock availability for the cart items",
     *
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
     * @OA\Post(path="/customer/cart/items", tags={"Customer Cart"}, summary="Add an item to the shopping cart (sets its quantity)",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="product_variant_id", type="integer"),
     *         @OA\Property(property="quantity", type="integer"))),
     *
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

        $item->load('productVariant.product');
        self::markFavorites([$item]);

        return $this->jsonResponse([
            'data' => $item,
            'cart' => $this->cartPayload($cart),
        ], 201);
    }

    /**
     * @OA\Patch(path="/customer/cart/items/{id}", tags={"Customer Cart"}, summary="Change a cart item quantity",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="quantity", type="integer"))),
     *
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
     * @OA\Delete(path="/customer/cart/items/{id}", tags={"Customer Cart"}, summary="Remove a cart item",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     * @OA\Delete(path="/customer/cart", tags={"Customer Cart"}, summary="Empty the shopping cart",
     *
     *     @OA\Response(response=200, description="Cart emptied"))
     */
    public function clearCart(): JsonResponse
    {
        $this->shoppingCart()?->items()->delete();

        return $this->jsonResponse(['message' => 'تم إفراغ السلة']);
    }

    /**
     * @OA\Post(path="/customer/cart/checkout", tags={"Customer Cart"}, summary="Turn the shopping cart into an order",
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"address_id"},
     *
     *         @OA\Property(property="address_id", type="integer"),
     *         @OA\Property(property="payment_method", type="string", enum={"cash","wallet"}, default="cash", description="wallet = pay the full total from the wallet now"))),
     *
     *     @OA\Response(response=201, description="The cart became an order and was emptied. Stock is already deducted.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Created",
     *             example={"success": true, "message": "تم إنشاء الطلب بنجاح", "data": {"id": 59, "order_number": "ORD-2026-09-18-14-003", "status": "pending", "total_amount": "143.00"}})),
     *
     *     @OA\Response(response=400, description="Nothing in the cart to order.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Error", example={"success": false, "message": "السلة فارغة"})),
     *
     *     @OA\Response(response=422, description="Refused: not enough stock for a line, not enough balance for a wallet payment, or the address is not yours.",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError",
     *             example={"success": false, "message": "البيانات المدخلة غير صحيحة", "errors": {"items": {"الكمية المطلوبة غير متوفرة في المخزون"}}})),
     *
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
     * @OA\Get(path="/customer/orders/{id}/delegate", tags={"Customer Orders"}, summary="Live location of the order's delegate",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Delegate location"),
     *     @OA\Response(response=404, description="No delegate assigned"))
     */
    /**
     * @OA\Get(path="/customer/orders/{id}/invoice", tags={"Customer Orders"}, summary="Download my order's invoice (PDF)",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="PDF download"))
     */
    public function orderInvoice(int $id)
    {
        return app(ReportController::class)->invoiceFor($this->orderScope()->findOrFail($id));
    }

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
        self::markFavorites($cart->items);

        return $cart;
    }

    /**
     * Sets is_favorite on the product of each cart item so the app can draw the heart.
     *
     * @param  iterable<CartItem>  $items
     */
    public static function markFavorites(iterable $items): void
    {
        $favorites = auth()->user()?->favoriteProducts()->pluck('products.id') ?? collect();

        foreach ($items as $item) {
            $product = $item->productVariant?->product;
            $product?->setAttribute('is_favorite', $favorites->contains($product->id));
        }
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
