<?php

namespace App\Http\Requests\Housekeeping;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignHousekeepingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer'],
            'assigned_to_user_id' => ['nullable', 'integer'],
        ];
    }
}
