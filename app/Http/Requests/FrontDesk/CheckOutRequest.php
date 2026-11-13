<?php

namespace App\Http\Requests\FrontDesk;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('checkout.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'settle_balance' => ['nullable', 'boolean'],
            'settlement_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'confirm_keycard_returned' => ['nullable', 'boolean'],
            'manager_override' => ['nullable', 'boolean'],
        ];
    }
}
