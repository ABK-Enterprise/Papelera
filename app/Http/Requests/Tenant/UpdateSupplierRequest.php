<?php

namespace App\Http\Requests\Tenant;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
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
        $tenantId = $this->user()->tenant_id;
        $targetSupplier = $this->route('supplier');
        $supplierId = $targetSupplier instanceof Supplier ? $targetSupplier->id : $targetSupplier;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('suppliers', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($supplierId),
            ],
            'tax_id' => [
                'nullable',
                'string',
                'max:13',
                'regex:/^(?:\d{1,11}|\d{2}-\d{8}-\d{1})$/',
                Rule::unique('suppliers', 'tax_id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNotNull('tax_id'))
                    ->ignore($supplierId),
            ],
            'contact_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'El proveedor ya se encuentra registrado en tu comercio.',
            'tax_id.unique' => 'Ya existe un proveedor con este CUIT en tu comercio.',
            'tax_id.max' => 'El CUIT no puede superar los 11 dígitos.',
            'tax_id.regex' => 'El CUIT debe contener como máximo 11 dígitos numéricos.',
        ];
    }
}
