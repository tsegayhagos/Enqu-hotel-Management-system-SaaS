<?php

namespace App\Http\Requests\Housekeeping;

use Illuminate\Foundation\Http\FormRequest;

class StoreHousekeepingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'task_type' => ['required', 'string', 'in:checkout_clean,stayover_clean,deep_clean,turndown'],
            'priority' => ['required', 'string', 'in:low,normal,urgent'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
