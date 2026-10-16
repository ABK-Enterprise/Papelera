<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerOrderReceiptController extends Controller
{
    /**
     * Update receipt visibility settings (current account data and individual adjustments).
     */
    public function updateVisibility(Request $request, CustomerOrder $customerOrder): JsonResponse|RedirectResponse
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $validated = $request->validate([
            'show_account_in_receipt' => ['nullable', 'boolean'],
            'show_account_status_in_receipt' => ['nullable', 'boolean'],
            'show_account_balance_in_receipt' => ['nullable', 'boolean'],
            'show_current_balance_in_receipt_subtotal' => ['nullable', 'boolean'],
            'show_projected_balance_in_receipt_subtotal' => ['nullable', 'boolean'],
            'show_credit_applied_in_receipt' => ['nullable', 'boolean'],
            'show_debt_collected_in_receipt' => ['nullable', 'boolean'],
            'adjustments_visibility' => ['nullable', 'array'],
            'adjustments_visibility.*' => ['boolean'],
        ]);

        if (array_key_exists('show_account_in_receipt', $validated)) {
            $customerOrder->show_account_in_receipt = (bool) $validated['show_account_in_receipt'];
        }

        if (array_key_exists('show_account_status_in_receipt', $validated)) {
            $customerOrder->show_account_status_in_receipt = (bool) $validated['show_account_status_in_receipt'];
        }

        if (array_key_exists('show_account_balance_in_receipt', $validated)) {
            $customerOrder->show_account_balance_in_receipt = (bool) $validated['show_account_balance_in_receipt'];
        }

        if (array_key_exists('show_current_balance_in_receipt_subtotal', $validated)) {
            $customerOrder->show_current_balance_in_receipt_subtotal = (bool) $validated['show_current_balance_in_receipt_subtotal'];
        }

        if (array_key_exists('show_projected_balance_in_receipt_subtotal', $validated)) {
            $customerOrder->show_projected_balance_in_receipt_subtotal = (bool) $validated['show_projected_balance_in_receipt_subtotal'];
        }

        if (array_key_exists('show_credit_applied_in_receipt', $validated)) {
            $customerOrder->show_credit_applied_in_receipt = ((float) $customerOrder->credit_balance_applied > 0)
                ? (bool) $validated['show_credit_applied_in_receipt']
                : false;
        }

        if (array_key_exists('show_debt_collected_in_receipt', $validated)) {
            $customerOrder->show_debt_collected_in_receipt = ((float) $customerOrder->previous_debt_collected > 0)
                ? (bool) $validated['show_debt_collected_in_receipt']
                : false;
        }

        if (! empty($validated['adjustments_visibility']) && is_array($customerOrder->price_adjustments)) {
            $adjustments = $customerOrder->price_adjustments;
            foreach ($adjustments as &$adj) {
                $id = $adj['id'] ?? null;
                if ($id && array_key_exists($id, $validated['adjustments_visibility'])) {
                    $adj['show_in_receipt'] = (bool) $validated['adjustments_visibility'][$id];
                }
            }
            $customerOrder->price_adjustments = $adjustments;
        }

        $customerOrder->save();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'message' => 'Configuración de visibilidad en remito actualizada con éxito.',
                'order' => $customerOrder->fresh(['items.product', 'customer']),
            ]);
        }

        return back()->with('success', 'Configuración de visibilidad en remito actualizada con éxito.');
    }
}
