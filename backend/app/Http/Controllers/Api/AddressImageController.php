<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pictures of a delivery address — the shop front, the door, the sign — so a
 * driver finds a place that a pin and a street name do not describe.
 *
 * One controller serves both doors. A cafe reaches only its own addresses; the
 * office reaches any. Which one is asking is decided here rather than by the
 * route, so the two can never drift apart.
 */
class AddressImageController extends BaseApiController
{
    private const DIRECTORY = 'addresses';

    private function address(int $id): Address
    {
        $query = Address::query();

        // A cafe asking through its own door sees only its own branches; the
        // 404 is deliberate, so a stranger's address id cannot be confirmed.
        if (auth()->user()?->userType?->name === UserRole::Customer->value) {
            $query->where('user_id', auth()->id());
        }

        return $query->findOrFail($id);
    }

    /**
     * @OA\Post(path="/customer/addresses/{id}/images", tags={"Customer Addresses"}, summary="Add a picture to an address",
     *     description="Multipart upload. The first picture an address gets becomes its primary; send `is_primary` to make a later one take over. Pictures always come back as the `images` list on the address.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data",
     *
     *         @OA\Schema(required={"image"},
     *
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="is_primary", type="boolean")))),
     *
     *     @OA\Response(response=201, description="Picture added",
     *
     *         @OA\JsonContent(example={"success": true, "message": "تمت إضافة الصورة بنجاح", "data": {"id": 41, "url": "/storage/addresses/front.jpg", "type": "jpg", "is_primary": true, "sort_order": 0}})),
     *
     *     @OA\Response(response=404, description="Address not found or not yours"))
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $address = $this->address($id);
        $data = $request->validate([
            // 10 MB is what PHP accepts for an upload here (docker/php/uploads.ini).
            'image' => ['required', 'image', 'max:10240'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $image = $address->attachImage($request->file('image'), self::DIRECTORY, (bool) ($data['is_primary'] ?? false));

        return $this->jsonResponse(['message' => 'تمت إضافة الصورة بنجاح', 'data' => $image->toClient()], 201);
    }

    /**
     * @OA\Patch(path="/customer/addresses/{id}/images/{imageId}", tags={"Customer Addresses"}, summary="Make a picture the primary one",
     *     description="Exactly one picture is primary; promoting one demotes the rest, and the promoted picture leads the `images` list.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="imageId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"is_primary"}, @OA\Property(property="is_primary", type="boolean", example=true))),
     *
     *     @OA\Response(response=200, description="Picture promoted"),
     *     @OA\Response(response=404, description="Address or picture not found"))
     */
    public function update(Request $request, int $id, int $imageId): JsonResponse
    {
        $address = $this->address($id);
        $request->validate(['is_primary' => ['required', 'boolean', 'accepted']]);

        $image = $address->images()->findOrFail($imageId);
        $address->makePrimary($image);

        return $this->jsonResponse(['message' => 'تم تعيين الصورة الرئيسية', 'data' => $image->fresh()->toClient()]);
    }

    /**
     * @OA\Delete(path="/customer/addresses/{id}/images/{imageId}", tags={"Customer Addresses"}, summary="Delete a picture",
     *     description="Removes the row and the stored file. An address left with none answers the default artwork.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="imageId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Picture deleted"),
     *     @OA\Response(response=404, description="Address or picture not found"))
     */
    public function destroy(int $id, int $imageId): JsonResponse
    {
        $address = $this->address($id);
        $image = $address->images()->findOrFail($imageId);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // The list must never be left leaderless: the next picture steps up.
        if ($wasPrimary && ($next = $address->images()->first()) instanceof Image) {
            $address->makePrimary($next);
        }

        return $this->jsonResponse(['message' => 'تم حذف الصورة بنجاح']);
    }
}
