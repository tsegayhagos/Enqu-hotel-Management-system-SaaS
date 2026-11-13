<?php

namespace App\Http\Requests\Rooms;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:room.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->where('hotel_id', Tenant::id())],
            'room_number' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.\/ ]+$/',
                Rule::unique('rooms', 'room_number')->where('hotel_id', Tenant::id()),
            ],
            'floor_number' => ['required', 'integer', 'between:-5,200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'room_number.unique' => 'This room number already exists in this hotel.',
            'room_number.regex' => 'Use letters, numbers, spaces, dashes or dots only.',
            'room_type_id.exists' => 'Choose one of this hotel\'s room types.',
        ];
    }
}
