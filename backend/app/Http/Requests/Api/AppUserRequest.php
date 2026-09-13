<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'email' => ['nullable', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'mobile_number' => ['nullable', 'string', 'max:20', Rule::unique('users', 'mobile_number')->ignore($userId)],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'min:6'],
            'user_type_id' => ['required', 'integer', 'exists:user_types,id'],
            'is_active' => ['boolean'],
        ];
    }
}
