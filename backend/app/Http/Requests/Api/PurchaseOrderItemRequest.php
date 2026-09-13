<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'manufacturing_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
