<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\UserRole;
use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            // This route always creates a customer, so scope it to that type.
            'mobile_number' => ['nullable', 'string', 'max:20',
                Rule::unique('users', 'mobile_number')->where(
                    fn ($q) => $q->whereIn('user_type_id', UserType::where('name', UserRole::Customer->value)->select('id'))
                )],
        ];
    }
}
