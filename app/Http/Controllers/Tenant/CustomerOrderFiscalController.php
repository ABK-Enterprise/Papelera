<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Fiscal\EmitFiscalInvoiceAction;
use App\Exceptions\ArcaException;
use App\Http\Controllers\Controller;
use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Services\Arca\ArcaErrorTranslator;
use App\Services\Arca\ArcaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

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

    /**
     * Download the official ARCA fiscal invoice PDF document.
     */
    public function downloadPdf(Request $request, CustomerOrder $order, ArcaQrService $qrService)
    {
        if ($order->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        /** @var FiscalInvoice|null $invoice */
        $invoice = $order->activeFiscalInvoice()->first()
            ?? $order->fiscalInvoices()->where('status', FiscalInvoice::STATUS_APROBADO)->latest()->first();

        if (! $invoice) {
            abort(404, 'El pedido no cuenta con una factura fiscal aprobada.');
        }

        $qrBase64 = $qrService->generateForInvoice($invoice);

        $order->loadMissing(['items.product', 'customer', 'tenant']);
        $invoice->loadMissing(['associatedInvoice']);
        $tenant = $order->tenant;

        return Pdf::view('pdf.fiscal-invoice', compact('order', 'invoice', 'qrBase64', 'tenant'))
            ->format('a4')
            ->name("factura-{$invoice->formatted_number}.pdf")
            ->download();
    }

    /**
     * Preview the official ARCA fiscal invoice PDF document inline.
     */
    public function previewPdf(Request $request, CustomerOrder $order, ArcaQrService $qrService)
    {
        if ($order->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        /** @var FiscalInvoice|null $invoice */
        $invoice = $order->activeFiscalInvoice()->first()
            ?? $order->fiscalInvoices()->where('status', FiscalInvoice::STATUS_APROBADO)->latest()->first();

        if (! $invoice) {
            abort(404, 'El pedido no cuenta con una factura fiscal aprobada.');
        }

        $qrBase64 = $qrService->generateForInvoice($invoice);

        $order->loadMissing(['items.product', 'customer', 'tenant']);
        $invoice->loadMissing(['associatedInvoice']);
        $tenant = $order->tenant;

        return Pdf::view('pdf.fiscal-invoice', compact('order', 'invoice', 'qrBase64', 'tenant'))
            ->format('a4')
            ->inline("factura-{$invoice->formatted_number}.pdf");
    }
}
