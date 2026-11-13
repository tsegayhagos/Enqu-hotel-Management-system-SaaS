<?php

namespace App\Http\Requests\Laundry;

use App\Enums\LaundryOrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateLaundryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('laundry.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(LaundryOrderStatus::class)],
        ];
    }
}
