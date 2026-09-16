<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra - {{ $order->order_number }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('logo-provisorio-papelera.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
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
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .order-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
            line-height: 1.1;
        }
        .order-date {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        .tenant-name {
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }
        .tenant-meta {
            font-size: 10px;
            color: #64748b;
        }
        .supplier-box {
            text-align: right;
        }
        .supplier-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 2px;
        }
        .supplier-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .supplier-meta {
            font-size: 11px;
            color: #475569;
            margin-top: 2px;
        }

        /* Barra divisoria clásica negra */
        .black-bar {
            height: 3px;
            background-color: #0f172a;
            width: 100%;
            margin: 12px 0 16px 0;
            border-radius: 2px;
        }

        /* Tabla central de productos con layout fijo y alineaciones simétricas */
        .items-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            margin-bottom: 16px;
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
            padding: 8px 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            border-bottom: 1px solid #cbd5e1;
            background-color: #f8fafc;
        }
        .items-table td {
            padding: 10px 10px;
            font-size: 11px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .items-table tr:nth-child(even) {
            background-color: #fafafa;
        }

        /* Alineaciones con alta especificidad para evitar descalces */
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
            font-size: 12px;
        }
        .product-sku {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }
        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }

        /* Sección inferior con relación 40/60 */
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .bottom-table td {
            vertical-align: top;
            padding: 0;
        }
        .notes-box {
            font-size: 10px;
            color: #475569;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            width: 90%;
        }
        .notes-title {
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            font-size: 9px;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        /* Tabla de totales en bloque derecho (60%) */
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 4px 10px;
            font-size: 11px;
        }
        .totals-table td.totals-label {
            text-align: right;
            padding-right: 14px;
            color: #475569;
            font-weight: 500;
        }
        .totals-table td.totals-value {
            text-align: right;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: 600;
            color: #0f172a;
            width: 1%;
        }
        .total-highlight-row td.totals-label {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            padding-top: 10px;
            padding-bottom: 4px;
            border-top: 2px solid #0f172a;
        }
        .total-highlight-row td.totals-value {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            padding-top: 10px;
            padding-bottom: 4px;
            border-top: 2px solid #0f172a;
        }
    </style>
</head>
<body>

    <!-- Zona Superior: Datos de la Orden de Compra, Comercio y Proveedor -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="order-title">Orden de Compra #{{ $order->order_number }}</div>
                <div class="order-date">Fecha de emisión: {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</div>
                <div class="tenant-name">{{ $order->tenant->name ?? 'NUESTRO COMERCIO' }}</div>
                @if(!empty($order->tenant->cuit_tax_id))
                    <div class="tenant-meta">CUIT: {{ $order->tenant->cuit_tax_id }}</div>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="supplier-box">
                    <div class="supplier-badge">Datos del Proveedor</div>
                    <div class="supplier-name">{{ $order->supplier->name ?? 'Proveedor' }}</div>
                    @if(!empty($order->supplier?->tax_id))
                        <div class="supplier-meta">CUIT: {{ $order->supplier->tax_id }}</div>
                    @endif
                    @if(!empty($order->supplier?->phone))
                        <div class="supplier-meta">Tel: {{ $order->supplier->phone }}</div>
                    @endif
                    @if(!empty($order->supplier?->email))
                        <div class="supplier-meta">Email: {{ $order->supplier->email }}</div>
                    @endif
                    @if(!empty($order->supplier?->address))
                        <div class="supplier-meta">Dirección: {{ $order->supplier->address }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Barra divisoria clásica negra superior -->
    <div class="black-bar"></div>

    <!-- Zona Centro: Ítems Solicitados -->
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
                        <div class="product-name">{{ $product->name ?? 'Producto' }}</div>
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
                    <td colspan="4" class="text-center" style="padding: 24px; color: #64748b;">
                        No hay productos registrados en esta orden.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Barra divisoria clásica negra inferior -->
    <div class="black-bar"></div>

    <!-- Zona Inferior: Totales y Notas (Relación 40 / 60) -->
    <table class="bottom-table">
        <tr>
            <td style="width: 40%;">
                @if(!empty($order->notes))
                    <div class="notes-box">
                        <div class="notes-title">Observaciones / Notas</div>
                        <div>{{ $order->notes }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 60%;">
                <table class="totals-table">
                    <tr class="total-highlight-row">
                        <td class="totals-label">TOTAL ORDEN DE COMPRA:</td>
                        <td class="totals-value tabular-nums">
                            ${{ number_format((float) $order->total_cost, 2, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
