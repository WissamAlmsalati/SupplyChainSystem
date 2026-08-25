<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouse,id'],
            'product_variant_id' => ['required', 'integer', 'exists:product_variant,id'],
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
