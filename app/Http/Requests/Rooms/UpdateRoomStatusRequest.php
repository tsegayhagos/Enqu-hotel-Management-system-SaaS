<?php

namespace App\Http\Requests\Rooms;

use App\Enums\RoomStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:room.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(array_map(fn (RoomStatus $s): string => $s->value, RoomStatus::manuallySettable())),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['status.in' => 'That status cannot be set by hand. Occupancy changes through check-in / check-out.'];
    }
}
