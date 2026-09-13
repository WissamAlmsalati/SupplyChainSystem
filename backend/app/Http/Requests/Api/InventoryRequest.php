<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class InventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // store: goods received (added to stock). update: quantity is the counted on-hand value.
    public function rules(): array
    {
        $isStore = $this->isMethod('post');

        return [
            'warehouse_id' => [$isStore ? 'required' : 'prohibited', 'integer', 'exists:warehouses,id'],
            'product_variant_id' => [$isStore ? 'required' : 'prohibited', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', $isStore ? 'min:1' : 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'unit_cost' => [$isStore ? 'nullable' : 'prohibited', 'numeric', 'min:0'],
            'manufacturing_year' => [$isStore ? 'nullable' : 'prohibited', 'integer', 'min:1900', 'max:2100'],
            'expiry_date' => [$isStore ? 'nullable' : 'prohibited', 'date'],
        ];
    }
}
