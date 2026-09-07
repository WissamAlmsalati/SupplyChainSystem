<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CafeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_info' => ['nullable', 'string', 'max:200'],
            'image' => ['nullable', 'image', 'max:2048'],
            'created_by_admin_id' => ['nullable', 'integer', 'exists:user,id'],
            'is_active' => ['boolean'],
        ];
    }
}
