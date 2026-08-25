<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStore = $this->isMethod('post');

        return [
            'user_id' => [$isStore ? 'required' : 'sometimes', 'integer', 'exists:app_user,id'],
            'branch_id' => [$isStore ? 'required' : 'sometimes', 'integer', 'exists:cafe_branch,id'],
            'delegate_id' => ['nullable', 'integer', 'exists:app_user,id'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zone,id'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'order_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'max:30'],
            'total_amount' => [$isStore ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.product_variant_id' => ['required_with:items', 'integer', 'exists:product_variant,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
