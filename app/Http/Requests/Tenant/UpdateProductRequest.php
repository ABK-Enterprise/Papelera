<?php

namespace App\Http\Requests\Tenant;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $this->user()
            && $this->user()->tenant_id !== null
            && $product !== null
            && $product->tenant_id === $this->user()->tenant_id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        /** @var Product|int $product */
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        $currentSaleType = is_object($product) ? $product->sale_type : 'unit';
        $saleType = $this->input('sale_type', $currentSaleType);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->where('tenant_id', $tenantId)
                    ->where('sale_type', $saleType)
                    ->ignore($productId),
            ],
            'sale_type' => ['nullable', 'string', 'in:unit,weight'],
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where('tenant_id', $tenantId)
                    ->where('sale_type', $saleType)
                    ->whereNotNull('sku')
                    ->ignore($productId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('barcode')
                    ->ignore($productId),
            ],

            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe otro producto con este nombre y modalidad de venta en tu catálogo.',
            'sku.unique' => 'El código SKU ingresado ya está asignado a otro producto con esta modalidad de venta.',
            'barcode.unique' => 'El código de barras ingresado ya está asignado a otro producto.',
        ];
    }
}

