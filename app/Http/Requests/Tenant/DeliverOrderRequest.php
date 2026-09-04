<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class DeliverOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['required', 'string', 'in:paid_now,on_credit'],
            'payment_method' => ['required_if:payment_mode,paid_now', 'nullable', 'string', 'in:efectivo,transferencia,mercadopago,cheque,otro'],
            'amount' => ['required_if:payment_mode,paid_now', 'nullable', 'numeric', 'min:0.01'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_mode.required' => 'El modo de entrega/pago es obligatorio.',
            'payment_method.required_if' => 'El método de pago es obligatorio cuando se registra cobro.',
            'amount.required_if' => 'El monto cobrado es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a 0.',
        ];
    }
}
