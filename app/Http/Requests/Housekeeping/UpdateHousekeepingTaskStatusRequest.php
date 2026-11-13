<?php

namespace App\Http\Requests\Housekeeping;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHousekeepingTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->can('housekeeping.perform') || $user->can('housekeeping.manage'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,in_progress,inspected,completed'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
