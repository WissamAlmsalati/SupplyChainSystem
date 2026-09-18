<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\UserRole;
use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Unique among customers; the same number may already drive for us.
            'phone_number' => ['required', 'string', 'max:20',
                Rule::unique('users', 'mobile_number')->where(
                    fn ($q) => $q->whereIn('user_type_id', UserType::where('name', UserRole::Customer->value)->select('id'))
                )],
            'email' => ['nullable', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
