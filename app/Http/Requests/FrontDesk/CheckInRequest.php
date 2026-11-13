<?php

namespace App\Http\Requests\FrontDesk;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkin.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer', 'exists:reservations,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'keycard_number' => ['nullable', 'string', 'max:50'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'manager_override' => ['nullable', 'boolean'],
        ];
    }
}
