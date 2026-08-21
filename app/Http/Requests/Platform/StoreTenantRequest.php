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
            'tenant.cuit_tax_id' => ['nullable', 'string', 'max:50'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin.password' => ['required', 'string', 'min:8', 'confirmed'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:modules,id'],
        ];
    }
}
