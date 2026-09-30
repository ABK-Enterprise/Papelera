<?php

namespace App\Observers;

use App\Models\FiscalInvoice;
use App\Services\Audit\AuditLogService;

class FiscalInvoiceObserver
{
    public function __construct(
        protected AuditLogService $auditService
    ) {}

    /**
     * Handle the FiscalInvoice "created" event.
     */
    public function created(FiscalInvoice $invoice): void
    {
        $ptoVenta = str_pad((string) $invoice->punto_venta, 4, '0', STR_PAD_LEFT);
        $cbte = str_pad((string) $invoice->numero_comprobante, 8, '0', STR_PAD_LEFT);

        $this->auditService->recordModelCreation(
            model: $invoice,
            action: 'Emisión de factura electrónica',
            entityLabel: "Factura {$ptoVenta}-{$cbte}"
        );
    }
}
