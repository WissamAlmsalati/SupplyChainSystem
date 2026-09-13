<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variantId = $this->route('product_variant')?->id;

        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'name' => ['required', 'string', 'max:100'],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'barcode')->ignore($variantId)],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
