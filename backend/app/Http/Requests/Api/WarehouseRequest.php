<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'hex_id' => ['nullable', 'string', 'max:40'],
            'resolution' => ['nullable', 'integer', 'min:0', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'hex_ids' => ['nullable', 'array'],
            'hex_ids.*' => ['string', 'max:40'],
        ];
    }
}
