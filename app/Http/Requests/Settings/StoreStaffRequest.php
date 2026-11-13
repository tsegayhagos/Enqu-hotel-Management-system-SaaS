<?php

namespace App\Http\Requests\Settings;

use App\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Guarded by permission:staff.manage route middleware.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in(array_map(fn (SystemRole $r): string => $r->value, SystemRole::hotelRoles()))],
            // Only required when the e-mail does not belong to an existing account.
            'password' => ['nullable', 'string', Password::min(8)],
        ];
    }
}
