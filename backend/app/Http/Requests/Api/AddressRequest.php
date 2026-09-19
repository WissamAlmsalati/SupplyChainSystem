<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCustomer = auth()->user()?->userType?->name === UserRole::Customer->value;
        $isStore = $this->isMethod('post');

        return [
            'user_id' => $isCustomer ? ['prohibited'] : [$isStore ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'name' => [$isStore ? 'required' : 'sometimes', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:200'],
            'latitude' => [$isStore ? 'required' : 'sometimes', 'numeric', 'between:-90,90'],
            'longitude' => [$isStore ? 'required' : 'sometimes', 'numeric', 'between:-180,180'],
            // Customers never choose the zone: the server finds it from the coordinates.
            'delivery_zone_id' => $isCustomer ? ['sometimes'] : ['nullable', 'integer', 'exists:delivery_zones,id'],
            'contact_phones' => ['nullable', 'array'],
            'contact_phones.*' => ['string', 'max:20'],
        ];
    }
}
