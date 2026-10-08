<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Cobro - {{ $receipt->receipt_number }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        @page {
            size: a4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: top;
        }

        /* Letra X Central */
        .type-box {
            text-align: center;
            width: 48px;
            height: 48px;
            border: 2px solid #0f172a;
            border-radius: 6px;
            margin: 0 auto;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .type-letter {
            font-size: 30px;
            font-weight: 900;
            line-height: 44px;
            color: #0f172a;
        }

        .tenant-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .tenant-legal {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            margin-top: 1px;
        }
        .tenant-meta {
            font-size: 11px;
            color: #475569;
            margin-top: 2px;
        }

        .receipt-box {
            text-align: right;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .receipt-number {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }
        .receipt-date {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }
        .status-valid {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .status-void {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .black-bar {
            height: 3px;
            background-color: #0f172a;
            width: 100%;
            margin: 10px 0 14px 0;
            border-radius: 2px;
        }

        /* Sección Cliente y Cobro */
        .client-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .section-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .client-table {
            width: 100%;
            border-collapse: collapse;
        }
        .client-table td {
            font-size: 11.5px;
            padding: 2px 0;
            vertical-align: top;
        }

        /* Resumen Destacado de Pago */
        .payment-summary-box {
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .payment-amount-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
        }
        .payment-amount-val {
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
        }
        .payment-method-tag {
            font-size: 12px;
            font-weight: 700;
            color: #0369a1;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            padding: 4px 10px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        /* Tabla de Imputaciones */
        .allocations-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .allocations-table th {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            background-color: #f1f5f9;
            padding: 6px 10px;
            border-bottom: 1px solid #cbd5e1;
        }
        .allocations-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
        }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .footer-notes {
            margin-top: 14px;
            padding: 10px 12px;
            background: #f8fafc;
            border-left: 3px solid #64748b;
            font-size: 11px;
            color: #475569;
        }

        .signature-zone {
            margin-top: 36px;
            width: 100%;
        }
        .signature-box {
            width: 220px;
            float: right;
            text-align: center;
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            font-size: 11px;
            color: #475569;
        }
    </style>
</head>
<body>

    <!-- Encabezado Oficial del Recibo -->
    <table class="header-table">
        <tr>
            <td style="width: 44%;">
                @php
                    $receiptTenant = $receipt->tenant ?? $receipt->customer?->tenant;
                    $resolvedReceiptLogo = $receiptTenant ? app(\App\Services\TenantLogoService::class)->getBase64Logo($receiptTenant) : null;
                @endphp
                @if($resolvedReceiptLogo)
                    <img src="{{ $resolvedReceiptLogo }}" alt="Logo" style="max-height: 44px; max-width: 140px; margin-bottom: 6px; object-fit: contain; display: block;" />
                @endif
                <div class="tenant-title">{{ $receipt->tenant->name ?? 'COMERCIO' }}</div>
                @if(!empty($receipt->tenant->legal_name))
                    <div class="tenant-legal">{{ $receipt->tenant->legal_name }}</div>
                @endif
                @if(!empty($receipt->tenant->cuit_tax_id))
                    <div class="tenant-meta"><strong>CUIT:</strong> {{ $receipt->tenant->cuit_tax_id }}</div>
                @endif
                <div class="tenant-meta">
                    <strong>Condición IVA:</strong> 
                    {{ strtoupper(str_replace('_', ' ', $receipt->tenant->tax_condition ?? 'Responsable Inscripto')) }}
                </div>
                @if(!empty($receipt->tenant->address))
                    <div class="tenant-meta">
                        <strong>Domicilio:</strong> {{ $receipt->tenant->address }}
                        {{ $receipt->tenant->city ? ' - ' . $receipt->tenant->city : '' }}
                        {{ $receipt->tenant->state ? ' (' . $receipt->tenant->state . ')' : '' }}
                    </div>
                @endif
                @if(!empty($receipt->tenant->gross_income_number))
                    <div class="tenant-meta"><strong>Ingresos Brutos:</strong> {{ $receipt->tenant->gross_income_number }}</div>
                @endif
            </td>

            <td style="width: 16%; text-align: center;">
                <div class="type-box">
                    <div class="type-letter">X</div>
                </div>
            </td>

            <td style="width: 40%;">
                <div class="receipt-box">
                    <div class="receipt-title">RECIBO DE COBRO</div>
                    <div class="receipt-number">Nº {{ $receipt->receipt_number }}</div>
                    <div class="receipt-date">
                        Fecha: {{ $receipt->created_at ? $receipt->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}
                    </div>
                    @if($receipt->is_cancelled)
                        <div class="status-badge status-void">
                            ANULADO ({{ $receipt->cancelled_at ? $receipt->cancelled_at->format('d/m/Y') : 'Fecha n/d' }})
                        </div>
                    @else
                        <div class="status-badge status-valid">
                            COBRO VÁLIDO
                        </div>
                    @endif
                    @if($receipt->createdByUser)
                        <div class="receipt-date" style="margin-top: 4px;">
                            Emitido por: <strong>{{ $receipt->createdByUser->name }}</strong>
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="black-bar"></div>

    <!-- Datos del Cliente / Pagador -->
    <div class="client-card">
        <div class="section-title">Datos del Cliente / Pagador</div>
        <table class="client-table">
            <tr>
                <td style="width: 50%;">
                    <strong>Cliente / Razón Social:</strong> {{ $receipt->customer->name ?? 'Cliente Ocasional' }}
                </td>
                <td style="width: 50%;">
                    <strong>CUIT / DNI:</strong> {{ $receipt->customer->tax_id ?? 'Sin registrar' }}
                </td>
            </tr>
            <tr>
                <td>
                    <strong>Condición frente al IVA:</strong> {{ strtoupper(str_replace('_', ' ', $receipt->customer->tax_condition ?? 'Consumidor Final')) }}
                </td>
                <td>
                    <strong>Teléfono:</strong> {{ $receipt->customer->phone ?? '—' }}
                </td>
            </tr>
            @if(!empty($receipt->customer?->address))
                <tr>
                    <td colspan="2">
                        <strong>Domicilio:</strong> {{ $receipt->customer->address }}
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <!-- Detalle Principal del Cobro -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px;">
        <tr>
            <td style="padding: 10px 14px; vertical-align: middle;">
                <div style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Importe Percibido</div>
                <div style="font-size: 26px; font-weight: 900; color: #0f172a; margin-top: 2px;">
                    $ {{ number_format((float) $receipt->amount, 2, ',', '.') }}
                </div>
            </td>
            <td style="padding: 10px 14px; text-align: right; vertical-align: middle;">
                <span class="payment-method-tag">
                    {{ strtoupper(str_replace(['_', '-'], ' ', $receipt->payment_method)) }}
                </span>
                @if(!empty($receipt->reference_number))
                    <div style="font-size: 11px; color: #475569; margin-top: 4px;">
                        Ref / Transacción: <strong>{{ $receipt->reference_number }}</strong>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Imputación de Deuda / Pedidos -->
    <div class="section-title" style="margin-top: 14px;">Imputación de Pedidos y Comprobantes Afectados</div>
    @if($receipt->orderAllocations && $receipt->orderAllocations->count() > 0)
        <table class="allocations-table">
            <thead>
                <tr>
                    <th class="text-left" style="width: 25%;">Nº de Pedido</th>
                    <th class="text-right" style="width: 25%;">Total del Pedido</th>
                    <th class="text-right" style="width: 25%;">Monto Imputado</th>
                    <th class="text-right" style="width: 25%;">Saldo Restante</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receipt->orderAllocations as $allocation)
                    <tr>
                        <td class="text-left">
                            <strong>{{ $allocation->order->order_number ?? "PED-{$allocation->customer_order_id}" }}</strong>
                        </td>
                        <td class="text-right">
                            $ {{ number_format((float) ($allocation->order->total_amount ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="text-right" style="font-weight: 700; color: #15803d;">
                            $ {{ number_format((float) $allocation->amount_applied, 2, ',', '.') }}
                        </td>
                        <td class="text-right">
                            $ {{ number_format((float) ($allocation->order->debt_amount ?? 0), 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="padding: 10px 14px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 11px; color: #64748b; margin-bottom: 14px;">
            Este cobro fue acreditado a la cuenta corriente del cliente como saldo acreedor sin afectación directa a pedidos individuales.
        </div>
    @endif

    <!-- Observaciones / Motivo de Cancelación -->
    @if(!empty($receipt->notes))
        <div class="footer-notes">
            <strong>Observaciones:</strong> {{ $receipt->notes }}
        </div>
    @endif

    @if($receipt->is_cancelled && !empty($receipt->cancellation_reason))
        <div class="footer-notes" style="border-left-color: #ef4444; background: #fef2f2; color: #991b1b; margin-top: 8px;">
            <strong>Motivo de anulación:</strong> {{ $receipt->cancellation_reason }}
            @if($receipt->cancelledByUser)
                (Anulado por: {{ $receipt->cancelledByUser->name }})
            @endif
        </div>
    @endif

    <!-- Firma / Constancia -->
    <div class="signature-zone">
        <div class="signature-box">
            Firma y Aclaración / Sello Comercial
        </div>
    </div>

</body>
</html>
