<?php

namespace App\Http\Requests\Rooms;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:room.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roomId = $this->route('room')?->getKey();

        return [
            'room_type_id' => ['sometimes', 'integer', Rule::exists('room_types', 'id')->where('hotel_id', Tenant::id())],
            'room_number' => [
                'sometimes', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_.\/ ]+$/',
                Rule::unique('rooms', 'room_number')->where('hotel_id', Tenant::id())->ignore($roomId),
            ],
            'floor_number' => ['sometimes', 'integer', 'between:-5,200'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'room_number.unique' => 'This room number already exists in this hotel.',
            'room_number.regex' => 'Use letters, numbers, spaces, dashes or dots only.',
        ];
    }
}
