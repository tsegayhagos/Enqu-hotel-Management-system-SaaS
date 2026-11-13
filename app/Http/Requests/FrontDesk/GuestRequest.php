<?php

namespace App\Http\Requests\FrontDesk;

use App\Enums\IdType;
use App\Enums\VipLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guest.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'id_type' => ['required', new Enum(IdType::class)],
            'id_number' => ['required', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'car_plate_number' => ['nullable', 'string', 'max:50'],
            'vip_level' => ['nullable', new Enum(VipLevel::class)],
            'is_blacklisted' => ['nullable', 'boolean'],
            'blacklist_reason' => ['nullable', 'string', 'max:1000'],
            'special_preferences' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
