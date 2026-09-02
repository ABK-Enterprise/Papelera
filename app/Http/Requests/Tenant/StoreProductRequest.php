<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        $saleType = $this->input('sale_type', 'unit') ?: 'unit';

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('sale_type', $saleType),
            ],
            'sale_type' => ['nullable', 'string', 'in:unit,weight'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $tenantId)
                    ->where('sale_type', $saleType)
                    ->whereNotNull('sku'),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('barcode'),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'units_per_package' => ['nullable', 'numeric', 'min:0.001'],
            'package_quantity' => ['nullable', 'numeric', 'min:0.001'],
            'bulk_discount_type' => ['nullable', 'string', 'in:inherited,custom,none'],
            'custom_bulk_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Custom validation error messages in Spanish.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un producto con este nombre para la modalidad de venta seleccionada (unidad/pesaje).',
            'sku.unique' => 'El código SKU ingresado ya está asignado a otro producto en esta misma modalidad de venta.',
            'barcode.unique' => 'El código de barras ya pertenece a otro producto en tu catálogo.',
        ];
    }
}
