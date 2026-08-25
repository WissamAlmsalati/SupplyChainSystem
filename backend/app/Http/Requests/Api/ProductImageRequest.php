<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'product_variant_id' => ['required', 'integer', 'exists:product_variant,id'],
            'is_primary' => ['boolean'],
        ];

        if ($this->hasFile('image')) {
            $rules['image'] = ['required', 'image', 'max:2048'];
            $rules['url'] = ['nullable', 'string'];
        } else {
            $rules['url'] = ['required', 'string'];
        }

        return $rules;
    }
}
