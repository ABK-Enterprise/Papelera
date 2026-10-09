<?php

namespace App\Http\Requests\Tenant\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockOutcomeRequest extends FormRequest
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

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'warehouse_id' => [
                'nullable',
                'integer',
                Rule::exists('warehouses', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

