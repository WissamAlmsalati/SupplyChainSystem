<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\DeliveryZoneRequest;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Admin Delivery Zones", description="Admin platform delivery zone management")
 */
class DeliveryZoneController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/delivery-zones",
     *     tags={"Admin Delivery Zones"},
     *     summary="List delivery zones",
     *     @OA\Response(response=200, description="Paginated list of delivery zones")
     * )
     */
    public function index(): JsonResponse
    {
        $perPage = request()->integer('per_page', 15);
        return $this->jsonResponse(DeliveryZone::paginate($perPage > 0 ? min($perPage, 10000) : 15));
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
        $zone = DeliveryZone::create($request->validated());
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
        return $this->jsonResponse($deliveryZone->load(['cafeBranches', 'orders']));
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
        $deliveryZone->update($request->validated());
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
