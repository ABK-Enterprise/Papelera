<?php

namespace App\Http\Requests\Tenant;

use App\Models\CustomerOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DeliverOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var CustomerOrder|string|int|null $orderParam */
        $orderParam = $this->route('customerOrder');
        $order = $orderParam instanceof CustomerOrder
            ? $orderParam
            : CustomerOrder::where('tenant_id', $this->user()?->tenant_id)->find($orderParam);

        $orderDebt = $order ? (float) $order->debt_amount : 0.0;
        $isOrderPaid = $orderDebt <= 0.0;
        $hasExtraAmount = $this->filled('amount') && (float) $this->input('amount') > 0;

        $rules = [
            'payment_mode' => ['required', 'string', 'in:paid_now,on_credit'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($isOrderPaid) {
            // Pedido ya pagado: amount y payment_method solo aplican si se envía cobro extra a cuenta corriente
            $rules['amount'] = ['nullable', 'numeric', 'min:0.01'];
            $rules['payment_method'] = [
                Rule::requiredIf($hasExtraAmount),
                'nullable',
                'string',
                'in:efectivo,transferencia,mercadopago,cheque,otro',
            ];
        } else {
            // Pedido pendiente de cobro
            $rules['payment_method'] = [
                Rule::requiredIf($this->input('payment_mode') === 'paid_now'),
                'nullable',
                'string',
                'in:efectivo,transferencia,mercadopago,cheque,otro',
            ];
            $rules['amount'] = [
                Rule::requiredIf($this->input('payment_mode') === 'paid_now'),
                'nullable',
                'numeric',
                'min:0.01',
                'max:50000000',
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var CustomerOrder|string|int|null $orderParam */
            $orderParam = $this->route('customerOrder');
            $order = $orderParam instanceof CustomerOrder
                ? $orderParam
                : CustomerOrder::where('tenant_id', $this->user()?->tenant_id)->find($orderParam);

            if (! $order) {
                return;
            }

            $orderDebt = (float) $order->debt_amount;
            $hasExtraAmount = $this->filled('amount') && (float) $this->input('amount') > 0;
            $hasAccount = $order->customer_id !== null;

            // Bloquear cobro extra si el pedido ya está pagado pero el cliente NO tiene cuenta corriente
            if ($orderDebt <= 0 && $hasExtraAmount && ! $hasAccount) {
                $v->errors()->add('amount', 'No se puede registrar cobro extra en un pedido ya pagado para un cliente sin cuenta corriente.');
            }

            // Validar límite máximo de saldo a favor (tope de $10.000.000)
            if ($hasAccount && $hasExtraAmount) {
                $order->loadMissing('customer');
                if ($order->customer) {
                    $extraPayment = max(0.0, (float) $this->input('amount') - max(0.0, $orderDebt));
                    $currentBalance = (float) $order->customer->current_balance;
                    $existingCredit = $currentBalance < 0 ? abs($currentBalance) : 0.0;
                    $resultingCredit = $existingCredit + $extraPayment;
                    if ($resultingCredit > 10000000.00) {
                        $v->errors()->add('amount', 'El monto ingresado excede el límite máximo permitido de saldo a favor ($10.000.000,00).');
                    }
                }
            }

            // Validar entrega a crédito solo si el cliente tiene cuenta habilitada
            if ($orderDebt > 0 && $this->input('payment_mode') === 'on_credit') {
                $order->loadMissing('customer');
                if (! $order->customer) {
                    $v->errors()->add('payment_mode', 'El cliente no tiene cuenta corriente habilitada para entregas a crédito.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'payment_mode.required' => 'El modo de entrega/pago es obligatorio.',
            'payment_method.required' => 'El método de pago es obligatorio cuando se registra cobro.',
            'payment_method.required_if' => 'El método de pago es obligatorio cuando se registra cobro.',
            'amount.required' => 'El monto cobrado es obligatorio.',
            'amount.required_if' => 'El monto cobrado es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a 0.',
        ];
    }
}
