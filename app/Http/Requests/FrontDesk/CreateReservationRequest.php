<?php

namespace App\Http\Requests\FrontDesk;

use App\Enums\ReservationSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservation.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'guest_id' => ['required', 'integer', 'exists:guests,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:10'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_deposit_paid' => ['nullable', 'numeric', 'min:0'],
            'source' => ['nullable', new Enum(ReservationSource::class)],
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
