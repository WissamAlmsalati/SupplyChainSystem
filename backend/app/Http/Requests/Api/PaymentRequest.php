<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            // A payment is recorded as received or expected. "Refunded" and
            // "failed" are outcomes the system reaches, not things to type in.
            'status' => ['required', Rule::in([PaymentStatus::Paid->value, PaymentStatus::Pending->value])],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
