<?php

namespace App\Http\Controllers\Api;

use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Read-only stock ledger.
class StockMovementController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['warehouse:id,name', 'productVariant.product:id,name', 'createdBy:id,name']);

        foreach (['warehouse_id', 'product_variant_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('reference_type') && $request->filled('reference_id')) {
            $query->where('reference_type', $request->input('reference_type'))
                ->where('reference_id', $request->integer('reference_id'));
        }

        return $this->paginated($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function show(StockMovement $stockMovement): JsonResponse
    {
        return $this->jsonResponse($stockMovement->load(['warehouse', 'productVariant.product', 'createdBy', 'reference']));
    }
}
