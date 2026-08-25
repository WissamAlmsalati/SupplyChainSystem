<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class DelegateDocsController extends BaseApiController
{
    /**
     * Return the filtered OpenAPI JSON for the delegate mobile app.
     */
    public function json(): JsonResponse
    {
        $path = storage_path('api-docs/api-docs.json');
        if (! File::exists($path)) {
            return $this->jsonResponse(['message' => 'API docs not generated'], 404);
        }

        $docs = json_decode(File::get($path), true);
        $delegateTag = 'Delegate Mobile';

        $paths = [];
        foreach ($docs['paths'] ?? [] as $route => $methods) {
            foreach ($methods as $method => $operation) {
                if (in_array($delegateTag, $operation['tags'] ?? [], true)) {
                    $operation['security'] = [['bearerAuth' => []]];
                    $paths[$route][$method] = $operation;
                }
            }
        }

        $schemas = $docs['components']['schemas'] ?? [];
        $usedSchemas = $this->collectUsedSchemas($paths, $schemas);

        $filtered = [
            'openapi' => $docs['openapi'] ?? '3.0.0',
            'info' => [
                'title' => 'Delegate Mobile API',
                'description' => 'API documentation for the delegate mobile app. All endpoints require a delegate bearer token.',
                'version' => $docs['info']['version'] ?? '1.0.0',
            ],
            'servers' => [
                ['url' => '/api', 'description' => 'Local development server'],
            ],
            'paths' => $paths,
            'components' => [
                'schemas' => $usedSchemas,
                'securitySchemes' => $docs['components']['securitySchemes'] ?? [],
            ],
            'tags' => [
                ['name' => $delegateTag, 'description' => 'Delegate mobile app endpoints'],
            ],
        ];

        return response()->json($filtered);
    }

    /**
     * Show the Swagger UI for the delegate mobile app.
     */
    public function ui()
    {
        return view('swagger-delegate');
    }

    protected function collectUsedSchemas(array $paths, array $schemas): array
    {
        $used = [];
        $refs = $this->findRefs($paths);

        foreach ($refs as $ref) {
            $name = str_replace('#/components/schemas/', '', $ref);
            if (isset($schemas[$name])) {
                $used[$name] = $schemas[$name];
            }
        }

        foreach (['ValidationError'] as $name) {
            if (isset($schemas[$name])) {
                $used[$name] = $schemas[$name];
            }
        }

        return $used;
    }

    protected function findRefs(array $data): array
    {
        $refs = [];
        array_walk_recursive($data, function ($value, $key) use (&$refs) {
            if ($key === '$ref' && is_string($value)) {
                $refs[] = $value;
            }
        });

        return array_unique($refs);
    }
}
