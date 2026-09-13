<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\WarehouseRequest;
use App\Models\DeliveryZone;
use App\Models\PremiumFeature;
use App\Models\Warehouse;
use App\Services\H3Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Warehouses", description="Admin platform warehouse management")
 */
class WarehouseController extends BaseApiController
{
    private function resolveHex(array $data): array
    {
        if (empty($data['hex_id']) && isset($data['latitude'], $data['longitude'], $data['resolution'])) {
            $data['hex_id'] = H3Service::latLngToCell(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (int) $data['resolution']
            );
        }

        return $data;
    }

    private function syncWarehouseZones(Warehouse $warehouse, array $hexIds): void
    {
        // Detach zones that are no longer selected
        DeliveryZone::where('warehouse_id', $warehouse->id)
            ->whereNotIn('hex_id', $hexIds)
            ->update(['warehouse_id' => null]);

        if (empty($hexIds)) {
            return;
        }

        $existing = DeliveryZone::whereIn('hex_id', $hexIds)->get()->keyBy('hex_id');

        foreach ($hexIds as $hexId) {
            if ($existing->has($hexId)) {
                $existing->get($hexId)->update(['warehouse_id' => $warehouse->id]);
            } else {
                $center = H3Service::cellToLatLng($hexId);
                DeliveryZone::create([
                    'hex_id' => $hexId,
                    'warehouse_id' => $warehouse->id,
                    'name' => 'منطقة ' . substr($hexId, -6),
                    'delivery_price' => 0,
                    'latitude' => $center[0],
                    'longitude' => $center[1],
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @OA\Get(
     *     path="/warehouses",
     *     tags={"Admin Warehouses"},
     *     summary="List warehouses",
     *     @OA\Response(response=200, description="Paginated list of warehouses")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::with('deliveryZones')->withCount('deliveryZones as zones_count');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate(15));
    }

    /**
     * @OA\Post(
     *     path="/warehouses",
     *     tags={"Admin Warehouses"},
     *     summary="Create a warehouse",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/WarehouseRequest")),
     *     @OA\Response(response=201, description="Warehouse created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(WarehouseRequest $request): JsonResponse
    {
        // ponytail: add_inventory feature now gates warehouse creation (not inventory rows);
        // frontend hides the button, this guard blocks direct API calls.
        if (!PremiumFeature::isActive('add_inventory')) {
            return $this->jsonResponse(['message' => 'إضافة مستودع جديد غير متاحة — الميزة معطلة'], 403);
        }

        $data = $this->resolveHex($request->validated());
        $hexIds = $data['hex_ids'] ?? [];
        unset($data['hex_ids']);

        $warehouse = Warehouse::create($data);
        $this->syncWarehouseZones($warehouse, $hexIds);

        return $this->jsonResponse($warehouse->load('deliveryZones'), 201);
    }

    /**
     * @OA\Get(
     *     path="/warehouses/{id}",
     *     tags={"Admin Warehouses"},
     *     summary="Get a warehouse",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Warehouse details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        return $this->jsonResponse($warehouse->load([
            'inventories.productVariant.product',
            'deliveryZones',
        ]));
    }

    /**
     * @OA\Put(
     *     path="/warehouses/{id}",
     *     tags={"Admin Warehouses"},
     *     summary="Update a warehouse",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/WarehouseRequest")),
     *     @OA\Response(response=200, description="Warehouse updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $data = $this->resolveHex($request->validated());
        $hexIds = $data['hex_ids'] ?? [];
        unset($data['hex_ids']);

        $warehouse->update($data);
        $this->syncWarehouseZones($warehouse, $hexIds);

        return $this->jsonResponse($warehouse->load('deliveryZones'));
    }

    /**
     * @OA\Delete(
     *     path="/warehouses/{id}",
     *     tags={"Admin Warehouses"},
     *     summary="Delete a warehouse",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Warehouse deleted")
     * )
     */
    // Soft delete; blocked while the warehouse still holds stock.
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->inventories()->where('quantity', '>', 0)->exists()) {
            return $this->jsonResponse(['message' => 'لا يمكن حذف مستودع يحتوي على مخزون'], 422);
        }

        $warehouse->delete();

        return $this->jsonResponse(['message' => 'تم حذف المستودع بنجاح'], 200);
    }

    public function expandHex(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'child_resolution' => ['required', 'integer', 'min:0', 'max:15'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (empty($warehouse->hex_id)) {
            return $this->jsonResponse(['message' => 'المستودع لا يملك شكل سداسي محدد'], 422);
        }

        $childRes = (int) $data['child_resolution'];
        if ($childRes <= H3Service::getResolution($warehouse->hex_id)) {
            return $this->jsonResponse(['message' => 'دقة مناطق التوصيل يجب أن تكون أكبر من دقة المستودع'], 422);
        }
        $defaultPrice = (float) ($data['default_price'] ?? 0);
        $children = H3Service::cellToChildren($warehouse->hex_id, $childRes);
        \Illuminate\Support\Facades\Log::info('expandHex', ['children_count' => count($children), 'children' => $children]);

        $created = [];
        foreach ($children as $hexId) {
            $center = H3Service::cellToLatLng($hexId);
            $zone = DeliveryZone::firstOrCreate(
                ['hex_id' => $hexId],
                [
                    'warehouse_id' => $warehouse->id,
                    'name' => 'منطقة ' . substr($hexId, -6),
                    'delivery_price' => $defaultPrice,
                    'latitude' => $center[0],
                    'longitude' => $center[1],
                    'is_active' => true,
                ]
            );
            \Illuminate\Support\Facades\Log::info('expandHex loop', ['hex_id' => $hexId, 'zone_id' => $zone->id, 'created' => $zone->wasRecentlyCreated]);
            $created[] = $zone;
        }

        return $this->jsonResponse([
            'message' => 'تم إنشاء ' . count($created) . ' منطقة توصيل',
            'data' => $created,
        ], 201);
    }
}
