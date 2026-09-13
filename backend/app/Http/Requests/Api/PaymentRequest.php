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
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['required', Rule::enum(PaymentStatus::class)],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
