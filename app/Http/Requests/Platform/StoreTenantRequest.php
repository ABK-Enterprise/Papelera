<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'tenant.name' => ['required', 'string', 'max:255'],
            'tenant.cuit_tax_id' => ['nullable', 'string', 'max:13', 'regex:/^(?:\d{1,11}|\d{2}-\d{8}-\d{1})$/'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:modules,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant.cuit_tax_id.max' => 'El CUIT no puede superar los 11 dígitos.',
            'tenant.cuit_tax_id.regex' => 'El CUIT debe contener como máximo 11 dígitos numéricos o formato válido (ej. 30-12345678-9).',
        ];
    }
}
