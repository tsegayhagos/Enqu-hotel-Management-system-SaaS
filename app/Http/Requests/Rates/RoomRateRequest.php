<?php

namespace App\Http\Requests\Rates;

use App\Support\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both creating and updating a seasonal / weekend rate. */
class RoomRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:rate.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->where('hotel_id', Tenant::id())],
            'rate_name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'price_per_night' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'days_of_week' => ['nullable', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['integer', 'between:1,7', 'distinct'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date cannot be before the start date.',
            'price_per_night.regex' => 'Use at most two decimals, e.g. 150.00.',
        ];
    }
}
