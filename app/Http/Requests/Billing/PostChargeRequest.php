<?php

namespace App\Http\Requests\Billing;

use App\Enums\TransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_type' => [
                'required',
                'string',
                Rule::in([
                    TransactionType::RoomCharge->value,
                    TransactionType::Tax->value,
                    TransactionType::Laundry->value,
                    TransactionType::RestaurantPos->value,
                    TransactionType::Minibar->value,
                    TransactionType::Fee->value,
                ]),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
