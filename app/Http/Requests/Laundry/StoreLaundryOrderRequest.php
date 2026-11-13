<?php

namespace App\Http\Requests\Laundry;

use App\Enums\LaundryOrderType;
use App\Enums\LaundryPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreLaundryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('laundry.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'order_type' => ['required', new Enum(LaundryOrderType::class)],
            'is_express' => ['boolean'],
            'payment_method' => ['required', new Enum(LaundryPaymentMethod::class)],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.laundry_service_id' => ['required', 'integer', 'exists:laundry_services,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
