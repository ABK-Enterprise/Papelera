<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cuit_tax_id' => ['nullable', 'string', 'max:13', 'regex:/^(?:\d{1,11}|\d{2}-\d{8}-\d{1})$/'],
            'is_active' => ['boolean'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:modules,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'cuit_tax_id.max' => 'El CUIT no puede superar los 11 dígitos.',
            'cuit_tax_id.regex' => 'El CUIT debe contener como máximo 11 dígitos numéricos o formato válido (ej. 30-12345678-9).',
        ];
    }
}
