<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function jsonResponse(mixed $data, int $status = 200): JsonResponse
    {
        if ($status === 201 && is_array($data)) {
            $message = $data['message'] ?? 'تم الإنشاء بنجاح';
            $payload = $data;
            unset($payload['message']);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $payload,
            ], 201);
        }

        if ($status >= 400 && is_array($data)) {
            $response = [
                'success' => false,
                'message' => $data['message'] ?? 'حدث خطأ',
            ];

            if (! empty($data['errors'])) {
                $response['errors'] = $data['errors'];
            }

            return response()->json($response, $status);
        }

        return response()->json($data, $status);
    }
}
