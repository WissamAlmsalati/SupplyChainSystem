<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CafeBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCafe = auth()->user()?->userType?->name === 'cafe';

        return [
            'cafe_id' => $isCafe ? ['sometimes', 'integer', 'exists:cafe,id'] : ['sometimes', 'required', 'integer', 'exists:cafe,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:200'],
            'latitude' => ['sometimes', 'required', 'numeric'],
            'longitude' => ['sometimes', 'required', 'numeric'],
            'delivery_zone_id' => ['nullable', 'integer', 'exists:delivery_zone,id'],
            'is_active' => ['boolean'],
        ];
    }
}
