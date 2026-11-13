<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,emergency'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
