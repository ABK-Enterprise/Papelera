<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordCustomerPaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:50000000'],
            'payment_method' => ['required', 'string', 'in:efectivo,transferencia,cheque,tarjeta,otro'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'El monto del pago es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'payment_method.required' => 'Selecciona un método de pago válido.',
            'payment_method.in' => 'El método de pago no es válido.',
        ];
    }
}
