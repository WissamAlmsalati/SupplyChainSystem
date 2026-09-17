<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Requests\Api\AddressRequest;
use App\Models\Address;
use App\Models\PremiumFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Addresses", description="Admin platform address management")
 */
class AddressController extends BaseApiController
{
    private function isCustomer(): bool
    {
        return auth()->user()?->userType?->name === UserRole::Customer->value;
    }

    /**
     * @OA\Get(
     *     path="/addresses",
     *     tags={"Admin Addresses"},
     *     summary="List addresses",
     *     @OA\Response(response=200, description="Paginated list of addresses")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = request()->integer('per_page', 15);
        $query = Address::with(['user', 'deliveryZone']);

        if ($this->isCustomer()) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('street', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return $this->paginated($query->orderByDesc('id')->paginate($perPage > 0 ? min($perPage, 10000) : 15));
    }

    /**
     * @OA\Post(
     *     path="/addresses",
     *     tags={"Admin Addresses"},
     *     summary="Create an address",
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *     @OA\Response(response=201, description="Address created"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(AddressRequest $request): JsonResponse
    {
        // ponytail: customer_branches premium feature gates address creation for everyone;
        // frontend hides the add button, this guard blocks direct API calls.
        if (!PremiumFeature::isActive('customer_branches')) {
            return $this->jsonResponse(['message' => 'إضافة عناوين غير متاحة — الميزة معطلة'], 403);
        }

        $data = $request->validated();

        if ($this->isCustomer()) {
            $data['user_id'] = auth()->id();
        }

        $address = Address::create($data);
        return $this->jsonResponse($address->load(['user', 'deliveryZone']), 201);
    }

    /**
     * @OA\Get(
     *     path="/addresses/{id}",
     *     tags={"Admin Addresses"},
     *     summary="Get an address",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Address details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Address $address): JsonResponse
    {
        return $this->jsonResponse($address->load(['user', 'deliveryZone', 'orders']));
    }

    /**
     * @OA\Put(
     *     path="/addresses/{id}",
     *     tags={"Admin Addresses"},
     *     summary="Update an address",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *     @OA\Response(response=200, description="Address updated"),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function update(AddressRequest $request, Address $address): JsonResponse
    {
        if ($this->isCustomer() && $address->user_id !== auth()->id()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $data = $request->validated();
        if ($this->isCustomer()) {
            $data['user_id'] = auth()->id();
        }

        $address->update($data);
        return $this->jsonResponse($address->load(['user', 'deliveryZone']));
    }

    /**
     * @OA\Delete(
     *     path="/addresses/{id}",
     *     tags={"Admin Addresses"},
     *     summary="Delete an address",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Address deleted")
     * )
     */
    // Soft delete: past orders keep their own copy of the delivery address.
    public function destroy(Address $address): JsonResponse
    {
        if ($this->isCustomer() && $address->user_id !== auth()->id()) {
            return $this->jsonResponse(['message' => 'غير مصرح'], 403);
        }

        $address->delete();
        return $this->jsonResponse(null, 204);
    }
}
