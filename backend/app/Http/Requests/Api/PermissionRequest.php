<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:permission,code' . ($permissionId ? ",$permissionId" : '')],
        ];
    }
}
