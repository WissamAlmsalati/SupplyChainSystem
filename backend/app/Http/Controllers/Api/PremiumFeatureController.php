<?php

namespace App\Http\Controllers\Api;

use App\Models\PremiumFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Premium Features", description="System premium features management")
 */
class PremiumFeatureController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/premium-features",
     *     tags={"Premium Features"},
     *     summary="List premium features",
     *     @OA\Response(response=200, description="List of premium features")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->jsonResponse(PremiumFeature::all());
    }

    /**
     * @OA\Put(
     *     path="/premium-features/{id}",
     *     tags={"Premium Features"},
     *     summary="Update a premium feature status",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="is_active", type="boolean"))),
     *     @OA\Response(response=200, description="Feature updated")
     * )
     */
    public function update(Request $request, PremiumFeature $premiumFeature): JsonResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $premiumFeature->update($data);
        return $this->jsonResponse($premiumFeature);
    }
}
