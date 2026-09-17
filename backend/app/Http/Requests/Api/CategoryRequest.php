<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:4096'],
            'parent_category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
