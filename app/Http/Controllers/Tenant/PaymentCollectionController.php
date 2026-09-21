<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Tenant\Payments\RegisterReceiptAction;
use App\Actions\Tenant\Payments\VoidReceiptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreReceiptRequest;
use App\Http\Requests\Tenant\VoidReceiptRequest;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\Receipt;
use App\Services\Arca\ArcaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class PaymentCollectionController extends Controller
{
    /**
     * Panel de Cobranzas Tripartito:
     * - Cuentas por Cobrar (Clientes con deuda / impagos)
     * - Pedidos Impagos del Cliente Seleccionado (Wide-cards con checkboxes e imputación FIFO)
     * - Cobros Realizados Recientes
     */
    public function index(Request $request): Response|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $search = $request->query('search');
        $selectedCustomerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        // Clientes con saldo deudor o con pedidos pendientes de pago
        $customersQuery = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true);

        if ($search) {
            $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
            $customersQuery->where(function ($q) use ($searchTerm, $search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                    ->orWhere('tax_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        } else {
            // Mostrar prioritariamente clientes con balance > 0 o pedidos impagos
            $customersQuery->where(function ($q) {
                $q->where('current_balance', '>', 0)
                    ->orWhereHas('orders', function ($oq) {
                        $oq->where('debt_amount', '>', 0)
                            ->where('status', '!=', 'cancelado');
                    });
            });
        }

        $customers = $customersQuery
            ->withCount([
                'orders as unpaid_orders_count' => function ($q) {
                    $q->where('debt_amount', '>', 0)->where('status', '!=', 'cancelado');
                },
            ])
            ->withSum([
                'orders as total_unpaid_debt' => function ($q) {
                    $q->where('debt_amount', '>', 0)->where('status', '!=', 'cancelado');
                },
            ], 'debt_amount')
            ->orderByDesc('current_balance')
            ->orderBy('name')
            ->take(50)
            ->get();

        $selectedCustomer = null;
        $unpaidOrders = [];
        $recentReceipts = [];

        if ($selectedCustomerId) {
            $selectedCustomer = Customer::where('tenant_id', $tenantId)
                ->where('id', $selectedCustomerId)
                ->first();

            if ($selectedCustomer) {
                // Pedidos impagos ordenados de más antiguo a más nuevo (FIFO)
                $unpaidOrders = CustomerOrder::where('tenant_id', $tenantId)
                    ->where('customer_id', $selectedCustomerId)
                    ->where('debt_amount', '>', 0)
                    ->where('status', '!=', 'cancelado')
                    ->with('shippingZone:id,name,surcharge_percentage')
                    ->orderBy('created_at', 'asc')
                    ->get();

                $recentReceipts = Receipt::where('tenant_id', $tenantId)
                    ->where('customer_id', $selectedCustomerId)
                    ->with(['createdByUser:id,name', 'orderAllocations.order:id,order_number,total_amount'])
                    ->latest()
                    ->take(15)
                    ->get();
            }
        } else {
            // Recibos generales más recientes del comercio
            $recentReceipts = Receipt::where('tenant_id', $tenantId)
                ->with(['customer:id,name', 'createdByUser:id,name', 'orderAllocations.order:id,order_number'])
                ->latest()
                ->take(10)
                ->get();
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'customers' => $customers,
                'selected_customer' => $selectedCustomer,
                'unpaid_orders' => $unpaidOrders,
                'recent_receipts' => $recentReceipts,
            ]);
        }

        return Inertia::render('tenant/payments/Index', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'unpaidOrders' => $unpaidOrders,
            'recentReceipts' => $recentReceipts,
            'filters' => [
                'search' => $search,
                'customer_id' => $selectedCustomerId,
            ],
        ]);
    }

    /**
     * Registra un nuevo recibo de cobro con imputaciones
     */
    public function store(StoreReceiptRequest $request, RegisterReceiptAction $action): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $receipt = $action->execute(
            tenantId: $tenantId,
            userId: $userId,
            customerId: $request->validated('customer_id'),
            amount: (float) $request->validated('amount'),
            paymentMethod: $request->validated('payment_method'),
            referenceNumber: $request->validated('reference_number'),
            notes: $request->validated('notes'),
            orderAllocations: $request->validated('allocations', [])
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => "Recibo {$receipt->receipt_number} emitido exitosamente.",
                'receipt' => $receipt,
            ], 201);
        }

        return back()->with('success', "Recibo {$receipt->receipt_number} registrado correctamente.");
    }

    /**
     * Vista de Auditoría e Historial de Recibos
     */
    public function receipts(Request $request): Response|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $search = $request->query('search');
        $paymentMethod = $request->query('payment_method');
        $status = $request->query('status', 'all');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = Receipt::where('tenant_id', $tenantId)
            ->with([
                'customer:id,name,tax_id',
                'createdByUser:id,name',
                'cancelledByUser:id,name',
                'orders:id,order_number,total_amount,debt_amount',
            ]);

        if ($search) {
            $searchTerm = '%'.mb_strtolower(trim((string) $search), 'UTF-8').'%';
            $query->where(function ($q) use ($searchTerm, $search) {
                $q->whereRaw('LOWER(receipt_number) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(reference_number) LIKE ?', [$searchTerm])
                    ->orWhereHas('customer', function ($cq) use ($searchTerm, $search) {
                        $cq->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                            ->orWhere('tax_id', 'like', "%{$search}%");
                    });
            });
        }

        if ($paymentMethod && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        if ($status === 'valid') {
            $query->where('is_cancelled', false);
        } elseif ($status === 'cancelled') {
            $query->where('is_cancelled', true);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $receipts = $query->latest()->paginate(20)->withQueryString();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($receipts);
        }

        return Inertia::render('tenant/payments/Receipts', [
            'receipts' => $receipts,
            'filters' => [
                'search' => $search,
                'payment_method' => $paymentMethod ?: 'all',
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Anulación administrativa de un recibo con contra-asiento en libro mayor
     */
    public function void(VoidReceiptRequest $request, Receipt $receipt, VoidReceiptAction $action): RedirectResponse|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        if ($receipt->tenant_id !== $tenantId) {
            abort(403, 'No tienes permiso para anular este comprobante.');
        }

        $voidedReceipt = $action->execute(
            tenantId: $tenantId,
            receiptId: $receipt->id,
            userId: $userId,
            reason: $request->validated('reason')
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => "Recibo {$voidedReceipt->receipt_number} anulado exitosamente.",
                'receipt' => $voidedReceipt,
            ]);
        }

        return back()->with('success', "Recibo {$voidedReceipt->receipt_number} anulado correctamente.");
    }

    /**
     * Download the receipt PDF document.
     */
    public function downloadPdf(Request $request, Receipt $receipt)
    {
        if ($receipt->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $receipt->load([
            'tenant',
            'customer',
            'createdByUser:id,name',
            'cancelledByUser:id,name',
            'orderAllocations.order:id,order_number,total_amount,debt_amount',
        ]);

        return Pdf::view('pdf.receipt', [
            'receipt' => $receipt,
        ])
            ->format('a4')
            ->meta(
                title: "Recibo de Cobro - {$receipt->receipt_number}",
                author: $receipt->tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->name("recibo-{$receipt->receipt_number}.pdf")
            ->download();
    }

    /**
     * Preview the receipt PDF document inline.
     */
    public function previewPdf(Request $request, Receipt $receipt)
    {
        if ($receipt->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $receipt->load([
            'tenant',
            'customer',
            'createdByUser:id,name',
            'cancelledByUser:id,name',
            'orderAllocations.order:id,order_number,total_amount,debt_amount',
        ]);

        return Pdf::view('pdf.receipt', [
            'receipt' => $receipt,
        ])
            ->format('a4')
            ->meta(
                title: "Recibo de Cobro - {$receipt->receipt_number}",
                author: $receipt->tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->inline("recibo-{$receipt->receipt_number}.pdf");
    }

    /**
     * Display the payment receipt in an HTML viewer shell with brand metadata and favicon.
     */
    public function viewer(Request $request, Receipt $receipt)
    {
        if ($receipt->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        return view('pdf.viewer', [
            'title' => "Recibo de Cobro - {$receipt->receipt_number}",
            'streamUrl' => route('tenant.payments.receipts.preview-pdf', $receipt),
        ]);
    }

    /**
     * Historial de Facturas Electrónicas y Notas de Crédito de ARCA.
     */
    public function invoices(Request $request): Response|JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $search = $request->query('search');
        $cbteTipo = $request->query('cbte_tipo');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $sortBy = $request->query('sort_by', 'latest');

        $query = FiscalInvoice::where('tenant_id', $tenantId)
            ->with([
                'customerOrder.customer',
                'customerOrder.items.product',
                'createdByUser:id,name',
                'associatedInvoice',
            ]);

        // Búsqueda por receptor, documento, CAE, número de comprobante o número de pedido
        if ($search) {
            $term = '%' . mb_strtolower(trim((string) $search), 'UTF-8') . '%';
            $query->where(function ($q) use ($term, $search) {
                $q->whereRaw('LOWER(receptor_name) LIKE ?', [$term])
                    ->orWhere('doc_nro', 'like', "%{$search}%")
                    ->orWhere('cae', 'like', "%{$search}%")
                    ->orWhere('numero_comprobante', 'like', "%{$search}%")
                    ->orWhereHas('customerOrder', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        // Filtro por Tipo de Comprobante
        if ($cbteTipo && $cbteTipo !== 'all') {
            $query->where('cbte_tipo', (int) $cbteTipo);
        }

        // Rango de fechas
        if ($dateFrom) {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        // Ordenamiento
        match ($sortBy) {
            'oldest' => $query->orderBy('issue_date', 'asc')->orderBy('id', 'asc'),
            'amount_desc' => $query->orderByDesc('importe_total')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('importe_total', 'asc')->orderBy('id', 'asc'),
            default => $query->orderBy('issue_date', 'desc')->orderBy('id', 'desc'),
        };

        $invoices = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($invoices);
        }

        return Inertia::render('tenant/payments/Invoices', [
            'invoices' => $invoices,
            'filters' => [
                'search' => $search,
                'cbte_tipo' => $cbteTipo ?: 'all',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort_by' => $sortBy,
            ],
        ]);
    }

    /**
     * Download the ARCA fiscal invoice PDF document directly from invoice.
     */
    public function downloadInvoicePdf(Request $request, FiscalInvoice $fiscalInvoice, ArcaQrService $qrService)
    {
        if ($fiscalInvoice->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $order = $fiscalInvoice->customerOrder;
        if (! $order) {
            abort(404, 'Pedido asociado a la factura no encontrado.');
        }

        $qrBase64 = $qrService->generateForInvoice($fiscalInvoice);

        $order->loadMissing(['items.product', 'customer', 'tenant']);
        $fiscalInvoice->loadMissing(['associatedInvoice']);
        $tenant = $order->tenant;
        $invoice = $fiscalInvoice;

        return Pdf::view('pdf.fiscal-invoice', compact('order', 'invoice', 'qrBase64', 'tenant'))
            ->format('a4')
            ->meta(
                title: ($invoice->isCreditNote() ? 'Nota de Crédito' : 'Factura') . " - {$invoice->formatted_number}",
                author: $tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->name("factura-{$invoice->formatted_number}.pdf")
            ->download();
    }

    /**
     * Preview the ARCA fiscal invoice PDF document inline directly from invoice.
     */
    public function previewInvoicePdf(Request $request, FiscalInvoice $fiscalInvoice, ArcaQrService $qrService)
    {
        if ($fiscalInvoice->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $order = $fiscalInvoice->customerOrder;
        if (! $order) {
            abort(404, 'Pedido asociado a la factura no encontrado.');
        }

        $qrBase64 = $qrService->generateForInvoice($fiscalInvoice);

        $order->loadMissing(['items.product', 'customer', 'tenant']);
        $fiscalInvoice->loadMissing(['associatedInvoice']);
        $tenant = $order->tenant;
        $invoice = $fiscalInvoice;

        return Pdf::view('pdf.fiscal-invoice', compact('order', 'invoice', 'qrBase64', 'tenant'))
            ->format('a4')
            ->meta(
                title: ($invoice->isCreditNote() ? 'Nota de Crédito' : 'Factura') . " - {$invoice->formatted_number}",
                author: $tenant?->name ?? 'Papelera',
                creator: config('app.name', 'Papelera'),
            )
            ->inline("factura-{$invoice->formatted_number}.pdf");
    }

    /**
     * Display the ARCA fiscal invoice in an HTML viewer shell with brand metadata and favicon.
     */
    public function invoiceViewer(Request $request, FiscalInvoice $fiscalInvoice)
    {
        if ($fiscalInvoice->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        return view('pdf.viewer', [
            'title' => ($fiscalInvoice->isCreditNote() ? 'Nota de Crédito' : 'Factura') . " - {$fiscalInvoice->formatted_number}",
            'streamUrl' => route('tenant.payments.invoices.preview-pdf', $fiscalInvoice),
        ]);
    }
}
