<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AppUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:app_user,email' . ($userId ? ",$userId" : '')],
            'mobile_number' => ['nullable', 'string', 'max:20', 'unique:app_user,mobile_number' . ($userId ? ",$userId" : '')],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:6'],
            'user_type_id' => ['required', 'integer', 'exists:user_type,id'],
            'cafe_id' => ['nullable', 'integer', 'exists:cafe,id'],
            'is_active' => ['boolean'],
        ];
    }
}
