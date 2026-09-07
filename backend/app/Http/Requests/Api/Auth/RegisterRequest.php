<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:user,email'],
            'password' => ['required', 'string', 'min:6'],
            'mobile_number' => ['nullable', 'string', 'max:20', 'unique:user,mobile_number'],
            'cafe_id' => ['nullable', 'integer', 'exists:cafe,id'],
        ];
    }
}
