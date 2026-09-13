<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public function __construct(public readonly float $balance, public readonly float $required)
    {
        parent::__construct('رصيد المحفظة غير كافٍ');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'balance' => round($this->balance, 2),
            'required' => round($this->required, 2),
        ], 422, [], JSON_UNESCAPED_UNICODE);
    }
}
