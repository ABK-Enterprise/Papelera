<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerCreditSettingsRequest extends FormRequest
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
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'credit_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_defaulter' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'credit_limit.required' => 'El límite de crédito es obligatorio.',
            'credit_limit.min' => 'El límite de crédito no puede ser negativo.',
            'credit_limit.max' => 'El límite de crédito no puede exceder $100.000.000.',
            'credit_due_days.required' => 'Los días de vencimiento son obligatorios.',
            'credit_due_days.min' => 'Debe ser al menos 1 día de plazo.',
        ];
    }
}
