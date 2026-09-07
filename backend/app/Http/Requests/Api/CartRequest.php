<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:user,id'],
            'branch_id' => ['required', 'integer', 'exists:cafe_branch,id'],
            'status' => ['nullable', 'string', 'max:20'],
        ];
    }
}
