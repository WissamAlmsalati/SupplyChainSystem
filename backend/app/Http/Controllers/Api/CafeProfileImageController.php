<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerProfile;
use App\Models\Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pictures of the cafe itself — the shop front, the counter — as opposed to the
 * pictures of a branch's door, which hang on an Address.
 *
 * Everything here is scoped to the signed-in cafe: there is no id in the path
 * to get wrong, and no way to name somebody else's profile.
 */
class CafeProfileImageController extends BaseApiController
{
    private const DIRECTORY = 'cafes';

    // A cafe that has never filled in its profile still has one the moment it
    // uploads a picture; without this the first upload would have nothing to
    // hang on.
    private function profile(): CustomerProfile
    {
        return CustomerProfile::firstOrCreate(['user_id' => auth()->id()]);
    }

    /**
     * @OA\Post(path="/customer/profile/images", tags={"Customer Profile"}, summary="Add a picture of the cafe",
     *     description="Multipart upload, scoped to the signed-in cafe. The first picture becomes the primary one; send `is_primary` to make a later one take over. Pictures come back as `user.images` on the profile.",
     *     security={{"bearerAuth":{}}},
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
     *         @OA\JsonContent(example={"success": true, "message": "تمت إضافة الصورة بنجاح", "data": {"id": 7, "url": "/storage/cafes/shop.jpg", "type": "jpg", "is_primary": true, "sort_order": 0}})))
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $image = $this->profile()->attachImage($request->file('image'), self::DIRECTORY, (bool) ($data['is_primary'] ?? false));

        return $this->jsonResponse(['message' => 'تمت إضافة الصورة بنجاح', 'data' => $image->toClient()], 201);
    }

    /**
     * @OA\Patch(path="/customer/profile/images/{imageId}", tags={"Customer Profile"}, summary="Make a picture the primary one",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="imageId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"is_primary"}, @OA\Property(property="is_primary", type="boolean", example=true))),
     *
     *     @OA\Response(response=200, description="Picture promoted"),
     *     @OA\Response(response=404, description="No such picture on this cafe"))
     */
    public function update(Request $request, int $imageId): JsonResponse
    {
        $request->validate(['is_primary' => ['required', 'boolean', 'accepted']]);

        $profile = $this->profile();
        $image = $profile->images()->findOrFail($imageId);
        $profile->makePrimary($image);

        return $this->jsonResponse(['message' => 'تم تعيين الصورة الرئيسية', 'data' => $image->fresh()->toClient()]);
    }

    /**
     * @OA\Delete(path="/customer/profile/images/{imageId}", tags={"Customer Profile"}, summary="Delete a picture of the cafe",
     *     description="Removes the row and the stored file. A cafe left with none answers the default artwork.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="imageId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Picture deleted"),
     *     @OA\Response(response=404, description="No such picture on this cafe"))
     */
    public function destroy(int $imageId): JsonResponse
    {
        $profile = $this->profile();
        $image = $profile->images()->findOrFail($imageId);
        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary && ($next = $profile->images()->first()) instanceof Image) {
            $profile->makePrimary($next);
        }

        return $this->jsonResponse(['message' => 'تم حذف الصورة بنجاح']);
    }
}
