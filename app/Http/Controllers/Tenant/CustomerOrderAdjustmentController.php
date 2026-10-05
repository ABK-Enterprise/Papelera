<?php

namespace App\Http\Controllers\Tenant;

use App\Events\Tenant\CustomerOrderAdjustmentsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\TenantPriceAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderAdjustmentController extends Controller
{
    /**
     * Add a custom or preset price adjustment to an existing order.
     */
    public function store(Request $request, CustomerOrder $customerOrder): JsonResponse|RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            throw ValidationException::withMessages([
                'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
            ]);
        }

        if ($customerOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ["El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite nuevos ajustes."],
            ]);
        }

        $itemsToApply = [];
        if ($request->has('adjustments') && is_array($request->input('adjustments'))) {
            $validated = $request->validate([
                'adjustments' => ['required', 'array', 'min:1'],
                'adjustments.*.source' => ['nullable', 'string', 'in:preset,ad_hoc'],
                'adjustments.*.source_id' => ['nullable', 'integer'],
                'adjustments.*.type' => ['required', 'string', 'in:discount,surcharge'],
                'adjustments.*.name' => ['nullable', 'string', 'max:255'],
                'adjustments.*.calculation_type' => ['required', 'string', 'in:percentage,fixed'],
                'adjustments.*.value' => ['required', 'numeric', 'min:0.01'],
                'adjustments.*.reason' => ['nullable', 'string', 'max:500'],
                'adjustments.*.show_in_receipt' => ['nullable', 'boolean'],
            ]);

            foreach ($validated['adjustments'] as $idx => $adj) {
                if ($adj['calculation_type'] === 'percentage' && (float) $adj['value'] > 100) {
                    throw ValidationException::withMessages([
                        "adjustments.{$idx}.value" => ['El porcentaje no puede ser superior al 100%.'],
                    ]);
                }
                if ($adj['calculation_type'] === 'fixed' && (float) $adj['value'] > 100000000) {
                    throw ValidationException::withMessages([
                        "adjustments.{$idx}.value" => ['El monto fijo no puede superar $100.000.000.'],
                    ]);
                }
            }
            $itemsToApply = $validated['adjustments'];
        } else {
            $isPercentage = $request->input('calculation_type') === 'percentage';
            $validated = $request->validate([
                'source' => ['nullable', 'string', 'in:preset,ad_hoc'],
                'source_id' => ['nullable', 'integer'],
                'type' => ['required', 'string', 'in:discount,surcharge'],
                'name' => ['nullable', 'string', 'max:255'],
                'calculation_type' => ['required', 'string', 'in:percentage,fixed'],
                'value' => [
                    'required',
                    'numeric',
                    'min:0.01',
                    $isPercentage ? 'max:100' : 'max:100000000',
                ],
                'reason' => ['nullable', 'string', 'max:500'],
                'show_in_receipt' => ['nullable', 'boolean'],
            ]);
            $itemsToApply = [$validated];
        }

        $order = DB::transaction(function () use ($customerOrder, $itemsToApply, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $lockedOrder->total_amount;
            $netProductsAmount = (float) $lockedOrder->items()->sum('subtotal');
            $currentAdjustments = $lockedOrder->price_adjustments ?: [];
            $appliedNames = [];

            foreach ($itemsToApply as $item) {
                if (($item['source'] ?? null) === 'preset' && ! empty($item['source_id'])) {
                    $sourceId = (int) $item['source_id'];
                    $alreadyExists = collect($currentAdjustments)->contains(function ($adj) use ($sourceId) {
                        return ($adj['source'] ?? null) === 'preset' && (int) ($adj['source_id'] ?? 0) === $sourceId;
                    });

                    if ($alreadyExists) {
                        throw ValidationException::withMessages([
                            'adjustments' => ['El recargo o descuento comercial ya se encuentra aplicado a este pedido.'],
                        ]);
                    }
                }

                $val = (float) $item['value'];
                if ($item['calculation_type'] === 'percentage') {
                    $appliedAmount = round($netProductsAmount * ($val / 100), 2);
                } else {
                    $appliedAmount = round($val, 2);
                }

                $name = ! empty($item['name']) ? trim($item['name']) : ($item['type'] === 'discount' ? 'Descuento' : 'Recargo');
                $appliedNames[] = $name;

                $currentAdjustments[] = [
                    'id' => (string) Str::uuid(),
                    'source' => $item['source'] ?? 'ad_hoc',
                    'source_id' => $item['source_id'] ?? null,
                    'type' => $item['type'],
                    'name' => $name,
                    'calculation_type' => $item['calculation_type'],
                    'value' => $val,
                    'applied_amount' => $appliedAmount,
                    'reason' => ! empty($item['reason']) ? trim($item['reason']) : null,
                    'show_in_receipt' => (bool) ($item['show_in_receipt'] ?? false),
                ];
            }

            // Recalcular discount_total y surcharge_total
            $discountTotal = 0.00;
            $surchargeTotal = 0.00;
            foreach ($currentAdjustments as $adj) {
                if (($adj['type'] ?? '') === 'discount') {
                    $discountTotal += (float) ($adj['applied_amount'] ?? 0);
                } else {
                    $surchargeTotal += (float) ($adj['applied_amount'] ?? 0);
                }
            }

            $newTotal = max(0.00, round($netProductsAmount + (float) $lockedOrder->shipping_cost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotal - $oldTotal, 2);

            $lockedOrder->price_adjustments = $currentAdjustments;
            $lockedOrder->discount_total = round($discountTotal, 2);
            $lockedOrder->surcharge_total = round($surchargeTotal, 2);
            $lockedOrder->total_amount = $newTotal;

            if ($lockedOrder->customer_id) {
                $newDebt = max(0.00, round((float) $lockedOrder->debt_amount + $diff, 2));
                $lockedOrder->debt_amount = $newDebt;

                if (abs($diff) >= 0.01) {
                    /** @var Customer $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance + $diff, 2);

                        $descriptionSummary = count($appliedNames) > 1
                            ? 'Ajustes múltiples ('.implode(', ', array_slice($appliedNames, 0, 3)).(count($appliedNames) > 3 ? '...' : '').')'
                            : "Ajuste de precio ({$appliedNames[0]})";

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "{$descriptionSummary} en Pedido {$lockedOrder->order_number}",
                            'amount' => abs($diff),
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "ADJ-{$lockedOrder->order_number}",
                            'notes' => 'Ajuste comercial aplicado al pedido.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        $freshOrder = $order->fresh(['items.product', 'customer']);
        event(new CustomerOrderAdjustmentsUpdated($freshOrder));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Ajuste agregado exitosamente al pedido.',
                'order' => $freshOrder,
            ]);
        }

        return back()->with('success', 'Ajuste agregado exitosamente al pedido.');
    }

    /**
     * Atomically synchronize preset price adjustments for an existing order.
     */
    public function syncPresets(Request $request, CustomerOrder $customerOrder): JsonResponse|RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            throw ValidationException::withMessages([
                'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
            ]);
        }

        if ($customerOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ["El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite modificaciones."],
            ]);
        }

        $validated = $request->validate([
            'preset_ids' => ['present', 'array'],
            'preset_ids.*' => ['integer'],
        ]);

        $targetPresetIds = array_values(array_unique(array_map('intval', $validated['preset_ids'])));

        // Load active tenant price adjustments for the target IDs
        $availablePresets = [];
        if (! empty($targetPresetIds)) {
            $availablePresets = TenantPriceAdjustment::where('tenant_id', $tenantId)
                ->whereIn('id', $targetPresetIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');
        }

        $order = DB::transaction(function () use ($customerOrder, $targetPresetIds, $availablePresets, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $lockedOrder->total_amount;
            $netProductsAmount = (float) $lockedOrder->items()->sum('subtotal');
            $currentAdjustments = $lockedOrder->price_adjustments ?: [];

            // Separate ad_hoc adjustments from existing presets
            $adHocAdjustments = [];
            $existingPresetsBySourceId = [];
            foreach ($currentAdjustments as $adj) {
                if (($adj['source'] ?? null) === 'preset' && ! empty($adj['source_id'])) {
                    $sourceId = (int) $adj['source_id'];
                    if (! isset($existingPresetsBySourceId[$sourceId])) {
                        $existingPresetsBySourceId[$sourceId] = $adj;
                    }
                } else {
                    $adHocAdjustments[] = $adj;
                }
            }

            // Reconcile presets
            $syncedPresets = [];
            foreach ($targetPresetIds as $presetId) {
                if (isset($existingPresetsBySourceId[$presetId])) {
                    $existing = $existingPresetsBySourceId[$presetId];
                    if (($existing['calculation_type'] ?? '') === 'percentage') {
                        $val = (float) ($existing['value'] ?? 0);
                        $existing['applied_amount'] = round($netProductsAmount * ($val / 100), 2);
                    }
                    $syncedPresets[] = $existing;
                } elseif (isset($availablePresets[$presetId])) {
                    $preset = $availablePresets[$presetId];
                    $val = (float) $preset->value;
                    if ($preset->calculation_type === 'percentage') {
                        $appliedAmount = round($netProductsAmount * ($val / 100), 2);
                    } else {
                        $appliedAmount = round($val, 2);
                    }

                    $syncedPresets[] = [
                        'id' => (string) Str::uuid(),
                        'source' => 'preset',
                        'source_id' => $preset->id,
                        'type' => $preset->type,
                        'name' => $preset->name,
                        'calculation_type' => $preset->calculation_type,
                        'value' => $val,
                        'applied_amount' => $appliedAmount,
                        'reason' => ! empty($preset->reason) ? trim($preset->reason) : null,
                        'show_in_receipt' => true,
                    ];
                }
            }

            $finalAdjustments = array_merge($adHocAdjustments, $syncedPresets);

            // Recalculate totals
            $discountTotal = 0.00;
            $surchargeTotal = 0.00;
            foreach ($finalAdjustments as $adj) {
                if (($adj['type'] ?? '') === 'discount') {
                    $discountTotal += (float) ($adj['applied_amount'] ?? 0);
                } else {
                    $surchargeTotal += (float) ($adj['applied_amount'] ?? 0);
                }
            }

            $newTotal = max(0.00, round($netProductsAmount + (float) $lockedOrder->shipping_cost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotal - $oldTotal, 2);

            $lockedOrder->price_adjustments = array_values($finalAdjustments);
            $lockedOrder->discount_total = round($discountTotal, 2);
            $lockedOrder->surcharge_total = round($surchargeTotal, 2);
            $lockedOrder->total_amount = $newTotal;

            if ($lockedOrder->customer_id) {
                $newDebt = max(0.00, round((float) $lockedOrder->debt_amount + $diff, 2));
                $lockedOrder->debt_amount = $newDebt;

                if (abs($diff) >= 0.01) {
                    /** @var Customer $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance + $diff, 2);

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Sincronización de recargos y descuentos en Pedido {$lockedOrder->order_number}",
                            'amount' => abs($diff),
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "SYNC-ADJ-{$lockedOrder->order_number}",
                            'notes' => 'Ajuste de saldo automático por sincronización de políticas comerciales.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        $freshOrder = $order->fresh(['items.product', 'customer']);
        event(new CustomerOrderAdjustmentsUpdated($freshOrder));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Ajustes del pedido actualizados con éxito.',
                'order' => $freshOrder,
            ]);
        }

        return back()->with('success', 'Ajustes del pedido actualizados con éxito.');
    }

    /**
     * Remove an adjustment from the order.
     */
    public function destroy(Request $request, CustomerOrder $customerOrder, string $adjustmentId): JsonResponse|RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($customerOrder->tenant_id !== $tenantId) {
            abort(404);
        }

        if ($customerOrder->fiscal_status === 'facturado') {
            throw ValidationException::withMessages([
                'order' => ['El pedido posee un comprobante con CAE emitido. Debe emitirse la Nota de Crédito correspondiente antes de modificar montos.'],
            ]);
        }

        if ($customerOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'order' => ["El pedido {$customerOrder->order_number} se encuentra en estado definitivo ({$customerOrder->status}) y no admite modificaciones."],
            ]);
        }

        $order = DB::transaction(function () use ($customerOrder, $adjustmentId, $tenantId, $userId) {
            /** @var CustomerOrder $lockedOrder */
            $lockedOrder = CustomerOrder::where('id', $customerOrder->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            $oldTotal = (float) $lockedOrder->total_amount;
            $netProductsAmount = (float) $lockedOrder->items()->sum('subtotal');
            $currentAdjustments = $lockedOrder->price_adjustments ?: [];

            $foundAdj = null;
            $filteredAdjustments = [];
            foreach ($currentAdjustments as $adj) {
                if (($adj['id'] ?? null) === $adjustmentId) {
                    $foundAdj = $adj;
                } else {
                    $filteredAdjustments[] = $adj;
                }
            }

            if (! $foundAdj) {
                abort(404, 'Ajuste no encontrado en el pedido.');
            }

            $discountTotal = 0.00;
            $surchargeTotal = 0.00;
            foreach ($filteredAdjustments as $adj) {
                if (($adj['type'] ?? '') === 'discount') {
                    $discountTotal += (float) ($adj['applied_amount'] ?? 0);
                } else {
                    $surchargeTotal += (float) ($adj['applied_amount'] ?? 0);
                }
            }

            $newTotal = max(0.00, round($netProductsAmount + (float) $lockedOrder->shipping_cost + $surchargeTotal - $discountTotal, 2));
            $diff = round($newTotal - $oldTotal, 2);

            $lockedOrder->price_adjustments = array_values($filteredAdjustments);
            $lockedOrder->discount_total = round($discountTotal, 2);
            $lockedOrder->surcharge_total = round($surchargeTotal, 2);
            $lockedOrder->total_amount = $newTotal;

            if ($lockedOrder->customer_id) {
                $newDebt = max(0.00, round((float) $lockedOrder->debt_amount + $diff, 2));
                $lockedOrder->debt_amount = $newDebt;

                if (abs($diff) >= 0.01) {
                    /** @var Customer $customer */
                    $customer = Customer::where('id', $lockedOrder->customer_id)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($customer) {
                        $newBalance = round((float) $customer->current_balance + $diff, 2);

                        CustomerAccountMovement::create([
                            'tenant_id' => $tenantId,
                            'customer_id' => $customer->id,
                            'customer_order_id' => $lockedOrder->id,
                            'created_by_user_id' => $userId,
                            'type' => 'adjustment',
                            'description' => "Remoción de ajuste ({$foundAdj['name']}) en Pedido {$lockedOrder->order_number}",
                            'amount' => abs($diff),
                            'balance_after' => $newBalance,
                            'payment_method' => 'ajuste',
                            'reference_number' => "DEL-ADJ-{$lockedOrder->order_number}",
                            'notes' => 'Ajuste de saldo automático por eliminación de recargo/descuento.',
                        ]);

                        $customer->update(['current_balance' => $newBalance]);
                    }
                }
            }

            $lockedOrder->save();

            return $lockedOrder;
        });

        $freshOrder = $order->fresh(['items.product', 'customer']);
        event(new CustomerOrderAdjustmentsUpdated($freshOrder));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Ajuste eliminado con éxito.',
                'order' => $freshOrder,
            ]);
        }

        return back()->with('success', 'Ajuste eliminado con éxito.');
    }
}
