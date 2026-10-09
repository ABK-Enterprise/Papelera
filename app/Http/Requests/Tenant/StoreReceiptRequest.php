<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => [
                'required',
                'string',
                'in:efectivo,transferencia,mercadopago,cheque,debito,credito,otro',
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.order_id' => [
                'required_with:allocations',
                'integer',
                Rule::exists('customer_orders', 'id')->where('tenant_id', $tenantId),
            ],
            'allocations.*.amount' => [
                'required_with:allocations',
                'numeric',
                'min:0.01',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'El importe a cobrar es obligatorio.',
            'amount.min' => 'El importe debe ser mayor a cero.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago seleccionado no es válido.',
            'allocations.*.order_id.exists' => 'Uno de los pedidos a imputar no pertenece a este comercio.',
            'allocations.*.amount.min' => 'El importe imputado por pedido debe ser mayor a cero.',
        ];
    }
}
