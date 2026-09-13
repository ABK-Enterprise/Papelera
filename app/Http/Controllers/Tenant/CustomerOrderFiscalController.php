<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Fiscal\EmitFiscalInvoiceAction;
use App\Exceptions\ArcaException;
use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Services\Arca\ArcaErrorTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerOrderFiscalController extends Controller
{
    /**
     * Emit electronic invoice via ARCA for the specified customer order.
     */
    public function emit(Request $request, CustomerOrder $order, EmitFiscalInvoiceAction $action): RedirectResponse|JsonResponse
    {
        try {
            $invoice = $action->execute($order, $request->user(), $request->all());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Factura electrónica emitida exitosamente.',
                    'fiscal_invoice' => $invoice,
                ]);
            }

            return back()->with('success', 'Factura electrónica emitida exitosamente.');
        } catch (ArcaException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['fiscal_error' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $translatedMsg = ArcaErrorTranslator::translate('SoapFault', $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $translatedMsg,
                    'error' => $translatedMsg,
                ], 422);
            }

            return back()->withErrors(['fiscal_error' => $translatedMsg])->with('error', $translatedMsg);
        }
    }
}
