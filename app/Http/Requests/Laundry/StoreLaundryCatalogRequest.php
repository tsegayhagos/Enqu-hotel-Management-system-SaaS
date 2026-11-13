<?php

namespace App\Http\Requests\Laundry;

use App\Enums\LaundryCategory;
use App\Enums\LaundryServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreLaundryCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('laundry.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:100'],
            'category' => ['required', new Enum(LaundryCategory::class)],
            'service_type' => ['required', new Enum(LaundryServiceType::class)],
            'standard_price' => ['required', 'numeric', 'min:0'],
            'express_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
