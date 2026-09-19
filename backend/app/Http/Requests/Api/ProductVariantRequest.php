<?php

namespace App\Http\Requests\Api;

use App\Models\ProductVariant;
use App\Support\ArabicText;
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
            // "250 جم" and "250جم" are one size to a person. Two rows for it split
            // the stock and leave one of them orderable at whatever price it got.
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($variantId) {
                $fold = fn (?string $v) => preg_replace('/\s+/u', '', ArabicText::normalize($v));
                $taken = ProductVariant::where('product_id', $this->input('product_id'))
                    ->when($variantId, fn ($q) => $q->where('id', '!=', $variantId))
                    ->pluck('name')->contains(fn ($name) => $fold($name) === $fold($value));
                if ($taken) {
                    $fail('هذا الحجم موجود لهذا المنتج من قبل');
                }
            }],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'barcode')->ignore($variantId)],
            // A size with no price is a size sold for free.
            'price' => ['required', 'numeric', 'gt:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
