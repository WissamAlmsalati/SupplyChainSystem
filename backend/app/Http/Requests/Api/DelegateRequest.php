<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use App\Models\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($delegateId)],
            // Unique among delegates; the same number may also be a customer.
            'mobile_number' => ['nullable', 'string', 'max:20',
                Rule::unique('users', 'mobile_number')->ignore($delegateId)->where(
                    fn ($q) => $q->whereIn('user_type_id', UserType::where('name', UserRole::Delegate->value)->select('id'))
                )],
            'password' => [$delegateId ? 'nullable' : 'required', 'string', 'min:6'],
            'is_active' => ['boolean'],
            // Stored on delegate_profiles.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_available' => ['boolean'],
        ];
    }
}
