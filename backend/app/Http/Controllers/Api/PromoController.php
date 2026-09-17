<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PromoRequest;
use App\Models\Promo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(name="Promos", description="Promotional banners for the mobile app")
 */
class PromoController extends BaseApiController
{
    private function storeImage(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        return $file->store('promos', 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function index(): JsonResponse
    {
        return $this->paginated(Promo::orderByDesc('id')->paginate(15));
    }

    /**
     * Active promos for the cafe mobile app (deep-linkable banners).
     */
    public function active(): JsonResponse
    {
        return $this->jsonResponse(Promo::where('is_active', true)->orderByDesc('id')->get());
    }

    public function store(PromoRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['image']);
        $data['image'] = $this->storeImage($request->file('image'));

        $promo = Promo::create($data);

        return $this->jsonResponse($promo, 201);
    }

    public function show(Promo $promo): JsonResponse
    {
        return $this->jsonResponse($promo);
    }

    public function update(PromoRequest $request, Promo $promo): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $this->deleteImage($promo->image);
            $data['image'] = $this->storeImage($request->file('image'));
        } else {
            unset($data['image']);
        }

        $promo->update($data);

        return $this->jsonResponse($promo);
    }

    public function destroy(Promo $promo): JsonResponse
    {
        $this->deleteImage($promo->image);
        $promo->delete();

        return $this->jsonResponse(['message' => 'تم حذف البرومو بنجاح']);
    }
}
