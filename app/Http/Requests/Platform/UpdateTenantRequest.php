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
            'cuit_tax_id' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'module_ids' => ['nullable', 'array'],
            'module_ids.*' => ['exists:modules,id'],
        ];
    }
}
