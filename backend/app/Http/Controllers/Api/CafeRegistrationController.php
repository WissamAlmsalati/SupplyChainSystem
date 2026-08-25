<?php

namespace App\Http\Controllers\Api;

use App\Models\Cafe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CafeRegistrationController extends BaseApiController
{
    public function pending(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $cafes = Cafe::with(['appUsers'])
            ->where('is_active', false)
            ->whereNull('created_by_admin_id')
            ->latest('created_at')
            ->paginate(15);

        return $this->jsonResponse($cafes);
    }

    public function approve(Request $request, Cafe $cafe): JsonResponse
    {
        $this->ensureAdmin($request);

        $cafe->update(['is_active' => true]);
        $cafe->appUsers()->update(['is_active' => true]);

        return $this->jsonResponse(['message' => 'تمت الموافقة على الطلب بنجاح']);
    }

    public function reject(Request $request, Cafe $cafe): JsonResponse
    {
        $this->ensureAdmin($request);

        $cafe->appUsers()->delete();
        $cafe->delete();

        return $this->jsonResponse(['message' => 'تم رفض الطلب بنجاح']);
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();

        if (! in_array($user?->userType?->name, ['admin', 'super_admin'], true)) {
            abort(403, 'غير مصرح');
        }
    }
}
