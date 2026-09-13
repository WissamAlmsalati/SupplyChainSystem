<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_active' => ['boolean'],
            // Stored as the product's primary image in product_images.
            'image' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
