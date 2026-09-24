<?php

namespace App\Http\Requests\Api;

use App\Enums\PromoDestination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['nullable', 'image', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            // ponytail: link is now what its name says — a place outside the
            // platform. A destination inside the apps is named, not spelled as a
            // path every client has to take apart, so the two cannot both apply.
            'link' => ['nullable', 'url', 'max:500', 'prohibited_unless:deeplink_entity,null'],
            'deeplink_entity' => ['nullable', Rule::in(PromoDestination::values())],
            'deeplink_entity_id' => [
                Rule::requiredIf(fn () => in_array($this->input('deeplink_entity'), PromoDestination::needingId(), true)),
                // A list has nothing to point at, so an id there is a mistake.
                Rule::prohibitedIf(fn () => ! in_array($this->input('deeplink_entity'), PromoDestination::needingId(), true)),
                'integer', 'min:1',
            ],
            'show_description' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'link.url' => 'الرابط الخارجي يجب أن يكون رابطاً كاملاً يبدأ بـ http أو https',
            'link.prohibited_unless' => 'اختر إما وجهة داخل التطبيق أو رابطاً خارجياً، لا الاثنين',
            'deeplink_entity.in' => 'وجهة غير معروفة للتطبيقات',
            'deeplink_entity_id.required' => 'هذه الوجهة تحتاج تحديد العنصر',
            'deeplink_entity_id.prohibited' => 'هذه الوجهة لا تحتاج معرّفاً',
        ];
    }
}
