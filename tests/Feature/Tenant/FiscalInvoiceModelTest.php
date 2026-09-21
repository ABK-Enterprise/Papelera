<?php

use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->user = User::factory()->forTenant($this->tenant)->create();
});

test('it can create a fiscal invoice with valid fields and casts', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'total_amount' => 1210.00,
    ]);

    $invoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 45,
        'cae' => '74123456789012',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final Anonimo',
        'receptor_address' => 'Av. Siempre Viva 123',
        'concepto' => 1,
        'importe_total' => 1210.00,
        'importe_neto' => 1000.00,
        'importe_iva' => 210.00,
        'importe_exento' => 0.00,
        'importe_tributos' => 0.00,
        'qr_data' => 'https://www.afip.gob.ar/fe/qr/?p=base64json',
        'arca_request_payload' => ['FECAESolicitar' => ['FeCAEReq' => ['FeCabReq' => ['CantReg' => 1]]]],
        'arca_response_payload' => ['FECAESolicitarResult' => ['FeDetResp' => ['FECAEDetResponse' => [['Resultado' => 'A']]]]],
        'status' => FiscalInvoice::STATUS_APROBADO,
        'notes' => 'Factura B emitida con éxito',
    ]);

    expect($invoice->id)->not->toBeNull()
        ->and($invoice->cbte_tipo)->toBe(6)
        ->and($invoice->punto_venta)->toBe(1)
        ->and($invoice->numero_comprobante)->toBe(45)
        ->and($invoice->importe_total)->toBe('1210.00')
        ->and($invoice->importe_neto)->toBe('1000.00')
        ->and($invoice->importe_iva)->toBe('210.00')
        ->and($invoice->arca_request_payload)->toBeArray()
        ->and($invoice->arca_request_payload['FECAESolicitar']['FeCAEReq']['FeCabReq']['CantReg'])->toBe(1)
        ->and($invoice->arca_response_payload)->toBeArray()
        ->and($invoice->status)->toBe('aprobado')
        ->and($invoice->cae_due_date->format('Y-m-d'))->toBe('2026-09-23')
        ->and($invoice->issue_date->format('Y-m-d'))->toBe('2026-09-13');
});

test('it verifies relationships between customer order, user, and fiscal invoices', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create();

    $invoice = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 1,
        'numero_comprobante' => 101,
        'cae' => '74123456789012',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 100.00,
        'importe_neto' => 100.00,
        'importe_iva' => 0.00,
        'qr_data' => 'https://afip.gob.ar',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    $creditNote = FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_NOTA_CREDITO_B,
        'punto_venta' => 1,
        'numero_comprobante' => 1,
        'cae' => '74123456789099',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 100.00,
        'importe_neto' => 100.00,
        'importe_iva' => 0.00,
        'associated_fiscal_invoice_id' => $invoice->id,
        'qr_data' => 'https://afip.gob.ar',
        'status' => FiscalInvoice::STATUS_APROBADO,
    ]);

    // BelongsTo relationships
    expect($invoice->customerOrder->id)->toBe($order->id)
        ->and($invoice->createdByUser->id)->toBe($this->user->id)
        ->and($creditNote->associatedInvoice->id)->toBe($invoice->id);

    // HasMany credit notes
    expect($invoice->creditNotes)->toHaveCount(1)
        ->and($invoice->creditNotes->first()->id)->toBe($creditNote->id);

    // CustomerOrder relations
    expect($order->fiscalInvoices)->toHaveCount(2)
        ->and($order->activeFiscalInvoice->id)->toBe($invoice->id);
});

test('it enforces unique constraint on tenant, cbte_tipo, punto_venta, and numero_comprobante', function () {
    $order1 = CustomerOrder::factory()->forTenant($this->tenant)->create();
    $order2 = CustomerOrder::factory()->forTenant($this->tenant)->create();

    FiscalInvoice::create([
        'tenant_id' => $this->tenant->id,
        'customer_order_id' => $order1->id,
        'created_by_user_id' => $this->user->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 2,
        'numero_comprobante' => 500,
        'cae' => '74123456789001',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 200.00,
        'importe_neto' => 200.00,
        'qr_data' => 'qr',
        'status' => 'aprobado',
    ]);

    expect(function () use ($order2) {
        FiscalInvoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_order_id' => $order2->id,
            'created_by_user_id' => $this->user->id,
            'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
            'punto_venta' => 2,
            'numero_comprobante' => 500, // Same comprobante number, pto venta, cbte tipo for tenant
            'cae' => '74123456789002',
            'cae_due_date' => '2026-09-23',
            'issue_date' => '2026-09-13',
            'doc_tipo' => 99,
            'doc_nro' => '0',
            'receptor_condicion_iva_id' => 5,
            'receptor_name' => 'Consumidor Final',
            'concepto' => 1,
            'importe_total' => 200.00,
            'importe_neto' => 200.00,
            'qr_data' => 'qr',
            'status' => 'aprobado',
        ]);
    })->toThrow(QueryException::class);

    // But same number on another tenant is allowed
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->forTenant($otherTenant)->create();
    $otherOrder = CustomerOrder::factory()->forTenant($otherTenant)->create();

    $otherInvoice = FiscalInvoice::create([
        'tenant_id' => $otherTenant->id,
        'customer_order_id' => $otherOrder->id,
        'created_by_user_id' => $otherUser->id,
        'cbte_tipo' => FiscalInvoice::CBTE_TIPO_FACTURA_B,
        'punto_venta' => 2,
        'numero_comprobante' => 500,
        'cae' => '74123456789003',
        'cae_due_date' => '2026-09-23',
        'issue_date' => '2026-09-13',
        'doc_tipo' => 99,
        'doc_nro' => '0',
        'receptor_condicion_iva_id' => 5,
        'receptor_name' => 'Consumidor Final',
        'concepto' => 1,
        'importe_total' => 200.00,
        'importe_neto' => 200.00,
        'qr_data' => 'qr',
        'status' => 'aprobado',
    ]);

    expect($otherInvoice->id)->not->toBeNull();
});

test('it handles comprobante helpers, letters, and formatted numbers correctly', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create();

    $facturaA = new FiscalInvoice(['cbte_tipo' => 1, 'punto_venta' => 4, 'numero_comprobante' => 12]);
    $notaCreditoA = new FiscalInvoice(['cbte_tipo' => 3, 'punto_venta' => 4, 'numero_comprobante' => 1]);
    $facturaB = new FiscalInvoice(['cbte_tipo' => 6, 'punto_venta' => 1, 'numero_comprobante' => 89]);
    $notaCreditoB = new FiscalInvoice(['cbte_tipo' => 8, 'punto_venta' => 1, 'numero_comprobante' => 5]);
    $facturaC = new FiscalInvoice(['cbte_tipo' => 11, 'punto_venta' => 2, 'numero_comprobante' => 345]);
    $notaCreditoC = new FiscalInvoice(['cbte_tipo' => 13, 'punto_venta' => 2, 'numero_comprobante' => 12]);

    expect($facturaA->isInvoice())->toBeTrue()
        ->and($facturaA->isCreditNote())->toBeFalse()
        ->and($facturaA->cbte_letter)->toBe('A')
        ->and($facturaA->formatted_number)->toBe('A 0004-00000012');

    expect($notaCreditoA->isInvoice())->toBeFalse()
        ->and($notaCreditoA->isCreditNote())->toBeTrue()
        ->and($notaCreditoA->cbte_letter)->toBe('A')
        ->and($notaCreditoA->formatted_number)->toBe('A 0004-00000001');

    expect($facturaB->isInvoice())->toBeTrue()
        ->and($facturaB->isCreditNote())->toBeFalse()
        ->and($facturaB->cbte_letter)->toBe('B')
        ->and($facturaB->formatted_number)->toBe('B 0001-00000089');

    expect($notaCreditoB->isInvoice())->toBeFalse()
        ->and($notaCreditoB->isCreditNote())->toBeTrue()
        ->and($notaCreditoB->cbte_letter)->toBe('B')
        ->and($notaCreditoB->formatted_number)->toBe('B 0001-00000005');

    expect($facturaC->isInvoice())->toBeTrue()
        ->and($facturaC->isCreditNote())->toBeFalse()
        ->and($facturaC->cbte_letter)->toBe('C')
        ->and($facturaC->formatted_number)->toBe('C 0002-00000345');

    expect($notaCreditoC->isInvoice())->toBeFalse()
        ->and($notaCreditoC->isCreditNote())->toBeTrue()
        ->and($notaCreditoC->cbte_letter)->toBe('C')
        ->and($notaCreditoC->formatted_number)->toBe('C 0002-00000012');
});

test('it verifies fiscal FSM and helper methods on CustomerOrder', function () {
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
    ]);

    expect($order->fiscal_status)->toBe('sin_facturar')
        ->and($order->fiscal_error_message)->toBeNull()
        ->and($order->isFiscalInvoiced())->toBeFalse()
        ->and($order->canBeFiscalInvoiced())->toBeTrue();

    // From sin_facturar transitions
    expect($order->canTransitionFiscalTo('facturado'))->toBeTrue()
        ->and($order->canTransitionFiscalTo('error_fiscal'))->toBeTrue()
        ->and($order->canTransitionFiscalTo('anulado_fiscal'))->toBeFalse()
        ->and($order->canTransitionFiscalTo('sin_facturar'))->toBeFalse();

    // Transition to error_fiscal
    $order->fiscal_status = 'error_fiscal';
    $order->fiscal_error_message = 'Error en validación ARCA: CUIT inválido';
    $order->save();

    expect($order->isFiscalInvoiced())->toBeFalse()
        ->and($order->canBeFiscalInvoiced())->toBeTrue()
        ->and($order->canTransitionFiscalTo('facturado'))->toBeTrue()
        ->and($order->canTransitionFiscalTo('sin_facturar'))->toBeTrue()
        ->and($order->canTransitionFiscalTo('anulado_fiscal'))->toBeFalse();

    // Transition to facturado
    $order->fiscal_status = 'facturado';
    $order->fiscal_error_message = null;
    $order->save();

    expect($order->isFiscalInvoiced())->toBeTrue()
        ->and($order->canBeFiscalInvoiced())->toBeFalse()
        ->and($order->canTransitionFiscalTo('anulado_fiscal'))->toBeTrue()
        ->and($order->canTransitionFiscalTo('sin_facturar'))->toBeFalse()
        ->and($order->canTransitionFiscalTo('error_fiscal'))->toBeFalse();

    // Transition to anulado_fiscal (terminal)
    $order->fiscal_status = 'anulado_fiscal';
    $order->save();

    expect($order->isFiscalInvoiced())->toBeFalse()
        ->and($order->canBeFiscalInvoiced())->toBeFalse()
        ->and($order->canTransitionFiscalTo('facturado'))->toBeFalse()
        ->and($order->canTransitionFiscalTo('sin_facturar'))->toBeFalse()
        ->and($order->canTransitionFiscalTo('error_fiscal'))->toBeFalse();

    // If order is cancelled, it cannot be fiscal invoiced
    $cancelledOrder = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'status' => 'cancelado',
        'fiscal_status' => 'sin_facturar',
    ]);

    expect($cancelledOrder->canBeFiscalInvoiced())->toBeFalse();
});
