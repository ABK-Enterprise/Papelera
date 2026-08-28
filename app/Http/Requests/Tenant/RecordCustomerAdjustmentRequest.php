<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordCustomerAdjustmentRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de ajuste es obligatorio.',
            'type.in' => 'El tipo debe ser débito (cargo) o crédito (descuento).',
            'amount.required' => 'El monto del ajuste es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'description.required' => 'El motivo del ajuste es obligatorio.',
        ];
    }
}
