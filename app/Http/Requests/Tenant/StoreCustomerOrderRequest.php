<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerOrderRequest extends FormRequest
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
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'shipping_zone_id' => [
                'nullable',
                Rule::exists('shipping_zones', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)),
            ],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:100'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_type' => ['nullable', 'string', 'in:contado,cuenta_corriente,parcial'],
            'payment_method' => ['nullable', 'string', 'in:efectivo,transferencia,mercadopago,cheque,debito,credito,otro'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'mp_qr_checkout' => ['nullable', 'boolean'],
            'apply_customer_discount' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'items.*.packaging_type' => ['required', 'string', 'in:paquete,unidad,bolson,bulto,gram,kg,units,package'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.units_per_package' => ['nullable', 'numeric', 'min:1'],
            'items.*.apply_customer_discount' => ['nullable', 'boolean'],
            'show_account_in_receipt' => ['nullable', 'boolean'],
            'price_adjustments' => ['nullable', 'array'],
            'price_adjustments.*.id' => ['nullable', 'string'],
            'price_adjustments.*.source' => ['nullable', 'string', 'in:preset,ad_hoc'],
            'price_adjustments.*.source_id' => ['nullable', 'integer'],
            'price_adjustments.*.type' => ['required_with:price_adjustments', 'string', 'in:discount,surcharge'],
            'price_adjustments.*.name' => ['nullable', 'string', 'max:255'],
            'price_adjustments.*.calculation_type' => ['required_with:price_adjustments', 'string', 'in:percentage,fixed'],
            'price_adjustments.*.value' => ['required_with:price_adjustments', 'numeric', 'min:0.01'],
            'price_adjustments.*.reason' => ['nullable', 'string', 'max:500'],
            'price_adjustments.*.show_in_receipt' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->customer_id) && $this->payment_type === 'cuenta_corriente') {
                $validator->errors()->add(
                    'payment_type',
                    'Los clientes ocasionales o no registrados no pueden operar con cuenta corriente. Debe seleccionarse Cobro Total o Pago Parcial.'
                );
            }

            if ($this->boolean('mp_qr_checkout')) {
                if ($this->payment_method !== 'mercadopago') {
                    $validator->errors()->add(
                        'payment_method',
                        'El cobro con QR en el momento solo está disponible para el método de pago Mercado Pago.'
                    );
                }
                if (! in_array($this->payment_type, ['contado', 'parcial'], true)) {
                    $validator->errors()->add(
                        'payment_type',
                        'El cobro con QR en el momento requiere seleccionar Cobro Total o Pago Parcial.'
                    );
                }
                if ($this->payment_type === 'parcial' && (float) $this->paid_amount <= 0) {
                    $validator->errors()->add(
                        'paid_amount',
                        'Para un pago parcial con QR debe ingresar un monto de seña o anticipo mayor a cero.'
                    );
                }
            }
        });
    }
}

