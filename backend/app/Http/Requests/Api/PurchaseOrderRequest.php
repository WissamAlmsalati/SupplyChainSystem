<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:supplier,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouse,id'],
            'order_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:20'],
        ];
    }
}
