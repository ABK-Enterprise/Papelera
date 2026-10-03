<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderReceiptController extends Controller
{
    /**
     * Update receipt visibility settings (current account data and individual adjustments).
     */
    public function updateVisibility(Request $request, CustomerOrder $customerOrder): JsonResponse
    {
        if ($customerOrder->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }

        $validated = $request->validate([
            'show_account_in_receipt' => ['nullable', 'boolean'],
            'adjustments_visibility' => ['nullable', 'array'],
            'adjustments_visibility.*' => ['boolean'],
        ]);

        if (array_key_exists('show_account_in_receipt', $validated)) {
            $customerOrder->show_account_in_receipt = (bool) $validated['show_account_in_receipt'];
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

        return response()->json([
            'success' => true,
            'message' => 'Configuración de visibilidad en remito actualizada con éxito.',
            'order' => $customerOrder->fresh(['items.product', 'customer']),
        ]);
    }
}
