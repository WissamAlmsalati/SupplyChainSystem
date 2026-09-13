<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use App\Services\H3Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Delivery Zones", description="Admin platform delivery zone management")
 */
class DeliveryZoneController extends BaseApiController
{
    private function resolveCenter(array $data): array
    {
        if (empty($data['latitude']) && empty($data['longitude']) && ! empty($data['hex_id'])) {
            try {
                [$lat, $lng] = H3Service::cellToLatLng($data['hex_id']);
                $data['latitude'] = $lat;
                $data['longitude'] = $lng;
            } catch (\Throwable $e) {
                // leave nullable; request validation will handle bad hex if needed
            }
        }

        return $data;
    }
    /**
     * @OA\Get(
     *     path="/delivery-zones",
     *     tags={"Admin Delivery Zones"},
     *     summary="List delivery zones",
     *     @OA\Response(response=200, description="Paginated list of delivery zones")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = request()->integer('per_page', 15);
        $query = DeliveryZone::query();

        if (request()->has('warehouse_id')) {
            $query->where('warehouse_id', request()->integer('warehouse_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('hex_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $this->jsonResponse($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    /**
     * @OA\Post(
     *     path="/delivery-zones",
     *     tags={"Admin Delivery Zones"},
     *     summary="Create a delivery zone",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DeliveryZoneRequest")),
     *     @OA\Response(response=201, description="Delivery zone created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(DeliveryZoneRequest $request): JsonResponse
    {
        $data = $this->resolveCenter($request->validated());
        $zone = DeliveryZone::create($data);
        return $this->jsonResponse($zone, 201);
    }

    /**
     * @OA\Get(
     *     path="/delivery-zones/{id}",
     *     tags={"Admin Delivery Zones"},
     *     summary="Get a delivery zone",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Delivery zone details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(DeliveryZone $deliveryZone): JsonResponse
    {
        return $this->jsonResponse($deliveryZone->load(['warehouse', 'addresses', 'orders']));
    }

    /**
     * @OA\Put(
     *     path="/delivery-zones/{id}",
     *     tags={"Admin Delivery Zones"},
     *     summary="Update a delivery zone",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DeliveryZoneRequest")),
     *     @OA\Response(response=200, description="Delivery zone updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(DeliveryZoneRequest $request, DeliveryZone $deliveryZone): JsonResponse
    {
        $data = $this->resolveCenter($request->validated());
        $deliveryZone->update($data);
        return $this->jsonResponse($deliveryZone);
    }

    /**
     * @OA\Delete(
     *     path="/delivery-zones/{id}",
     *     tags={"Admin Delivery Zones"},
     *     summary="Delete a delivery zone",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Delivery zone deleted")
     * )
     */
    public function destroy(DeliveryZone $deliveryZone): JsonResponse
    {
        $deliveryZone->delete();
        return $this->jsonResponse(null, 204);
    }
}
