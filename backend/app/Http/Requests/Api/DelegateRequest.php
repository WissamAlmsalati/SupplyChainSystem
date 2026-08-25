<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DelegateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $delegateId = $this->route('delegate');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:app_user,email' . ($delegateId ? ",$delegateId" : '')],
            'mobile_number' => ['nullable', 'string', 'max:20', 'unique:app_user,mobile_number' . ($delegateId ? ",$delegateId" : '')],
            'password' => [$delegateId ? 'nullable' : 'required', 'string', 'min:6'],
            'cafe_id' => ['nullable', 'integer', 'exists:cafe,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_available' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
