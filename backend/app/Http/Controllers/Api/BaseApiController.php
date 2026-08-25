<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PremiumFeature;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function requireFeature(string $code): ?JsonResponse
    {
        if (! PremiumFeature::isActive($code)) {
            return $this->jsonResponse([
                'message' => 'هذه الميزة غير متوفرة في خطتك',
            ], 403);
        }

        return null;
    }

    protected function jsonResponse(mixed $data, int $status = 200): JsonResponse
    {
        if ($status === 201 && is_array($data)) {
            $message = $data['message'] ?? 'تم الإنشاء بنجاح';
            $payload = $data;
            unset($payload['message']);

            // ponytail: unwrap nested ['data' => ...] so responses stay flat
            $payload = array_key_exists('data', $payload) && count($payload) === 1
                ? $payload['data']
                : $payload;

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $payload,
            ], 201, [], JSON_UNESCAPED_UNICODE);
        }

        if ($status >= 400 && is_array($data)) {
            $response = [
                'success' => false,
                'message' => $data['message'] ?? 'حدث خطأ',
            ];

            if (! empty($data['errors'])) {
                $response['errors'] = $data['errors'];
            }

            return response()->json($response, $status, [], JSON_UNESCAPED_UNICODE);
        }

        return response()->json($data, $status, [], JSON_UNESCAPED_UNICODE);
    }
}
