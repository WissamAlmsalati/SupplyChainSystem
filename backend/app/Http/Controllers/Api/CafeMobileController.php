<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CafeBranchRequest;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PremiumFeature;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DelegateAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(name="Cafe Mobile Orders", description="Cafe mobile app order management")
 * @OA\Tag(name="Cafe Mobile Branches", description="Cafe mobile app branch management")
 * @OA\Tag(name="Cafe Mobile Profile", description="Cafe mobile app profile")
 * @OA\Tag(name="Cafe Mobile Catalog", description="Cafe mobile app product catalog")
 */
class CafeMobileController extends BaseApiController
{
    protected function cafeId(): ?int
    {
        return auth()->user()?->cafe_id;
    }

    protected function isCafeUser(): bool
    {
        return auth()->user()?->userType?->name === 'cafe';
    }

    protected function orderScope()
    {
        return Order::with(['user', 'branch', 'deliveryZone'])
            ->whereHas('branch', fn ($q) => $q->where('cafe_id', $this->cafeId()));
    }

    protected function branchScope()
    {
        return CafeBranch::with(['cafe', 'deliveryZone'])
            ->where('cafe_id', $this->cafeId());
    }

    /**
     * @OA\Get(
     *     path="/cafe/orders",
     *     tags={"Cafe Mobile Orders"},
     *     summary="List cafe orders",
     *     @OA\Response(response=200, description="Paginated list of cafe orders")
     * )
     */
    public function orders(Request $request): JsonResponse
    {
        $query = $this->orderScope()->orderByDesc('order_date');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->input('to'));
        }

        return $this->jsonResponse($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(
     *     path="/cafe/branches/{id}/orders",
     *     tags={"Cafe Mobile Branches"},
     *     summary="List orders for a specific branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of branch orders"),
     *     @OA\Response(response=404, description="Branch not found")
     * )
     */
    public function branchOrders(Request $request, int $id): JsonResponse
    {
        $branch = $this->branchScope()->findOrFail($id);
        $query = $this->orderScope()
            ->where('branch_id', $branch->id)
            ->orderByDesc('order_date');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->jsonResponse($query->paginate($request->integer('per_page', 15)));
    }

    /**
     * @OA\Get(
     *     path="/cafe/orders/{id}",
     *     tags={"Cafe Mobile Orders"},
     *     summary="Get a cafe order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Order details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function showOrder(int $id): JsonResponse
    {
        $order = $this->orderScope()->with(['user', 'branch', 'deliveryZone', 'items.productVariant', 'payments', 'statusLogs'])->findOrFail($id);
        return $this->jsonResponse($order);
    }

    /**
     * @OA\Put(
     *     path="/cafe/orders/{id}/status",
     *     tags={"Cafe Mobile Orders"},
     *     summary="Confirm order receipt (only after the delegate marks it delivered)",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="status", type="string", enum={"received"}))),
     *     @OA\Response(response=200, description="Receipt confirmed"),
     *     @OA\Response(response=422, description="Invalid status or order not delivered yet")
     * )
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'string', 'in:received']]);

        $order = $this->orderScope()->findOrFail($id);

        if ($order->status !== 'delivered') {
            return $this->jsonResponse(['message' => 'لا يمكن تأكيد الاستلام إلا بعد التسليم'], 422);
        }

        $order->update(['status' => 'received']);
        $order->statusLogs()->create([
            'status' => 'received',
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone']));
    }

    /**
     * @OA\Post(
     *     path="/cafe/orders/{id}/cancel-request",
     *     tags={"Cafe Mobile Orders"},
     *     summary="Request order cancellation (reviewed by admin)",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cancellation requested"),
     *     @OA\Response(response=422, description="Order cannot be cancelled in its current state")
     * )
     */
    public function requestCancellation(int $id): JsonResponse
    {
        $order = $this->orderScope()->findOrFail($id);

        if ($order->status !== 'pending') {
            return $this->jsonResponse(['message' => 'لا يمكن طلب الإلغاء إلا للطلبات قيد الانتظار'], 422);
        }

        $order->update(['status' => 'cancellation_requested']);
        $order->statusLogs()->create([
            'status' => 'cancellation_requested',
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

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
     * @OA\Post(
     *     path="/cafe/orders",
     *     tags={"Cafe Mobile Orders"},
     *     summary="Create an order for the cafe",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeOrderRequest")),
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:cafe_branch,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variant,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branch = CafeBranch::with('deliveryZone')->find($data['branch_id']);
        if (! $branch || $branch->cafe_id !== $this->cafeId()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        // ponytail: price is always taken server-side from the variant; client-sent prices are ignored
        $variants = ProductVariant::whereIn('id', collect($data['items'])->pluck('product_variant_id'))
            ->get()
            ->keyBy('id');

        $items = collect($data['items'])->map(fn ($item) => [
            'product_variant_id' => $item['product_variant_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $variants[$item['product_variant_id']]->price,
        ])->all();

        $subtotal = collect($items)->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
        $deliveryFee = $branch->deliveryZone?->delivery_price ?? 0;

        $orderData = [
            'user_id' => auth()->id(),
            'branch_id' => $branch->id,
            'delegate_id' => null,
            'delivery_zone_id' => $branch->delivery_zone_id,
            'delivery_fee' => $deliveryFee,
            'order_date' => now(),
            'status' => 'pending',
            'source' => 'cafe_app',
            'total_amount' => $subtotal + $deliveryFee,
        ];

        $order = DB::transaction(function () use ($orderData, $items) {
            $orderData['order_number'] = Order::generateOrderNumber();
            $order = Order::create($orderData);
            $order->items()->createMany($items);
            return $order;
        });

        $assigned = app(DelegateAssignmentService::class)->assignNearest($order);

        Notification::notifyAdmins(
            'طلب جديد من مقهى',
            "تم إنشاء طلب جديد برقم {$order->order_number}",
            "/orders/{$order->id}",
            'order'
        );

        return $this->jsonResponse([
            'id' => $order->id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'delegate_id' => $order->delegate_id,
            'message' => 'تم إنشاء الطلب بنجاح',
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/cafe/branches",
     *     tags={"Cafe Mobile Branches"},
     *     summary="List cafe branches",
     *     @OA\Response(response=200, description="Paginated list of cafe branches")
     * )
     */
    public function branches(Request $request): JsonResponse
    {
        $branches = $this->branchScope()->get();
        return $this->jsonResponse(['data' => $branches]);
    }

    /**
     * @OA\Get(
     *     path="/cafe/branches/{id}",
     *     tags={"Cafe Mobile Branches"},
     *     summary="Get a cafe branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Branch details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function showBranch(int $id): JsonResponse
    {
        $branch = $this->branchScope()->with(['cafe', 'deliveryZone'])->findOrFail($id);
        return $this->jsonResponse($branch);
    }

    /**
     * @OA\Post(
     *     path="/cafe/branches",
     *     tags={"Cafe Mobile Branches"},
     *     summary="Create a branch for the cafe",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeBranchRequest")),
     *     @OA\Response(response=201, description="Branch created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeBranch(CafeBranchRequest $request): JsonResponse
    {
        if (!PremiumFeature::isActive('cafe_branches')) {
            return $this->jsonResponse(['message' => 'إضافة فروع غير متاحة — الميزة معطلة'], 403);
        }

        $data = $request->validated();
        $data['cafe_id'] = $this->cafeId();
        $branch = CafeBranch::create($data);
        return $this->jsonResponse([
            'id' => $branch->id,
            'name' => $branch->name,
            'message' => 'تم إنشاء الفرع بنجاح',
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/cafe/branches/{id}",
     *     tags={"Cafe Mobile Branches"},
     *     summary="Update a cafe branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/CafeBranchRequest")),
     *     @OA\Response(response=200, description="Branch updated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function updateBranch(CafeBranchRequest $request, int $id): JsonResponse
    {
        $branch = $this->branchScope()->findOrFail($id);
        $data = $request->validated();
        $data['cafe_id'] = $this->cafeId();
        $branch->update($data);
        return $this->jsonResponse($branch->load(['cafe', 'deliveryZone']));
    }

    /**
     * @OA\Delete(
     *     path="/cafe/branches/{id}",
     *     tags={"Cafe Mobile Branches"},
     *     summary="Delete a cafe branch",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Branch deleted"),
     *     @OA\Response(response=409, description="Branch has orders")
     * )
     */
    public function destroyBranch(int $id): JsonResponse
    {
        $branch = $this->branchScope()->findOrFail($id);

        if ($branch->orders()->exists()) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف فرع لديه طلبات'], 409);
        }

        $branch->delete();

        return $this->jsonResponse(['message' => 'تم حذف الفرع بنجاح']);
    }

    /**
     * @OA\Get(
     *     path="/cafe/profile",
     *     tags={"Cafe Mobile Profile"},
     *     summary="Get cafe profile (first call after login: check has_cafe)",
     *     description="Returns has_cafe=false with cafe=null when the user has not added a cafe yet. Otherwise returns the cafe with its branches and is_active approval state.",
     *     @OA\Response(response=200, description="Cafe profile")
     * )
     */
    public function profile(): JsonResponse
    {
        $cafe = $this->cafeId() ? Cafe::with('branches')->find($this->cafeId()) : null;

        return $this->jsonResponse([
            'has_cafe' => ! is_null($cafe),
            'cafe' => $cafe,
            'user' => auth()->user()?->only(['id', 'name', 'email', 'mobile_number']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/cafe/profile",
     *     tags={"Cafe Mobile Profile"},
     *     summary="Add the cafe for the logged-in user (pending admin approval)",
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *         required={"name"},
     *         @OA\Property(property="name", type="string", maxLength=150),
     *         @OA\Property(property="contact_info", type="string", maxLength=200, nullable=true, description="Defaults to the user's phone number"),
     *         @OA\Property(property="logo", type="string", format="binary", nullable=true)
     *     ))),
     *     @OA\Response(response=201, description="Cafe created"),
     *     @OA\Response(response=409, description="User already has a cafe"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeCafe(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $this->isCafeUser()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        if ($user->cafe_id) {
            return $this->jsonResponse(['message' => 'لديك مقهى مسجل بالفعل'], 409);
        }

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'contact_info' => 'nullable|string|max:200',
            'logo' => 'nullable|image|max:2048',
        ]);

        $autoApprove = PremiumFeature::isActive('cafe_auto_approve');

        $cafe = Cafe::create([
            'name' => $data['name'],
            'contact_info' => $data['contact_info'] ?? $user->mobile_number,
            'image' => $request->file('logo')?->store('cafes', 'public'),
            'is_active' => $autoApprove,
        ]);

        $user->syncCafeUser(['cafe_id' => $cafe->id]);

        if ($autoApprove) {
            Notification::notifyAdmins(
                'مقهى جديد مفعل تلقائياً',
                "تم تسجيل وتفعيل مقهى جديد: {$cafe->name}",
                '/cafes',
                'cafe_registration'
            );
        } else {
            Notification::notifyAdmins(
                'طلب تسجيل مقهى جديد',
                "طلب مقهى جديد ينتظر الموافقة: {$cafe->name}",
                '/cafe-registrations/pending',
                'cafe_registration'
            );
        }

        return $this->jsonResponse([
            'message' => $autoApprove
                ? 'تم تسجيل مقهاك وتفعيله'
                : 'تم إرسال طلب التسجيل بنجاح، سيتم التواصل معك بعد الموافقة',
            'cafe' => $cafe->only(['id', 'name', 'contact_info', 'image_url', 'is_active']),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/cafe/profile",
     *     tags={"Cafe Mobile Profile"},
     *     summary="Update cafe profile",
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="name", type="string"), @OA\Property(property="contact_info", type="string"))),
     *     @OA\Response(response=200, description="Profile updated")
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'contact_info' => 'nullable|string|max:200',
        ]);

        $cafe = Cafe::findOrFail($this->cafeId());
        $cafe->update($data);
        return $this->jsonResponse($cafe);
    }

    /**
     * @OA\Get(
     *     path="/cafe/delivery-zones",
     *     tags={"Cafe Mobile Branches"},
     *     summary="List active delivery zones for map branch placement",
     *     @OA\Response(response=200, description="List of delivery zones")
     * )
     */
    public function deliveryZones(Request $request): JsonResponse
    {
        $zones = DeliveryZone::where('is_active', true)
            ->select(['id', 'hex_id', 'name', 'delivery_price', 'latitude', 'longitude'])
            ->get();

        return $this->jsonResponse(['data' => $zones]);
    }

    /**
     * @OA\Get(
     *     path="/cafe/categories",
     *     tags={"Cafe Mobile Catalog"},
     *     summary="List product categories",
     *     @OA\Response(response=200, description="List of categories")
     * )
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = Category::with('parentCategory')->get();
        return $this->jsonResponse(['data' => $categories]);
    }

    /**
     * @OA\Get(
     *     path="/cafe/products",
     *     tags={"Cafe Mobile Catalog"},
     *     summary="List products",
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated list of products")
     * )
     */
    public function products(Request $request): JsonResponse
    {
        $query = Product::with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'), 'variants.images']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('search')) {
            return $this->jsonResponse(['data' => $this->searchProducts($request, $query)]);
        }

        // ponytail: mobile list cards only need name/price/image — full description
        // and variants live in show() and /variants (quick-add uses default_variant_id).
        $products = $query->get()->map(function (Product $product) {
            $primaryImage = $product->variants
                ->flatMap(fn ($v) => $v->images)
                ->first(fn ($img) => $img->is_primary);

            if (! $primaryImage) {
                $primaryImage = $product->variants
                    ->flatMap(fn ($v) => $v->images)
                    ->first();
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url ?: $primaryImage?->image_url,
                'min_price' => $product->variants->min(fn ($v) => $v->sell_price ?? $v->price),
                'category_id' => $product->category_id,
                'default_variant_id' => $product->variants->first()?->id,
            ];
        });

        return $this->jsonResponse(['data' => $products]);
    }

    /**
     * Search returns product-shaped entries at variant level: each matched
     * variant becomes "Product — variant" with its own price/image, so the
     * mobile app renders results exactly like normal product cards.
     */
    private function searchProducts(Request $request, $query): array
    {
        $search = trim((string) $request->input('search'));
        $results = [];

        foreach ($query->get() as $product) {
            $variantHit = false;
            foreach ($product->variants as $variant) {
                if (mb_stripos((string) $variant->attribute_value, $search) !== false) {
                    $variantHit = true;
                    $results[] = [
                        'id' => $product->id,
                        'variant_id' => $variant->id,
                        'name' => $product->name . ' — ' . $variant->attribute_value,
                        'image_url' => $variant->images->first()?->image_url ?: $product->image_url,
                        'min_price' => $variant->sell_price ?? $variant->price,
                        'category_id' => $product->category_id,
                        'default_variant_id' => $variant->id,
                    ];
                }
            }
            if (! $variantHit && (mb_stripos($product->name, $search) !== false || in_array($search, $product->tags ?? []))) {
                $results[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'image_url' => $product->image_url ?: $product->variants->flatMap(fn ($v) => $v->images)->first()?->image_url,
                    'min_price' => $product->variants->min(fn ($v) => $v->sell_price ?? $v->price),
                    'category_id' => $product->category_id,
                    'default_variant_id' => $product->variants->first()?->id,
                ];
            }
        }

        return $results;
    }

    /**
     * @OA\Get(
     *     path="/cafe/products/{id}",
     *     tags={"Cafe Mobile Catalog"},
     *     summary="Get product details",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function showProduct(int $id): JsonResponse
    {
        $product = Product::with(['category', 'variants.images'])->findOrFail($id);
        $primaryImage = $product->variants->flatMap(fn ($v) => $v->images)->first(fn ($img) => $img->is_primary)
            ?? $product->variants->flatMap(fn ($v) => $v->images)->first();
        return $this->jsonResponse([
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'image_url' => $product->image_url ?: $primaryImage?->image_url,
            'category' => $product->category,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/cafe/products/{id}/variants",
     *     tags={"Cafe Mobile Catalog"},
     *     summary="Get product variants by product id",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="List of product variants"),
     *     @OA\Response(response=404, description="Product not found")
     * )
     */
    public function productVariants(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        return $this->jsonResponse(['data' => $product->variants()->with('images')->get()]);
    }

    /**
     * @OA\Get(
     *     path="/cafe/cart",
     *     tags={"Cafe Mobile Cart"},
     *     summary="Get the current cafe user's active cart",
     *     @OA\Response(response=200, description="Cart details")
     * )
     */
    public function cart(): JsonResponse
    {
        $cart = $this->currentCart();

        return $this->jsonResponse([
            'data' => $cart?->load(['branch', 'items.productVariant.product']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/cafe/cart/items",
     *     tags={"Cafe Mobile Cart"},
     *     summary="Add an item to the cafe user's cart",
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="branch_id", type="integer"),
     *         @OA\Property(property="product_variant_id", type="integer"),
     *         @OA\Property(property="quantity", type="integer"),
     *         @OA\Property(property="price_at_add", type="number", format="float", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Item added"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addCartItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:cafe_branch,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variant,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $branch = $this->branchScope()->findOrFail($data['branch_id']);
        $variant = ProductVariant::findOrFail($data['product_variant_id']);

        $cart = $this->currentCart($branch->id);

        // ponytail: one branch per cart; reset items if branch changes
        if ($cart->branch_id && $cart->branch_id !== $branch->id) {
            $cart->items()->delete();
        }
        $cart->update(['branch_id' => $branch->id]);

        $item = $cart->items()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            [
                'quantity' => $data['quantity'],
                'price_at_add' => $variant->price,
            ]
        );

        return $this->jsonResponse([
            'data' => $item->load('productVariant.product'),
            'cart' => $cart->load(['branch', 'items.productVariant.product']),
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/cafe/cart/items/{id}",
     *     tags={"Cafe Mobile Cart"},
     *     summary="Update a cart item quantity",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="quantity", type="integer"))),
     *     @OA\Response(response=200, description="Item updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateCartItem(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $cart = $this->currentCart();
        if (! $cart) {
            return $this->jsonResponse(['message' => 'السلة غير موجودة'], 404);
        }

        $item = $cart->items()->findOrFail($id);
        $item->update(['quantity' => $data['quantity']]);

        return $this->jsonResponse($cart->load(['branch', 'items.productVariant.product']));
    }

    /**
     * @OA\Delete(
     *     path="/cafe/cart/items/{id}",
     *     tags={"Cafe Mobile Cart"},
     *     summary="Remove an item from the cart",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Item removed"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function removeCartItem(int $id): JsonResponse
    {
        $cart = $this->currentCart();
        if (! $cart) {
            return $this->jsonResponse(['message' => 'السلة غير موجودة'], 404);
        }

        $cart->items()->findOrFail($id)->delete();

        return $this->jsonResponse($cart->load(['branch', 'items.productVariant.product']));
    }

    /**
     * @OA\Delete(
     *     path="/cafe/cart",
     *     tags={"Cafe Mobile Cart"},
     *     summary="Clear the current cart",
     *     @OA\Response(response=200, description="Cart cleared")
     * )
     */
    public function clearCart(): JsonResponse
    {
        $cart = $this->currentCart();
        $cart?->items()->delete();
        $cart?->delete();

        return $this->jsonResponse(['message' => 'تم إفراغ السلة']);
    }

    /**
     * @OA\Post(
     *     path="/cafe/cart/checkout",
     *     tags={"Cafe Mobile Cart"},
     *     summary="Checkout the cart and create an order",
     *     @OA\Response(response=201, description="Order created"),
     *     @OA\Response(response=400, description="Cart is empty")
     * )
     */
    public function checkout(): JsonResponse
    {
        $cart = $this->currentCart();

        if (! $cart || $cart->items->isEmpty()) {
            return $this->jsonResponse(['message' => 'السلة فارغة'], 400);
        }

        $branch = $this->branchScope()->findOrFail($cart->branch_id);
        $items = $cart->items->map(fn (CartItem $item) => [
            'product_variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->price_at_add,
        ])->all();

        $subtotal = $cart->items->sum(fn ($item) => $item->quantity * $item->price_at_add);
        $deliveryFee = $branch->deliveryZone?->delivery_price ?? 0;

        $orderData = [
            'user_id' => auth()->id(),
            'branch_id' => $branch->id,
            'delegate_id' => null,
            'delivery_zone_id' => $branch->delivery_zone_id,
            'delivery_fee' => $deliveryFee,
            'order_date' => now(),
            'status' => 'pending',
            'source' => 'cafe_app',
            'total_amount' => $subtotal + $deliveryFee,
        ];

        $order = DB::transaction(function () use ($orderData, $items, $cart) {
            $orderData['order_number'] = Order::generateOrderNumber();
            $order = Order::create($orderData);
            $order->items()->createMany($items);
            $cart->items()->delete();
            $cart->delete();
            return $order;
        });

        $assigned = app(DelegateAssignmentService::class)->assignNearest($order);

        Notification::notifyAdmins(
            'طلب جديد من سلة مقهى',
            "تم إنشاء طلب جديد برقم {$order->order_number}",
            "/orders/{$order->id}",
            'order'
        );

        return $this->jsonResponse([
            'id' => $order->id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'delegate_id' => $order->delegate_id,
            'message' => 'تم إنشاء الطلب بنجاح',
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/cafe/orders/{id}/delegate",
     *     tags={"Cafe Mobile Orders"},
     *     summary="Get the assigned delegate live location for a cafe order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Delegate location"),
     *     @OA\Response(response=404, description="No delegate assigned")
     * )
     */
    public function orderDelegate(int $id): JsonResponse
    {
        $order = $this->orderScope()
            ->with(['delegate'])
            ->findOrFail($id);

        if (! $order->delegate_id) {
            return $this->jsonResponse(['message' => 'لا يوجد مندوب مخصص لهذا الطلب'], 404);
        }

        $delegate = $order->delegate;

        return $this->jsonResponse([
            'data' => [
                'id' => $delegate->id,
                'name' => $delegate->name,
                'latitude' => $delegate->latitude,
                'longitude' => $delegate->longitude,
                'is_available' => $delegate->is_available,
                'location_updated_at' => $delegate->location_updated_at?->toDateTimeString(),
            ],
        ]);
    }

    private function currentCart(?int $branchId = null): ?Cart
    {
        $cart = Cart::with('items')
            ->where('user_id', auth()->id())
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active');
            })
            ->first();

        if (! $cart && $branchId) {
            $cart = Cart::create([
                'user_id' => auth()->id(),
                'branch_id' => $branchId,
                'status' => 'active',
            ]);
            $cart->load('items');
        }

        return $cart;
    }
}
