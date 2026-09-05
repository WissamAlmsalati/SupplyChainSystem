<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CafeRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:20', 'unique:app_user,mobile_number'],
            'email' => ['nullable', 'string', 'email', 'max:150', 'unique:app_user,email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
