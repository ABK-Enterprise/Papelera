<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->isCreditNote() ? 'Nota de Crédito' : 'Factura' }} - {{ $invoice->formatted_number }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        @page {
            size: a4 portrait;
            margin: 12mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.35;
        }
        .page-container {
            min-height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .content-body {
            flex: 1 0 auto;
        }
        .page-footer {
            flex-shrink: 0;
            margin-top: auto;
            padding-top: 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .tabular-nums {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
        }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Contenedor Encabezado Oficial AFIP */
        .header-container {
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            margin-bottom: 8px;
            position: relative;
            background: #ffffff;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
            padding: 8px 10px;
        }
        .header-left {
            width: 46%;
        }
        .header-center {
            width: 8%;
            text-align: center;
            padding: 0 !important;
            position: relative;
        }
        .header-right {
            width: 46%;
            border-left: 1px solid #0f172a;
        }

        /* Recuadro Centrado con Letra Oficial */
        .letter-box {
            width: 48px;
            margin: 0 auto;
            background: #ffffff;
            border-left: 1.5px solid #0f172a;
            border-right: 1.5px solid #0f172a;
            border-bottom: 1.5px solid #0f172a;
            border-radius: 0 0 4px 4px;
            padding: 2px 0 4px 0;
            text-align: center;
        }
        .letter-char {
            font-size: 28px;
            font-weight: 900;
            line-height: 1;
            color: #0f172a;
        }
        .letter-code {
            font-size: 10px;
            font-weight: 800;
            color: #334155;
            margin-top: 2px;
            letter-spacing: 0.5px;
        }

        /* Tipografías del Encabezado */
        .tenant-name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.3px;
            margin-bottom: 3px;
        }
        .tenant-legal {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 2px;
        }
        .tenant-detail {
            font-size: 11px;
            color: #475569;
            margin-bottom: 1.5px;
        }
        .invoice-type-title {
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin-bottom: 5px;
        }
        .meta-row {
            font-size: 11px;
            color: #334155;
            margin-bottom: 1.5px;
        }
        .meta-row strong {
            color: #0f172a;
        }

        /* Comprobante Asociado */
        .associated-invoice-box {
            border: 1px dashed #0284c7;
            background-color: #f0f9ff;
            color: #0369a1;
            padding: 5px 8px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Datos del Cliente / Receptor */
        .receptor-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 8px;
        }
        .receptor-table {
            width: 100%;
            border-collapse: collapse;
        }
        .receptor-table td {
            vertical-align: top;
            font-size: 11.5px;
            color: #334155;
            padding: 1.5px 4px;
        }

        /* Tabla de Ítems */
        .items-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 6px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1.5px solid #0f172a;
        }
        .items-table td {
            padding: 5px 6px;
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Anchos de Columnas */
        .col-sku { width: 14%; }
        .col-desc { width: 38%; }
        .col-qty { width: 10%; }
        .col-unit { width: 8%; }
        .col-price { width: 15%; }
        .col-iva { width: 7%; }
        .col-subtotal { width: 15%; }

        /* Sticky Footer Simétrico a 2 Columnas */
        .footer-columns-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .footer-columns-table td {
            vertical-align: bottom;
            padding: 0;
        }
        .col-afip {
            width: 52%;
            padding-right: 8px !important;
        }
        .col-totals {
            width: 48%;
            text-align: right;
        }

        /* Recuadro Oficial AFIP / ARCA Izquierdo */
        .afip-footer-box {
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            padding: 6px 8px;
            background: #ffffff;
            text-align: left;
        }
        .afip-inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .afip-inner-table td {
            vertical-align: middle;
            padding: 0;
        }
        .qr-cell {
            width: 85px;
            text-align: center;
        }
        .qr-img {
            width: 78px;
            height: 78px;
            display: block;
            margin: 0 auto;
        }
        .footer-info {
            padding-left: 8px !important;
        }
        .arca-badge {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }
        .cae-highlight {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .cae-due {
            font-size: 11px;
            color: #334155;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .afip-disclaimer {
            font-size: 11px;
            color: #64748b;
            line-height: 1.2;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }

        /* Recuadro de Totales Derecho */
        .totals-box {
            display: inline-block;
            width: 100%;
            max-width: 280px;
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            background: #ffffff;
            padding: 6px 10px;
            text-align: left;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 2.5px 0;
            font-size: 11.5px;
        }
        .totals-table .label {
            color: #475569;
            text-align: left;
            font-weight: 600;
        }
        .totals-table .value {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
        .totals-table .total-row td {
            border-top: 1.5px solid #0f172a;
            font-size: 13px;
            font-weight: 900;
            color: #0f172a;
            padding-top: 4px;
        }

        .watermark-footer {
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            letter-spacing: 0.3px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            margin-top: 6px;
        }
    </style>
</head>
<body>

    <div class="page-container">
        <div class="content-body">
            <!-- 1. Encabezado Oficial AFIP -->
            <div class="header-container">
                <table class="header-table">
                    <tr>
                        <!-- Lado Izquierdo: Emisor -->
                        <td class="header-left">
                            @php
                                $tenantLogo = app(\App\Services\TenantLogoService::class)->getBase64Logo($tenant);
                            @endphp
                            @if($tenantLogo)
                                <img src="{{ $tenantLogo }}" alt="Logo" style="max-height: 44px; max-width: 140px; margin-bottom: 6px; object-fit: contain; display: block;" />
                            @endif
                            <div class="tenant-name">{{ $tenant->legal_name ?: $tenant->name }}</div>
                            @if($tenant->legal_name && $tenant->name && $tenant->legal_name !== $tenant->name)
                                <div class="tenant-legal">Nombre Comercial: {{ $tenant->name }}</div>
                            @endif
                            <div class="tenant-detail"><strong>Domicilio Comercial:</strong> {{ $tenant->address ?? '-' }}{{ $tenant->city ? ', ' . $tenant->city : '' }}{{ $tenant->state ? ', ' . $tenant->state : '' }}</div>
                            <div class="tenant-detail">
                                <strong>Condición IVA:</strong>
                                {{ match($tenant->tax_condition) {
                                    'responsable_inscripto' => 'IVA Responsable Inscripto',
                                    'monotributo', 'responsable_monotributo' => 'Responsable Monotributo',
                                    'exento' => 'IVA Exento',
                                    default => 'IVA Responsable Inscripto'
                                } }}
                            </div>
                            <div class="tenant-detail"><strong>CUIT:</strong> <span class="tabular-nums">{{ $tenant->cuit_tax_id ?? '-' }}</span></div>
                        </td>

                        <!-- Recuadro Central: Letra del Comprobante -->
                        <td class="header-center">
                            <div class="letter-box">
                                <div class="letter-char">{{ $invoice->cbte_letter }}</div>
                                <div class="letter-code">COD. {{ sprintf('%03d', $invoice->cbte_tipo) }}</div>
                            </div>
                        </td>

                        <!-- Lado Derecho: Datos del Comprobante y AFIP -->
                        <td class="header-right">
                            <div class="invoice-type-title">{{ $invoice->isCreditNote() ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</div>
                            <div class="meta-row">
                                <strong>Punto de Venta:</strong> <span class="tabular-nums">{{ sprintf('%04d', $invoice->punto_venta) }}</span>
                                &nbsp;&nbsp;
                                <strong>Comp. Nro:</strong> <span class="tabular-nums">{{ sprintf('%08d', $invoice->numero_comprobante) }}</span>
                            </div>
                            <div class="meta-row">
                                <strong>Fecha de Emisión:</strong> <span class="tabular-nums">{{ $invoice->issue_date?->format('d/m/Y') ?? now()->format('d/m/Y') }}</span>
                            </div>
                            <div class="meta-row">
                                <strong>CUIT:</strong> <span class="tabular-nums">{{ $tenant->cuit_tax_id ?? '-' }}</span>
                            </div>
                            <div class="meta-row">
                                <strong>Ingresos Brutos:</strong> <span class="tabular-nums">{{ $tenant->gross_income_number ?? $tenant->cuit_tax_id ?? '-' }}</span>
                            </div>
                            <div class="meta-row">
                                <strong>Inicio de Actividades:</strong> <span class="tabular-nums">{{ $tenant->start_activity_date?->format('d/m/Y') ?? '-' }}</span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 2. Comprobante Asociado (si es Nota de Crédito) -->
            @if($invoice->isCreditNote())
                <div class="associated-invoice-box">
                    @if($invoice->associatedInvoice)
                        Comprobante Asociado: Factura {{ $invoice->associatedInvoice->cbte_letter }} {{ sprintf('%04d-%08d', $invoice->associatedInvoice->punto_venta, $invoice->associatedInvoice->numero_comprobante) }} - Fecha: {{ $invoice->associatedInvoice->issue_date?->format('d/m/Y') }}
                    @else
                        Comprobante Asociado: Factura Original {{ $order->order_number }}
                    @endif
                </div>
            @endif

            <!-- 3. Datos del Receptor (Cliente) -->
            <div class="receptor-box">
                <table class="receptor-table">
                    <tr>
                        <td style="width: 50%;">
                            <strong>{{ match($invoice->doc_tipo) {
                                80 => 'CUIT',
                                86 => 'CUIL',
                                96 => 'DNI',
                                default => 'Documento'
                            } }}:</strong>
                            <span class="tabular-nums">{{ $invoice->doc_nro }}</span>
                        </td>
                        <td style="width: 50%;">
                            <strong>Condición IVA:</strong>
                            {{ match($invoice->receptor_condicion_iva_id) {
                                1 => 'IVA Responsable Inscripto',
                                4 => 'IVA Exento',
                                6 => 'Responsable Monotributo',
                                default => 'Consumidor Final'
                            } }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Razón Social / Nombre:</strong> {{ $invoice->receptor_name }}
                        </td>
                        <td>
                            <strong>Condición de Venta:</strong> {{ ($order->payment_status ?? '') === 'cobrado' ? 'Contado' : 'Cuenta Corriente' }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <strong>Domicilio:</strong> {{ $invoice->receptor_address ?: ($order->delivery_address ?: '-') }}
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 4. Tabla de Ítems -->
            @php
                $isInvoiceA = ($invoice->cbte_letter === 'A');
            @endphp
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-sku text-left">Código / SKU</th>
                        <th class="col-desc text-left">Descripción del Producto</th>
                        <th class="col-qty text-right">Cantidad</th>
                        <th class="col-unit text-center">Unidad</th>
                        <th class="col-price text-right">{{ $isInvoiceA ? 'Precio Unit. Neto' : 'Precio Unitario' }}</th>
                        @if($isInvoiceA)
                            <th class="col-iva text-right">% IVA</th>
                        @endif
                        <th class="col-subtotal text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        @php
                            $rate = (float) ($item->product?->iva_rate ?? 21.00);
                            $itemUnitPrice = (float) $item->unit_price;
                            $itemSubtotal = (float) $item->subtotal;

                            if ($isInvoiceA) {
                                $displayUnitPrice = $itemUnitPrice / (1 + ($rate / 100));
                                $displaySubtotal = $itemSubtotal / (1 + ($rate / 100));
                            } else {
                                $displayUnitPrice = $itemUnitPrice;
                                $displaySubtotal = $itemSubtotal;
                            }
                        @endphp
                        <tr>
                            <td class="col-sku text-left tabular-nums">{{ $item->product?->sku ?? '-' }}</td>
                            <td class="col-desc text-left">{{ $item->product?->name ?? 'Producto' }}</td>
                            <td class="col-qty text-right tabular-nums">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                            <td class="col-unit text-center">{{ strtoupper($item->packaging_type ?: 'u') }}</td>
                            <td class="col-price text-right tabular-nums">$ {{ number_format($displayUnitPrice, 2, ',', '.') }}</td>
                            @if($isInvoiceA)
                                <td class="col-iva text-right tabular-nums">{{ number_format($rate, 1, ',', '.') }}%</td>
                            @endif
                            <td class="col-subtotal text-right tabular-nums">$ {{ number_format($displaySubtotal, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isInvoiceA ? 7 : 6 }}" class="text-center" style="padding: 12px; color: #64748b;">
                                Detalle de venta según pedido {{ $order->order_number }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- 5. Pie de Página (Sticky Footer anclado simétrico a 2 Columnas) -->
        <div class="page-footer">
            <table class="footer-columns-table">
                <tr>
                    <!-- Columna Izquierda: AFIP QR + Autorización CAE -->
                    <td class="col-afip">
                        <div class="afip-footer-box">
                            <table class="afip-inner-table">
                                <tr>
                                    <td class="qr-cell">
                                        @if(!empty($qrBase64))
                                            <img src="{{ $qrBase64 }}" alt="QR Oficial AFIP / ARCA" class="qr-img" />
                                        @endif
                                    </td>
                                    <td class="footer-info">
                                        <div class="arca-badge">Comprobante Autorizado por ARCA (ex-AFIP)</div>
                                        <div class="cae-highlight">
                                            CAE Nº: <span class="tabular-nums">{{ $invoice->cae }}</span>
                                        </div>
                                        <div class="cae-due">
                                            Fecha de Vto. de CAE: <span class="tabular-nums">{{ $invoice->cae_due_date?->format('d/m/Y') }}</span>
                                        </div>
                                        <div class="afip-disclaimer">
                                            Esta Administración Federal no se responsabiliza por los datos ingresados en el detalle de la operación
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>

                    <!-- Columna Derecha: Cuadro de Totales -->
                    <td class="col-totals">
                        <div class="totals-box">
                            <table class="totals-table">
                                @if($isInvoiceA)
                                    <tr>
                                        <td class="label">Importe Neto Gravado:</td>
                                        <td class="value tabular-nums">$ {{ number_format((float) $invoice->importe_neto, 2, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Alícuota 21% (Importe IVA):</td>
                                        <td class="value tabular-nums">$ {{ number_format((float) $invoice->importe_iva, 2, ',', '.') }}</td>
                                    </tr>
                                    @if((float) $invoice->importe_exento > 0)
                                        <tr>
                                            <td class="label">No Gravado / Exento:</td>
                                            <td class="value tabular-nums">$ {{ number_format((float) $invoice->importe_exento, 2, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                    @if((float) $invoice->importe_tributos > 0)
                                        <tr>
                                            <td class="label">Otros Tributos:</td>
                                            <td class="value tabular-nums">$ {{ number_format((float) $invoice->importe_tributos, 2, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                @else
                                    <tr>
                                        <td class="label">Subtotal:</td>
                                        <td class="value tabular-nums">$ {{ number_format((float) ($order->subtotal_amount ?? $invoice->importe_total), 2, ',', '.') }}</td>
                                    </tr>
                                @endif
                                <tr class="total-row">
                                    <td class="label">Importe Total:</td>
                                    <td class="value tabular-nums">$ {{ number_format((float) $invoice->importe_total, 2, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
