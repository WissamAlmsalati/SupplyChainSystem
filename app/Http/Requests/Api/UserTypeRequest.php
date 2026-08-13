<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UserTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userTypeId = $this->route('user_type')?->id;

        return [
            'name' => ['required', 'string', 'max:50', 'unique:user_type,name' . ($userTypeId ? ",$userTypeId" : '')],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permission,id'],
        ];
    }
}
