<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:product,id'],
            'sku' => ['nullable', 'string', 'max:50'],
            'attribute_name' => ['nullable', 'string', 'max:50'],
            'attribute_value' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'manufacturing_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'expiry_date' => ['nullable', 'date'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
