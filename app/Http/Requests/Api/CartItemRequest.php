<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cart_id' => ['required', 'integer', 'exists:cart,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variant,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price_at_add' => ['required', 'numeric', 'min:0'],
        ];
    }
}
