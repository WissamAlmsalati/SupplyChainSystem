<?php

namespace App\Http\Requests\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Dashboard orders. Creation goes through OrderPlacementService (server-side
// prices and stock); updates only change workflow fields.
class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'address_id' => ['required', 'integer', 'exists:addresses,id'],
                'delegate_id' => ['nullable', 'integer', 'exists:users,id'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'payment_method' => ['nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Wallet->value])],
                // What the cafe wants the office and the driver to know ("اتركه عند الباب الخلفي").
                'note' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'delegate_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
