<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class H3Service
{
    private static function call(array $input): mixed
    {
        $script = base_path('scripts/h3.cjs');
        $result = Process::input(json_encode($input))->run('node '.escapeshellarg($script));

        if (! $result->successful()) {
            throw new RuntimeException('H3 service failed: '.$result->errorOutput());
        }

        $decoded = json_decode($result->output(), true);

        if (! ($decoded['ok'] ?? false)) {
            throw new RuntimeException('H3 error: '.($decoded['error'] ?? 'unknown'));
        }

        return $decoded['result'];
    }

    public static function latLngToCell(float $lat, float $lng, int $res): string
    {
        return self::call(['op' => 'latLngToCell', 'lat' => $lat, 'lng' => $lng, 'res' => $res]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    public static function cellToLatLng(string $hexId): array
    {
        return self::call(['op' => 'cellToLatLng', 'hexId' => $hexId]);
    }

    /**
     * @return string[]
     */
    public static function cellToChildren(string $hexId, int $childRes): array
    {
        return self::call(['op' => 'cellToChildren', 'hexId' => $hexId, 'childRes' => $childRes]);
    }

    public static function getResolution(string $hexId): int
    {
        return (int) self::call(['op' => 'getResolution', 'hexId' => $hexId]);
    }

    public static function cellToParent(string $hexId, int $parentRes): string
    {
        return self::call(['op' => 'cellToParent', 'hexId' => $hexId, 'parentRes' => $parentRes]);
    }
}
