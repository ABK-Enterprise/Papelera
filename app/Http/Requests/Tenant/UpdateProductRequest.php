<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->tenant_id !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('net_price') && is_numeric($this->input('net_price'))) {
            $net = (float) $this->input('net_price');
            $iva = $this->has('iva_rate') && is_numeric($this->input('iva_rate')) ? (float) $this->input('iva_rate') : 21.00;
            $calculatedPrice = round($net * (1.0 + ($iva / 100.0)), 2);
            $this->merge([
                'price' => $this->input('price') !== null && is_numeric($this->input('price')) ? (float) $this->input('price') : $calculatedPrice,
            ]);
        } elseif ($this->has('price') && is_numeric($this->input('price')) && (!$this->has('net_price') || $this->input('net_price') === null || $this->input('net_price') === '')) {
            $price = (float) $this->input('price');
            $iva = $this->has('iva_rate') && is_numeric($this->input('iva_rate')) ? (float) $this->input('iva_rate') : 21.00;
            $calculatedNet = round($price / (1.0 + ($iva / 100.0)), 2);
            $this->merge([
                'net_price' => $calculatedNet,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $productId = $this->route('product') ? (is_object($this->route('product')) ? $this->route('product')->id : $this->route('product')) : null;
        $saleType = $this->input('sale_type', 'unit') ?: 'unit';

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
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
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
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'net_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stock' => ['nullable', 'numeric'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'units_per_package' => ['nullable', 'numeric', 'min:0.001'],
            'package_quantity' => ['nullable', 'numeric', 'min:0.001'],
            'bulk_discount_type' => ['nullable', 'string', 'in:inherited,custom,none'],
            'custom_bulk_discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image' => ['nullable', 'file', 'mimes:jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
            'remove_image' => ['nullable', 'boolean'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'thumbnail_url' => ['nullable', 'string', 'max:1000'],
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
            'image.mimes' => 'El formato de imagen debe ser JPEG, PNG o WebP. Los archivos vectoriales SVG no están permitidos por motivos de seguridad.',
            'image.max' => 'La imagen no debe superar los 5 MB.',
            'image.dimensions' => 'Las dimensiones de la imagen no deben superar los 4000x4000 píxeles.',
        ];
    }
}
