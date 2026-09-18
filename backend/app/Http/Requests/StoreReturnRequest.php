<?php

namespace App\Http\Requests;

use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:255'],
            'refund_method' => ['nullable', Rule::in(OrderReturn::REFUND_METHODS)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', Rule::in(OrderReturnItem::CONDITIONS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'order_id' => 'الطلب',
            'reason' => 'سبب الإرجاع',
            'refund_method' => 'طريقة الاسترداد',
            'items' => 'الأصناف',
            'items.*.quantity' => 'الكمية',
            'items.*.condition' => 'حالة الصنف',
        ];
    }
}
