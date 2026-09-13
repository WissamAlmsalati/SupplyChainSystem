<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RecurringCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStore = $this->isMethod('post');

        return [
            'name' => [$isStore ? 'required' : 'sometimes', 'string', 'max:100'],
            'items' => [$isStore ? 'required' : 'sometimes', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
