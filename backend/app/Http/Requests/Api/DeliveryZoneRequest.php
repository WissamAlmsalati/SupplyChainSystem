<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'hex_id' => ['required', 'string', 'max:40', Rule::unique('delivery_zones', 'hex_id')->ignore($this->route('delivery_zone')?->id)],
            'name' => ['nullable', 'string', 'max:100'],
            'delivery_price' => ['required', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
        ];
    }
}
