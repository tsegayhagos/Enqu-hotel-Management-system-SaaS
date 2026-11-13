<?php

namespace App\Http\Requests\Rooms;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both creating and updating a room type. */
class RoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:room.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $currentId = $this->route('roomType')?->getKey();
        $money = ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('room_types', 'name')->where('hotel_id', Tenant::id())->ignore($currentId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_occupancy' => ['required', 'integer', 'between:1,20'],
            'max_occupancy' => ['required', 'integer', 'between:1,30', 'gte:base_occupancy'],
            'base_price' => $money,
            'extra_person_fee' => $money,
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a room type with this name.',
            'max_occupancy.gte' => 'Maximum occupancy cannot be lower than base occupancy.',
            'base_price.regex' => 'Use at most two decimals, e.g. 120.50.',
            'extra_person_fee.regex' => 'Use at most two decimals, e.g. 15.00.',
        ];
    }
}
