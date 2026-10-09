<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CollectCustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->tenant_id !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:efectivo,transferencia,mercadopago,cheque,debito,credito,otro'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'mark_as_delivered' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Debes seleccionar un método de pago.',
            'payment_method.in' => 'El método de pago seleccionado es inválido.',
            'amount.required' => 'El importe a cobrar es obligatorio.',
            'amount.numeric' => 'El importe debe ser un número válido.',
            'amount.min' => 'El importe a cobrar debe ser mayor a cero.',
            'amount.max' => 'El importe a cobrar no puede superar los $100.000.000.',
        ];
    }
}
