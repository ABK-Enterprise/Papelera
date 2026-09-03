<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $warehouseId = $this->route('warehouse')?->id ?? $this->route('warehouse');

        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('warehouses', 'name')
                    ->where('tenant_id', $tenantId)
                    ->ignore($warehouseId),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')
                    ->where('tenant_id', $tenantId)
                    ->ignore($warehouseId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
        ];
    }
}
