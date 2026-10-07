<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra - {{ $order->order_number }}</title>
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
            font-size: 11px;
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
            padding-top: 10px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Cabecera Clásica en 3 Secciones */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .header-left {
            width: 44%;
            text-align: left;
        }
        .header-center {
            width: 12%;
            text-align: center;
            padding: 0 4px;
        }
        .header-right {
            width: 44%;
            text-align: right;
        }

        .header-logo {
            max-height: 38px;
            max-width: 140px;
            object-fit: contain;
            margin-bottom: 4px;
            display: block;
        }
        .tenant-name {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.2px;
            line-height: 1.15;
            margin-bottom: 2px;
        }
        .tenant-meta {
            font-size: 9.5px;
            color: #475569;
            line-height: 1.3;
        }

        /* Recuadro Central con Letra X */
        .letter-box {
            width: 46px;
            height: 46px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 4px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .letter-char {
            font-size: 30px;
            font-weight: 900;
            line-height: 42px;
            color: #0f172a;
        }

        /* Metadatos Derecha */
        .doc-title {
            font-size: 15px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.4px;
            text-transform: uppercase;
        }
        .doc-number {
            font-size: 13px;
            font-weight: 700;
            font-family: monospace;
            color: #0f172a;
            margin-top: 2px;
        }
        .doc-date {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
            margin-bottom: 4px;
        }
        .doc-meta {
            font-size: 9.5px;
            color: #334155;
            line-height: 1.3;
        }

        /* Ficha del Proveedor con Borde Completo */
        .supplier-card {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background-color: #f8fafc;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .supplier-table {
            width: 100%;
            border-collapse: collapse;
        }
        .supplier-table td {
            font-size: 9.5px;
            color: #334155;
            padding: 2.5px 4px;
            vertical-align: top;
        }
        .supplier-table strong {
            color: #0f172a;
            font-weight: 700;
        }
        .supplier-row-notes {
            border-top: 1px solid #e2e8f0;
            padding-top: 4px !important;
            margin-top: 2px;
        }

        /* Tabla de Ítems */
        .items-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .col-product {
            width: 46%;
        }
        .col-quantity {
            width: 18%;
        }
        .col-price {
            width: 18%;
        }
        .col-subtotal {
            width: 18%;
        }
        .items-table th {
            padding: 6px 8px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #334155;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1.5px solid #0f172a;
            background-color: #f1f5f9;
        }
        .items-table td {
            padding: 7px 8px;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .items-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .items-table tr:nth-child(even) {
            background-color: #fafafa;
        }

        .items-table th.text-left,
        .items-table td.text-left {
            text-align: left;
        }
        .items-table th.text-center,
        .items-table td.text-center {
            text-align: center;
        }
        .items-table th.text-right,
        .items-table td.text-right {
            text-align: right;
        }

        .product-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 10.5px;
            line-height: 1.25;
        }
        .product-sku {
            font-size: 9px;
            color: #64748b;
            margin-top: 1.5px;
        }
        .tabular-nums {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
        }

        /* Sticky Footer y Recuadro de Totales */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .footer-table td {
            vertical-align: bottom;
            padding: 0;
        }
        .footer-notes-cell {
            width: 50%;
            padding-right: 10px !important;
        }
        .footer-totals-cell {
            width: 50%;
            text-align: right;
        }

        .notes-box {
            font-size: 9px;
            color: #475569;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            text-align: left;
        }
        .notes-title {
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            font-size: 8.5px;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .totals-box {
            display: inline-block;
            width: 100%;
            max-width: 270px;
            border: 1.5px solid #0f172a;
            border-radius: 6px;
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
            font-size: 10px;
        }
        .totals-label {
            color: #475569;
            font-weight: 600;
            text-align: left;
        }
        .totals-value {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
        .total-highlight-row td {
            border-top: 1.5px solid #0f172a;
            font-size: 13px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            padding-top: 4px;
        }

        .watermark-footer {
            text-align: center;
            font-size: 8.5px;
            color: #94a3b8;
            letter-spacing: 0.3px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    @php
        $tenantModel = $tenant ?? $order->tenant;
        $resolvedLogo = $logoBase64 ?? ($tenantModel ? app(\App\Services\TenantLogoService::class)->getBase64Logo($tenantModel) : null);
        $supplierModel = $supplier ?? $order->supplier;
    @endphp

    <div class="page-container">
        <div class="content-body">
            <!-- 1. Cabecera Clásica en 3 Secciones -->
            <table class="header-table">
                <tr>
                    <!-- Izquierda: Logo y Datos del Comercio -->
                    <td class="header-left">
                        @if($resolvedLogo)
                            <img src="{{ $resolvedLogo }}" alt="Logo" class="header-logo" />
                        @endif
                        <div class="tenant-name">{{ $tenantModel?->legal_name ?: ($tenantModel?->name ?? 'Papelera') }}</div>
                        @if(!empty($tenantModel?->address))
                            <div class="tenant-meta">{{ $tenantModel->address }}{{ !empty($tenantModel->city) ? ', ' . $tenantModel->city : '' }}</div>
                        @endif
                        @if(!empty($tenantModel?->phone))
                            <div class="tenant-meta">Tel: {{ $tenantModel->phone }}</div>
                        @endif
                        @if(!empty($tenantModel?->email))
                            <div class="tenant-meta">Email: {{ $tenantModel->email }}</div>
                        @endif
                    </td>

                    <!-- Centro: Letra Destacada en Recuadro -->
                    <td class="header-center">
                        <div class="letter-box">
                            <div class="letter-char">X</div>
                        </div>
                    </td>

                    <!-- Derecha: Tipo de Documento, Correlativo y Datos Fiscales -->
                    <td class="header-right">
                        <div class="doc-title">ORDEN DE COMPRA</div>
                        <div class="doc-number">N° {{ $order->order_number }}</div>
                        <div class="doc-date">Fecha: {{ $order->created_at ? $order->created_at->format('d/m/Y') : now()->format('d/m/Y') }}</div>
                        <div class="doc-meta"><strong>Condición IVA:</strong> {{ strtoupper(str_replace('_', ' ', $tenantModel?->tax_condition ?? 'Responsable Inscripto')) }}</div>
                        <div class="doc-meta"><strong>CUIT:</strong> {{ $tenantModel?->cuit_tax_id ?? '-' }}</div>
                        <div class="doc-meta"><strong>IIBB:</strong> {{ $tenantModel?->gross_income_number ?? $tenantModel?->cuit_tax_id ?? '-' }}</div>
                    </td>
                </tr>
            </table>

            <!-- 2. Datos del Proveedor (Ficha con Borde Completo) -->
            <div class="supplier-card">
                <table class="supplier-table">
                    <!-- Fila 1: Razón Social, Teléfono / Contacto, Email -->
                    <tr>
                        <td style="width: 45%;"><strong>Proveedor:</strong> {{ $supplierModel?->name ?? 'Proveedor' }}</td>
                        <td style="width: 30%;"><strong>Tel / Contacto:</strong> {{ $supplierModel?->contact_number ?: ($supplierModel?->phone ?: '-') }}</td>
                        <td style="width: 25%; text-align: right;"><strong>Email:</strong> {{ $supplierModel?->email ?: '-' }}</td>
                    </tr>
                    <!-- Fila 2: Dirección -->
                    <tr>
                        <td colspan="2"><strong>Dirección:</strong> {{ $supplierModel?->address ?: '-' }}</td>
                        <td style="text-align: right;">
                            @if(!empty($supplierModel?->tax_id))
                                <strong>CUIT:</strong> {{ $supplierModel->tax_id }}
                            @else
                                <strong>Condición:</strong> Proveedor Registrado
                            @endif
                        </td>
                    </tr>
                    <!-- Fila 3: Observaciones o condiciones de recepción si existen -->
                    @if(!empty($order->notes))
                        <tr>
                            <td colspan="3" class="supplier-row-notes">
                                <strong>Condiciones / Recepción:</strong> {{ $order->notes }}
                            </td>
                        </tr>
                    @endif
                </table>
            </div>

            <!-- 3. Detalle de Ítems (Tabla Limpia) -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-product text-left">Producto Solicitado</th>
                        <th class="col-quantity text-center">Cantidad</th>
                        <th class="col-price text-right">Costo Unit.</th>
                        <th class="col-subtotal text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        @php
                            $product = $item->product;
                            $isWeighable = $product ? $product->isWeighable() : false;

                            if ($isWeighable) {
                                $qtyFormatted = number_format((float) ($item->quantity_base ?? $item->quantity), 2, ',', '.') . ' kg';
                            } else {
                                $qtyFormatted = (int) $item->quantity . ' u.';
                            }
                        @endphp
                        <tr>
                            <td class="text-left">
                                <div class="product-name">{{ $item->product_name ?: ($product?->name ?? 'Producto') }}</div>
                                @if(!empty($product?->sku))
                                    <div class="product-sku">SKU: {{ $product->sku }}</div>
                                @endif
                            </td>
                            <td class="text-center tabular-nums font-semibold">
                                {{ $qtyFormatted }}
                            </td>
                            <td class="text-right tabular-nums">
                                ${{ number_format((float) $item->unit_cost, 2, ',', '.') }}
                            </td>
                            <td class="text-right tabular-nums font-bold">
                                ${{ number_format((float) $item->subtotal, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 20px; color: #64748b;">
                                No hay productos registrados en esta orden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- 4. Pie de Página (Sticky Footer anclado al margen inferior) -->
        <div class="page-footer">
            <table class="footer-table">
                <tr>
                    <td class="footer-notes-cell">
                        @if(!empty($order->notes))
                            <div class="notes-box">
                                <div class="notes-title">Observaciones / Recepción</div>
                                <div>{{ $order->notes }}</div>
                            </div>
                        @endif
                    </td>
                    <td class="footer-totals-cell">
                        <div class="totals-box">
                            <table class="totals-table">
                                <tr>
                                    <td class="totals-label">SUBTOTAL ESTIMADO:</td>
                                    <td class="totals-value tabular-nums">${{ number_format((float) $order->total_cost, 2, ',', '.') }}</td>
                                </tr>
                                <tr class="total-highlight-row">
                                    <td class="totals-label">TOTAL ORDEN DE COMPRA:</td>
                                    <td class="totals-value tabular-nums">${{ number_format((float) $order->total_cost, 2, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
            <div class="watermark-footer">
                Generado por {{ $tenantModel->name ?? config('app.name', 'Papelera') }}
            </div>
        </div>
    </div>
</body>
</html>
