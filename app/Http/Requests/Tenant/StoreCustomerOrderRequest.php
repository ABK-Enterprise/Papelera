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
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'mp_qr_checkout' => ['nullable', 'boolean'],
            'apply_customer_discount' => ['nullable', 'boolean'],
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
            'show_account_in_receipt' => ['nullable', 'boolean'],
            'show_credit_applied_in_receipt' => ['nullable', 'boolean'],
            'show_debt_collected_in_receipt' => ['nullable', 'boolean'],
            'credit_balance_applied' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'previous_debt_collected' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'regex:/^\d+(?:\.\d{1,2})?$/'],
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

            $creditApplied = (float) $this->input('credit_balance_applied', 0);
            if ($creditApplied > 0) {
                if ($this->payment_type !== 'cuenta_corriente') {
                    $validator->errors()->add(
                        'credit_balance_applied',
                        'El uso de saldo a favor solo está permitido en la condición Cuenta Corriente.'
                    );
                } elseif (empty($this->customer_id)) {
                    $validator->errors()->add(
                        'credit_balance_applied',
                        'No se puede aplicar saldo a favor a un cliente no registrado.'
                    );
                } else {
                    $customer = \App\Models\Customer::where('id', $this->customer_id)
                        ->where('tenant_id', $this->user()->tenant_id)
                        ->first();

                    if (! $customer || (float) $customer->current_balance >= 0) {
                        $validator->errors()->add(
                            'credit_balance_applied',
                            'El cliente seleccionado no posee saldo a favor en su cuenta corriente.'
                        );
                    } else {
                        $availableCredit = abs((float) $customer->current_balance);
                        if ($creditApplied > round($availableCredit, 2) + 0.01) {
                            $validator->errors()->add(
                                'credit_balance_applied',
                                'El monto imputado ($'.number_format($creditApplied, 2, ',', '.').') supera el saldo a favor disponible del cliente ($'.number_format($availableCredit, 2, ',', '.').').'
                            );
                        }
                    }
                }
            }

            $debtCollected = (float) $this->input('previous_debt_collected', 0);
            if ($debtCollected > 0) {
                if ($this->payment_type !== 'contado') {
                    $validator->errors()->add(
                        'previous_debt_collected',
                        'El cobro de deuda anterior solo está permitido en la condición Cobro Total.'
                    );
                } elseif (empty($this->customer_id)) {
                    $validator->errors()->add(
                        'previous_debt_collected',
                        'No se puede cobrar deuda anterior a un cliente no registrado.'
                    );
                } else {
                    $customer = \App\Models\Customer::where('id', $this->customer_id)
                        ->where('tenant_id', $this->user()->tenant_id)
                        ->first();

                    if (! $customer || (float) $customer->current_balance <= 0) {
                        $validator->errors()->add(
                            'previous_debt_collected',
                            'El cliente seleccionado no posee deuda pendiente en su cuenta corriente.'
                        );
                    } else {
                        $availableDebt = (float) $customer->current_balance;
                        if ($debtCollected > round($availableDebt, 2) + 0.01) {
                            $validator->errors()->add(
                                'previous_debt_collected',
                                'El monto a cobrar ($'.number_format($debtCollected, 2, ',', '.').') supera la deuda pendiente del cliente ($'.number_format($availableDebt, 2, ',', '.').').'
                            );
                        }
                    }
                }
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

            if (is_array($this->price_adjustments)) {
                $seenPresetIds = [];
                foreach ($this->price_adjustments as $idx => $adj) {
                    $calcType = $adj['calculation_type'] ?? null;
                    $val = (float) ($adj['value'] ?? 0);
                    if ($calcType === 'percentage' && $val > 100) {
                        $validator->errors()->add("price_adjustments.{$idx}.value", 'El porcentaje de ajuste no puede superar el 100%.');
                    } elseif ($calcType === 'fixed' && $val > 100000000) {
                        $validator->errors()->add("price_adjustments.{$idx}.value", 'El monto fijo de ajuste no puede superar $100.000.000.');
                    }

                    if (($adj['source'] ?? null) === 'preset' && ! empty($adj['source_id'])) {
                        $sourceId = (int) $adj['source_id'];
                        if (in_array($sourceId, $seenPresetIds, true)) {
                            $validator->errors()->add('price_adjustments', 'No se puede aplicar el mismo ajuste del catálogo más de una vez.');
                        } else {
                            $seenPresetIds[] = $sourceId;
                        }
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
            'paid_amount.max' => 'El importe abonado no puede superar los $100.000.000.',
            'credit_balance_applied.max' => 'El saldo a favor aplicado no puede superar los $100.000.000.',
            'credit_balance_applied.regex' => 'El saldo a favor debe contener únicamente dígitos numéricos positivos sin signos especiales.',
            'previous_debt_collected.max' => 'El monto de cobro de deuda no puede superar los $100.000.000.',
            'previous_debt_collected.regex' => 'El cobro de deuda previa debe contener únicamente dígitos numéricos positivos sin signos especiales.',
            'items.*.unit_price.max' => 'El precio unitario no puede superar los $100.000.000.',
            'items.*.quantity.max' => 'La cantidad de producto no puede superar los 100.000.000.',
        ];
    }
}
