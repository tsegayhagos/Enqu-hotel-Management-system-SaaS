<?php

namespace App\Http\Requests\Settings;

use App\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:staff.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(array_map(fn (SystemRole $r): string => $r->value, SystemRole::hotelRoles()))],
        ];
    }
}
