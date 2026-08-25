<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:150', 'required_without:phone_number'],
            'phone_number' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'password' => ['required', 'string'],
        ];
    }
}
