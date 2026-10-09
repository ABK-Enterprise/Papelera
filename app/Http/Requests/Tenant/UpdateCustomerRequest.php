<?php

namespace App\Http\Requests\Tenant;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');

        return $this->user()
            && $this->user()->tenant_id !== null
            && $customer !== null
            && $customer->tenant_id === $this->user()->tenant_id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        /** @var Customer $customer */
        $customer = $this->route('customer');
        $customerId = $customer?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($customerId),
            ],
            'tax_id' => [
                'nullable',
                'string',
                'max:13',
                'regex:/^(?:\d{1,11}|\d{2}-\d{8}-\d{1})$/',
                Rule::unique('customers', 'tax_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNotNull('tax_id'))
                    ->ignore($customerId),
            ],
            'tax_condition' => ['required', 'string', 'in:consumidor_final,responsable_inscripto,monotributo,exento'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_defaulter' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }


    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre o razón social del cliente es obligatorio.',
            'name.unique' => 'Ya existe un cliente con este nombre en tu comercio.',
            'tax_id.unique' => 'Ya existe un cliente con este CUIT / DNI en tu comercio.',
            'tax_id.max' => 'El CUIT / DNI no puede superar los 11 dígitos.',
            'tax_id.regex' => 'El CUIT / DNI debe contener como máximo 11 dígitos numéricos.',
            'credit_limit.max' => 'El límite de crédito no puede superar los $100.000.000.',
            'credit_limit.min' => 'El límite de crédito no puede ser negativo.',
            'tax_condition.required' => 'La condición fiscal es obligatoria.',
            'tax_condition.in' => 'La condición fiscal seleccionada no es válida.',
        ];
    }
}
