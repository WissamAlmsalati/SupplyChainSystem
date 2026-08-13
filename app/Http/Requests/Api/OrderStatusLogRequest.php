<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class OrderStatusLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:order,id'],
            'status' => ['required', 'string', 'max:30'],
            'changed_by' => ['nullable', 'integer', 'exists:app_user,id'],
            'changed_at' => ['nullable', 'date'],
        ];
    }
}
