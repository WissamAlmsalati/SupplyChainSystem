<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

// Status changes (receive/cancel) have dedicated endpoints.
class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.manufacturing_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ];
    }
}
