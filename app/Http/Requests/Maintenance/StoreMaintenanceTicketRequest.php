<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->can('maintenance.report') || $user->can('maintenance.manage'));
    }

    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'integer'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'string', 'in:plumbing,electrical,ac_hvac,furniture,appliance,structural,other'],
            'priority' => ['required', 'string', 'in:low,medium,high,emergency'],
            'blocks_room' => ['nullable', 'boolean'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'image', 'max:5120'],
        ];
    }
}
