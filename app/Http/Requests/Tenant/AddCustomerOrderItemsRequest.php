<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCustomerOrderItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0.01', 'max:100000000'],
            'items.*.warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'items.*.packaging_type' => ['required', 'string', 'in:paquete,unidad,bolson,bulto,gram,kg,units,package'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:100000000'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.units_per_package' => ['nullable', 'numeric', 'min:1'],
            'items.*.apply_customer_discount' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            foreach ($items as $idx => $item) {
                if (empty($item['product_id']) && empty($item['product_name'])) {
                    $validator->errors()->add("items.{$idx}", 'Debe seleccionar un producto o ingresar un nombre.');
                }
                if (empty($item['product_id']) && ! empty($item['product_name'])) {
                    $price = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.00;
                    if ($price <= 0) {
                        $validator->errors()->add("items.{$idx}.unit_price", 'El precio unitario de un producto nuevo debe ser mayor a cero.');
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debe agregar al menos un producto al pedido.',
            'items.min' => 'Debe agregar al menos un producto al pedido.',
            'items.*.unit_price.max' => 'El precio unitario no puede superar los $100.000.000.',
            'items.*.quantity.max' => 'La cantidad de producto no puede superar los 100.000.000.',
        ];
    }
}
