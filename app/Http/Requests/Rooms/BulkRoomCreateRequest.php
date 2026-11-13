<?php

namespace App\Http\Requests\Rooms;

use App\Services\BulkRoomCreator;
use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkRoomCreateRequest extends FormRequest
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
            'floor_number' => ['required', 'integer', 'between:-5,200'],
            'prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-_.]*$/'],
            'start_number' => ['required', 'integer', 'min:0', 'max:99999'],
            'end_number' => [
                'required', 'integer', 'min:0', 'max:99999', 'gte:start_number',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $count = (int) $value - (int) $this->input('start_number') + 1;

                    if ($count > BulkRoomCreator::MAX_ROOMS) {
                        $fail('You can create at most '.BulkRoomCreator::MAX_ROOMS.' rooms at once.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_number.gte' => 'The last room number must not be lower than the first.',
            'prefix.regex' => 'The prefix may contain letters, numbers, dashes or dots only.',
        ];
    }
}
