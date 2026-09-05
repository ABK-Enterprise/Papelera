<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Pedido - {{ $order->order_number }}</title>
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
        .client-box {
            text-align: right;
        }
        .client-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            margin-bottom: 2px;
        }
        .client-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .client-meta {
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

        /* Tabla central de productos */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .items-table th {
            text-align: left;
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
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .tabular-nums {
            font-variant-numeric: tabular-nums;
        }
        .badge-bulto {
            display: inline-block;
            padding: 1px 5px;
            font-size: 9px;
            font-weight: 600;
            color: #0369a1;
            background-color: #e0f2fe;
            border-radius: 4px;
            margin-left: 4px;
        }

        /* Sección inferior */
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .bottom-table td {
            vertical-align: top;
        }
        .notes-box {
            font-size: 10px;
            color: #475569;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
            max-width: 320px;
        }
        .notes-title {
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            font-size: 9px;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .totals-table {
            width: 320px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 6px;
            font-size: 11px;
        }
        .discount-row td {
            color: #15803d;
            font-weight: 600;
        }
        .total-highlight-row td {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            padding-top: 10px;
            border-top: 2px solid #0f172a;
        }
    </style>
</head>
<body>

    <!-- Zona Superior: Datos del Pedido, Comercio y Cliente -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="order-title">Pedido #{{ $order->order_number }}</div>
                <div class="order-date">Fecha: {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</div>
                <div class="tenant-name">{{ $order->tenant->name ?? 'NUESTRO COMERCIO' }}</div>
                @if(!empty($order->tenant->cuit_tax_id))
                    <div class="tenant-meta">CUIT: {{ $order->tenant->cuit_tax_id }}</div>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="client-box">
                    <div class="client-badge">Datos del Cliente</div>
                    <div class="client-name">{{ $order->customer_name ?: ($order->customer->name ?? 'Cliente Ocasional') }}</div>
                    @if(!empty($order->customer?->tax_id))
                        <div class="client-meta">CUIT/DNI: {{ $order->customer->tax_id }} ({{ strtoupper($order->customer->tax_condition ?? 'Consumidor Final') }})</div>
                    @endif
                    @if(!empty($order->customer_phone ?: $order->customer?->phone))
                        <div class="client-meta">Tel: {{ $order->customer_phone ?: $order->customer?->phone }}</div>
                    @endif
                    @if(!empty($order->delivery_address ?: $order->customer?->address))
                        <div class="client-meta">Dirección: {{ $order->delivery_address ?: $order->customer?->address }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Barra divisoria clásica negra superior -->
    <div class="black-bar"></div>

    <!-- Zona Centro: Ítems del Pedido -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Producto</th>
                <th style="width: 20%;" class="text-center">Cantidad</th>
                <th style="width: 17%;" class="text-right">Precio Unit.</th>
                <th style="width: 18%;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grossSubtotal = 0.00;
                $bulkDiscountTotal = 0.00;
                $customerDiscountTotal = (float) ($order->customer_discount_amount ?? 0.00);
            @endphp

            @forelse($order->items as $item)
                @php
                    $product = $item->product;
                    $isWeighable = $product ? $product->isWeighable() : false;
                    $isBulk = in_array($item->packaging_type, ['bulto', 'bolson', 'package'], true);

                    // Formateo de cantidad: si es pesaje, 2 decimales con coma (ej. 1,62 kg)
                    if ($isWeighable) {
                        $qtyFormatted = number_format((float) $item->quantity_base, 2, ',', '.') . ' kg';
                    } elseif ($isBulk) {
                        $qtyFormatted = (int) $item->quantity . ' bulto(s)';
                    } else {
                        $qtyFormatted = (int) $item->quantity . ' u.';
                    }

                    // Precios para desglose
                    $catalogPrice = (float) ($product->price ?? $item->unit_price);
                    $lineGross = $catalogPrice * (float) $item->quantity_base;
                    $grossSubtotal += $lineGross;

                    if ($isBulk && (float) ($product->bulk_discount_price ?? 0) > 0 && $catalogPrice > (float) $item->unit_price) {
                        $bulkDiscountTotal += ($catalogPrice - (float) $item->unit_price) * (float) $item->quantity_base;
                    }

                    $itemSubtotal = (float) $item->subtotal;
                @endphp
                <tr>
                    <td>
                        <div class="product-name">
                            {{ $product->name ?? 'Producto' }}
                            @if($isBulk)
                                <span class="badge-bulto">Bulto x {{ (float) ($item->units_per_package ?: $product->package_size ?? 1) }}</span>
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
                    <td colspan="4" class="text-center" style="padding: 24px; color: #64748b;">
                        No hay productos registrados en este pedido.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Barra divisoria clásica negra inferior -->
    <div class="black-bar"></div>

    <!-- Zona Inferior: Desglose de Totales y Notas -->
    <table class="bottom-table">
        <tr>
            <td style="width: 50%;">
                @if(!empty($order->notes))
                    <div class="notes-box">
                        <div class="notes-title">Observaciones</div>
                        <div>{{ $order->notes }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 50%;">
                <table class="totals-table">
                    <tr>
                        <td style="color: #64748b;">Suma total de productos:</td>
                        <td class="text-right tabular-nums font-semibold">
                            ${{ number_format($grossSubtotal, 2, ',', '.') }}
                        </td>
                    </tr>

                    @if($bulkDiscountTotal > 0.01)
                        <tr class="discount-row">
                            <td>Descuento por bulto:</td>
                            <td class="text-right tabular-nums">
                                -${{ number_format($bulkDiscountTotal, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endif

                    @if($customerDiscountTotal > 0.01)
                        <tr class="discount-row">
                            <td>Descuento por cliente ({{ (float) ($order->customer_discount_percentage ?? 0) }}%):</td>
                            <td class="text-right tabular-nums">
                                -${{ number_format($customerDiscountTotal, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endif

                    <tr class="total-highlight-row">
                        <td>TOTAL DEL PEDIDO:</td>
                        <td class="text-right tabular-nums">
                            ${{ number_format((float) $order->total_amount, 2, ',', '.') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
