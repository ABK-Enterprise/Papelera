<?php

namespace App\Http\Requests\Tenant\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
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
            'origin_warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'destination_warehouse_id' => [
                'required',
                'integer',
                'different:origin_warehouse_id',
                Rule::exists('warehouses', 'id')->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:255'],
            'allow_negative_origin' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destination_warehouse_id.different' => 'El depósito de origen y destino deben ser diferentes.',
            'quantity.min' => 'La cantidad a transferir debe ser mayor a 0.',
        ];
    }
}
