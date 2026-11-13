<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class ResolveMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'assigned_to_user_id' => ['nullable', 'integer'],
        ];
    }
}
