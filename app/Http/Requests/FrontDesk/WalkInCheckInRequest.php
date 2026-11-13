<?php

namespace App\Http\Requests\FrontDesk;

use App\Enums\IdType;
use App\Enums\PaymentMethod;
use App\Enums\VipLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class WalkInCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkin.create') ?? false;
    }

    public function rules(): array
    {
        return [
            // Guest data
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'first_name' => ['required_without:guest_id', 'nullable', 'string', 'max:100'],
            'last_name' => ['required_without:guest_id', 'nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required_without:guest_id', 'nullable', 'string', 'max:50'],
            'id_type' => ['required_without:guest_id', 'nullable', new Enum(IdType::class)],
            'id_number' => ['required_without:guest_id', 'nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'car_plate_number' => ['nullable', 'string', 'max:50'],
            'vip_level' => ['nullable', new Enum(VipLevel::class)],
            'special_preferences' => ['nullable', 'string', 'max:500'],

            // Stay data
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'check_in_date' => ['nullable', 'date'],
            'check_out_date' => ['required', 'date', 'after:today'],
            'adults_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'keycard_number' => ['nullable', 'string', 'max:50'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'manager_override' => ['nullable', 'boolean'],
        ];
    }
}
