<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

// Leave product_variant_id empty for an image of the product itself.
class ProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStore = $this->isMethod('post');

        return [
            'product_id' => [$isStore ? 'required_without:product_variant_id' : 'sometimes', 'nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'image' => [$isStore ? 'required_without:url' : 'nullable', 'image', 'max:2048'],
            'url' => [$isStore ? 'required_without:image' : 'nullable', 'string', 'max:500'],
            'is_primary' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
