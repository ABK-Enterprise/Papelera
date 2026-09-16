<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Pedido - {{ $order->order_number }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('logo-provisorio-papelera.png') }}?v=2" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2">
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
            width: 48px;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 4px;
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
            font-size: 8px;
            font-weight: 800;
            color: #475569;
            margin-top: 1px;
            letter-spacing: 0.5px;
        }
        .not-fiscal-banner {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-top: 3px;
            line-height: 1.1;
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

        /* Ficha del Cliente con Borde Completo en 4 Filas */
        .customer-card {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background-color: #f8fafc;
            padding: 6px 10px;
            margin-bottom: 10px;
        }
        .customer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .customer-table td {
            font-size: 9.5px;
            color: #334155;
            padding: 2.5px 4px;
            vertical-align: top;
        }
        .customer-table strong {
            color: #0f172a;
            font-weight: 700;
        }
        .customer-row-notes {
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
        .badge-bulto {
            display: inline-block;
            padding: 1px 4px;
            font-size: 8.5px;
            font-weight: 600;
            color: #0369a1;
            background-color: #e0f2fe;
            border-radius: 3px;
            margin-left: 3px;
            vertical-align: middle;
        }
        .tabular-nums {
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
        }

        /* Sticky Footer y Recuadro de Totales a la Derecha */
        .totals-container {
            width: 100%;
            display: table;
            margin-bottom: 6px;
        }
        .totals-box-wrapper {
            display: table-cell;
            text-align: right;
        }
        .totals-box {
            display: inline-block;
            width: 260px;
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
        .text-green {
            color: #16a34a !important;
        }
        .total-highlight-row {
            border-top: 1.5px solid #0f172a;
            margin-top: 3px;
            padding-top: 4px;
        }
        .total-highlight-row td {
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
        $logoPath = public_path('logo-provisorio-papelera.png');
        $resolvedLogo = $logoBase64 ?? (file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null);

        $bulkDiscountTotal = 0.00;
        $customerDiscountPct = (float) ($order->customer_discount_percentage ?? 0.00);
        $rawItemsSubtotal = (float) $order->items->sum(fn($i) => (float) $i->subtotal);

        $customerDiscountAmount = (float) ($order->customer_discount_amount ?? 0.00);
        if ($customerDiscountAmount <= 0 && $customerDiscountPct > 0) {
            $customerDiscountAmount = round($rawItemsSubtotal * ($customerDiscountPct / 100), 2);
        }
    @endphp

    <div class="page-container">
        <div class="content-body">
            <!-- 1. Cabecera Clásica en 3 Secciones -->
            <table class="header-table">
                <tr>
                    <!-- Izquierda: Logo y Datos del Comercio Emisor -->
                    <td class="header-left">
                        @if($resolvedLogo)
                            <img src="{{ $resolvedLogo }}" alt="Logo" class="header-logo" />
                        @endif
                        <div class="tenant-name">{{ $order->tenant?->legal_name ?: ($order->tenant?->name ?? 'Papelera') }}</div>
                        @if(!empty($order->tenant?->address))
                            <div class="tenant-meta">{{ $order->tenant->address }}{{ !empty($order->tenant->city) ? ', ' . $order->tenant->city : '' }}</div>
                        @endif
                        @if(!empty($order->tenant?->phone))
                            <div class="tenant-meta">Tel: {{ $order->tenant->phone }}</div>
                        @endif
                        @if(!empty($order->tenant?->email))
                            <div class="tenant-meta">Email: {{ $order->tenant->email }}</div>
                        @endif
                    </td>

                    <!-- Centro: Letra Destacada en Recuadro -->
                    <td class="header-center">
                        <div class="letter-box">
                            <div class="letter-char">X</div>
                            <div class="letter-code">REMITO</div>
                        </div>
                        <div class="not-fiscal-banner">DOCUMENTO NO VÁLIDO COMO FACTURA</div>
                    </td>

                    <!-- Derecha: Tipo de Documento, Correlativo y Datos Fiscales -->
                    <td class="header-right">
                        <div class="doc-title">COMPROBANTE DE PEDIDO</div>
                        <div class="doc-number">N° {{ $order->order_number }}</div>
                        <div class="doc-date">Fecha: {{ $order->created_at ? $order->created_at->format('d/m/Y') : now()->format('d/m/Y') }}</div>
                        <div class="doc-meta"><strong>Condición IVA:</strong> {{ strtoupper(str_replace('_', ' ', $order->tenant?->tax_condition ?? 'Responsable Inscripto')) }}</div>
                        <div class="doc-meta"><strong>CUIT:</strong> {{ $order->tenant?->cuit_tax_id ?? '-' }}</div>
                        <div class="doc-meta"><strong>IIBB:</strong> {{ $order->tenant?->gross_income_number ?? $order->tenant?->cuit_tax_id ?? '-' }} &bull; <strong>Inicio Act:</strong> {{ $order->tenant?->start_activity_date ? \Illuminate\Support\Carbon::parse($order->tenant->start_activity_date)->format('d/m/Y') : '-' }}</div>
                    </td>
                </tr>
            </table>

            <!-- 2. Datos del Cliente y Operación (Borde completo en 4 Filas) -->
            <div class="customer-card">
                <table class="customer-table">
                    <!-- Fila 1: Nombre del cliente, Condición frente al IVA, DNI/CUIT -->
                    <tr>
                        <td style="width: 45%;"><strong>Cliente:</strong> {{ $order->customer_name ?: ($order->customer?->name ?? 'Consumidor Final') }}</td>
                        <td style="width: 32%;"><strong>Condición IVA:</strong> {{ strtoupper(str_replace('_', ' ', $order->customer?->tax_condition ?? 'Consumidor Final')) }}</td>
                        <td style="width: 23%; text-align: right;"><strong>CUIT / DNI:</strong> {{ $order->customer?->tax_id ?? '-' }}</td>
                    </tr>
                    <!-- Fila 2: Domicilio, Localidad, Provincia -->
                    <tr>
                        <td><strong>Domicilio:</strong> {{ $order->delivery_address ?: ($order->customer?->address ?? 'Mostrador') }}</td>
                        <td><strong>Localidad:</strong> {{ $order->customer?->city ?? $order->tenant?->city ?? '-' }}</td>
                        <td style="text-align: right;"><strong>Provincia:</strong> {{ $order->customer?->province ?? $order->tenant?->state ?? '-' }}</td>
                    </tr>
                    <!-- Fila 3: Correo electrónico, Condición de pago -->
                    <tr>
                        <td colspan="2"><strong>Email:</strong> {{ $order->customer?->email ?? '-' }}</td>
                        <td style="text-align: right;"><strong>Condición de Pago:</strong> {{ $order->derived_payment_condition }}</td>
                    </tr>
                    <!-- Fila 4: Observaciones con borde superior interno -->
                    <tr>
                        <td colspan="3" class="customer-row-notes">
                            <strong>Observaciones:</strong> {{ $order->notes ?: ($order->payment_status === 'cobrado' ? 'PAGADO' : 'Sin observaciones') }}
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 3. Detalle de Ítems (Tabla Limpia) -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-product text-left">Producto</th>
                        <th class="col-quantity text-center">Cantidad</th>
                        <th class="col-price text-right">Precio Unit.</th>
                        <th class="col-subtotal text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($order->items as $item)
                        @php
                            $product = $item->product;
                            $isWeighable = $product ? $product->isWeighable() : false;
                            $isBulk = in_array($item->packaging_type, ['bulto', 'bolson', 'package'], true);

                            if ($isWeighable) {
                                $qtyBase = (float) ($item->quantity_base ?: $item->quantity);
                                $qtyFormatted = number_format($qtyBase, 2, ',', '.') . ' kg';
                            } elseif ($isBulk) {
                                $qtyFormatted = (int) $item->quantity . ' bulto(s)';
                            } else {
                                $qtyFormatted = (int) $item->quantity . ' u.';
                            }

                            if ($isBulk) {
                                $totalUnits = 0.0;
                                if ($item->quantity_base !== null && (float) $item->quantity_base > 0) {
                                    $totalUnits = (float) $item->quantity_base;
                                } else {
                                    $packageSize = (float) ($item->units_per_package ?: ($product ? $product->package_size : 1));
                                    $totalUnits = (float) $item->quantity * ($packageSize > 0 ? $packageSize : 1);
                                }

                                if ($product) {
                                    $regularPrice = (float) $product->price;
                                    $unitPrice = (float) $item->unit_price;
                                    if ($regularPrice > $unitPrice) {
                                        $bulkDiscountTotal += ($regularPrice - $unitPrice) * $totalUnits;
                                    } elseif ((float) $product->effective_bulk_discount_percentage > 0) {
                                        $discountPct = (float) $product->effective_bulk_discount_percentage;
                                        $bulkDiscountTotal += round($regularPrice * ($discountPct / 100) * $totalUnits, 2);
                                    }
                                }
                            }

                            $itemSubtotal = (float) $item->subtotal;
                        @endphp
                        <tr>
                            <td class="text-left">
                                <div class="product-name">
                                    {{ $product->name ?? 'Producto' }}
                                    @if($isBulk)
                                        <span class="badge-bulto">Bulto x {{ (float) ($item->units_per_package ?: ($product ? $product->package_size : 1)) }}</span>
                                    @endif
                                </div>
                                @if(!empty($product?->sku))
                                    <div class="product-sku">SKU: {{ $product->sku }}</div>
                                @endif
                            </td>
                            <td class="text-center tabular-nums font-semibold">
                                {{ $qtyFormatted }}
                            </td>
                            <td class="text-right tabular-nums">
                                ${{ number_format((float) $item->unit_price, 2, ',', '.') }}
                            </td>
                            <td class="text-right tabular-nums font-bold">
                                ${{ number_format($itemSubtotal, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 20px; color: #64748b;">
                                No hay productos registrados en este pedido.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- 4. Pie de Página (Sticky Footer anclado al margen inferior) -->
        <div class="page-footer">
            <div class="totals-container">
                <div class="totals-box-wrapper">
                    <div class="totals-box">
                        <table class="totals-table">
                            <tr>
                                <td class="totals-label">SUBTOTAL:</td>
                                <td class="totals-value tabular-nums">${{ number_format($rawItemsSubtotal, 2, ',', '.') }}</td>
                            </tr>

                            @if($bulkDiscountTotal > 0)
                                <tr>
                                    <td class="totals-label text-green">Descuento por bulto:</td>
                                    <td class="totals-value tabular-nums text-green">-${{ number_format($bulkDiscountTotal, 2, ',', '.') }}</td>
                                </tr>
                            @endif

                            @if($customerDiscountPct > 0)
                                <tr>
                                    <td class="totals-label text-green">Descuento cliente (%{{ number_format($customerDiscountPct, 1, ',', '.') }}):</td>
                                    <td class="totals-value tabular-nums text-green">-${{ number_format($customerDiscountAmount, 2, ',', '.') }}</td>
                                </tr>
                            @endif

                            @if((float) ($order->shipping_cost ?? 0) > 0)
                                <tr>
                                    <td class="totals-label">Costo de envío ({{ $order->shipping_zone_name ?? 'Zona' }}):</td>
                                    <td class="totals-value tabular-nums">${{ number_format((float) $order->shipping_cost, 2, ',', '.') }}</td>
                                </tr>
                            @endif

                            <tr class="total-highlight-row">
                                <td class="totals-label">TOTAL:</td>
                                <td class="totals-value tabular-nums">${{ number_format((float) $order->total_amount, 2, ',', '.') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Marca de Agua y Pie Institucional del Sistema -->
            <div class="watermark-footer">
                Generado por {{ config('app.name', 'Papelera ERP') }} &bull; Documento no válido como factura
            </div>
        </div>
    </div>
</body>
</html>
