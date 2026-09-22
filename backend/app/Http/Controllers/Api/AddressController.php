<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Requests\Api\AddressRequest;
use App\Models\Address;
use App\Models\PremiumFeature;
use App\Services\AddressZoneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Admin Addresses", description="Admin platform address management")
 */
class AddressController extends BaseApiController
{
    /**
     * Resolves the zone for a point and writes it into $data. Returns whether we
     * actually got an answer: false means the hexagon service did not reply, so
     * nobody knows whether the point is covered and anything the office is told
     * about coverage would be a guess. A zone the office set by hand survives a
     * lookup that found nothing, which is how an uncovered place is served.
     */
    private function applyZone(array &$data, float $latitude, float $longitude): bool
    {
        $chosenByHand = ! empty($data['delivery_zone_id']);

        try {
            $zone = app(AddressZoneResolver::class)->resolve($latitude, $longitude);
        } catch (\RuntimeException $e) {
            report($e);

            return false;
        }

        // ponytail: a pin that moved out of every zone clears the zone it had,
        // or the address would go on charging the fee of a zone that no longer
        // reaches it. A zone named in this very request survives, which is how
        // the office serves a place no zone covers yet.
        if ($zone) {
            $data['delivery_zone_id'] = $zone->id;
        } elseif (! $chosenByHand) {
            $data['delivery_zone_id'] = null;
        }

        return true;
    }

    /**
     * The dashboard gets the same answer the apps get: an address no zone reaches
     * is still saved, and carries `is_deliverable: false` with a sentence ready for
     * a dialog, rather than a plain success for an address nobody can deliver to.
     * Shape matches CustomerMobileController::addressSaved() so one dialog serves
     * both; the status stays 201/200, because the record was created in full.
     *
     * A hexagon service that did not answer leaves coverage unknown, and saying
     * "outside coverage" would be a guess — so that case keeps the plain success.
     */
    private function addressSaved(Address $address, int $status, bool $coverageKnown): JsonResponse
    {
        $deliverable = (bool) $address->deliveryZone?->is_active;
        if ($deliverable || ! $coverageKnown) {
            return $this->jsonResponse($address, $status);
        }

        return response()->json([
            'success' => true,
            'is_deliverable' => false,
            'title' => AddressZoneResolver::OUTSIDE_COVERAGE_TITLE,
            'message' => AddressZoneResolver::OUTSIDE_COVERAGE_MESSAGE,
            'data' => $address,
        ], $status, [], JSON_UNESCAPED_UNICODE);
    }

    private function isCustomer(): bool
    {
        return auth()->user()?->userType?->name === UserRole::Customer->value;
    }

    /**
     * @OA\Get(
     *     path="/addresses",
     *     tags={"Admin Addresses"},
     *     summary="List addresses",
     *
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
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *
     *     @OA\Response(response=201, description="Address created. When no delivery zone reaches the point and none was set by hand, the body carries `is_deliverable: false` with a `title` and `message` ready for a dialog; a hand-set zone, or a hexagon service that did not answer, answers the plain model."),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    public function store(AddressRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($this->isCustomer()) {
            $data['user_id'] = auth()->id();
        }

        // The feature limits extra branches; a customer's first address is always allowed.
        if (Address::where('user_id', $data['user_id'])->exists() && ! PremiumFeature::isActive('customer_branches')) {
            return $this->jsonResponse(['message' => 'إضافة فروع أخرى غير متاحة — الميزة معطلة'], 403);
        }

        // The point decides the zone. The office may still set one by hand, for a
        // place no zone covers yet or while the hexagon service is down; without
        // either, the address has no fee.
        $looked = $this->applyZone($data, (float) $data['latitude'], (float) $data['longitude']);

        $address = Address::create($data);

        return $this->addressSaved($address->load(['user', 'deliveryZone']), 201, $looked);
    }

    /**
     * @OA\Get(
     *     path="/addresses/{id}",
     *     tags={"Admin Addresses"},
     *     summary="Get an address",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/AddressRequest")),
     *
     *     @OA\Response(response=200, description="Address updated. When the pin moved outside every delivery zone, the body carries `is_deliverable: false` as on create; coverage is only re-read when the pin moved."),
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

        // Coverage is only re-read when the pin moved; otherwise it is not our
        // place to say anything about it, so $looked stays false.
        $looked = false;
        if (array_key_exists('latitude', $data) || array_key_exists('longitude', $data)) {
            $looked = $this->applyZone($data, (float) ($data['latitude'] ?? $address->latitude), (float) ($data['longitude'] ?? $address->longitude));
        }

        $address->update($data);

        return $this->addressSaved($address->load(['user', 'deliveryZone']), 200, $looked);
    }

    /**
     * @OA\Delete(
     *     path="/addresses/{id}",
     *     tags={"Admin Addresses"},
     *     summary="Delete an address",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
