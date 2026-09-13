<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  array<int, array{product_variant_id:int, requested:int, available:int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('الكمية المطلوبة غير متوفرة لبعض المنتجات');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'shortages' => $this->shortages,
        ], 409, [], JSON_UNESCAPED_UNICODE);
    }
}
