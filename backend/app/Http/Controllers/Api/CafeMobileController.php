<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CafeBranchRequest;
use App\Models\Cafe;
use App\Models\CafeBranch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
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
        $orders = $this->orderScope()->orderByDesc('order_date')->get();
        return $this->jsonResponse(['data' => $orders]);
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
    public function branchOrders(int $id): JsonResponse
    {
        $branch = $this->branchScope()->findOrFail($id);
        $orders = $this->orderScope()
            ->where('branch_id', $branch->id)
            ->orderByDesc('order_date')
            ->get();

        return $this->jsonResponse(['data' => $orders]);
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
     *     summary="Update order status",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="status", type="string"))),
     *     @OA\Response(response=200, description="Status updated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|string|max:30']);
        $order = $this->orderScope()->findOrFail($id);
        $order->update(['status' => $request->input('status')]);
        return $this->jsonResponse($order->load(['user', 'branch', 'deliveryZone']));
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
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $items = $data['items'];
        $branch = CafeBranch::with('deliveryZone')->find($data['branch_id']);
        if (! $branch || $branch->cafe_id !== $this->cafeId()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

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
            $order = Order::create($orderData);
            $order->items()->createMany($items);
            return $order;
        });

        $assigned = app(DelegateAssignmentService::class)->assignNearest($order);

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
        $branch = $this->branchScope()->with(['cafe', 'deliveryZone', 'orders'])->findOrFail($id);
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
     * @OA\Get(
     *     path="/cafe/profile",
     *     tags={"Cafe Mobile Profile"},
     *     summary="Get cafe profile",
     *     @OA\Response(response=200, description="Cafe profile")
     * )
     */
    public function profile(): JsonResponse
    {
        $cafe = Cafe::with('branches')->findOrFail($this->cafeId());
        return $this->jsonResponse([
            'cafe' => $cafe,
            'user' => auth()->user()?->only(['id', 'name', 'email', 'mobile_number']),
        ]);
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
        $query = Product::select(['id', 'name', 'description', 'image']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return $this->jsonResponse(['data' => $query->get()]);
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
        $product = Product::with(['category', 'variants', 'supplier'])->findOrFail($id);
        return $this->jsonResponse($product);
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
        return $this->jsonResponse(['data' => $product->variants]);
    }
}
