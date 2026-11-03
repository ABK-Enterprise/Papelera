<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Comercial - {{ $tenant->name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 12mm 15mm 12mm 15mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Google Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .logo-img {
            max-height: 48px;
            max-width: 180px;
            object-fit: contain;
        }
        .tenant-name {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #0f172a;
            text-transform: uppercase;
        }
        .tenant-cuit {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            margin-top: 2px;
        }
        .report-title-box {
            text-align: right;
        }
        .report-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.3px;
        }
        .report-period {
            font-size: 12px;
            color: #334155;
            font-weight: 600;
            margin-top: 3px;
        }
        .report-warehouse {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        /* KPI Cards */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 20px;
        }
        .kpi-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
            vertical-align: top;
        }
        .kpi-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }
        .kpi-sub {
            font-size: 10px;
            color: #64748b;
            margin-top: 4px;
            font-variant-numeric: tabular-nums;
        }

        /* Content Layout: 2 Columns */
        .layout-table {
            width: 100%;
            border-collapse: collapse;
        }
        .layout-table td {
            vertical-align: top;
        }
        .left-col {
            width: 60%;
            padding-right: 15px;
        }
        .right-col {
            width: 40%;
            padding-left: 15px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #cbd5e1;
        }

        /* Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .data-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .data-table td {
            padding: 6px 8px;
            font-size: 11px;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .data-table tr:nth-child(even) {
            background-color: #fafbfc;
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

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo-img"><br>
                @endif
                <div class="tenant-name">{{ $tenant->name }}</div>
                @if($tenant->cuit_tax_id)
                    <div class="tenant-cuit">CUIT: {{ $tenant->cuit_tax_id }}</div>
                @endif
            </td>
            <td class="report-title-box" style="width: 50%;">
                <div class="report-title">Reporte y Estadísticas</div>
                <div class="report-period">
                    Período: {{ $dateFrom->format('d/m/Y') }} al {{ $dateTo->format('d/m/Y') }}
                </div>
                <div class="report-warehouse">
                    Sucursal/Depósito: {{ $warehouse ? $warehouse->name : 'Consolidado (Todos)' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- KPI Summary Row -->
    <table class="kpi-table">
        <tr>
            <!-- Ventas Totales -->
            <td class="kpi-card">
                <div class="kpi-label">Ventas Brutas</div>
                <div class="kpi-value">${{ number_format($metrics['sales']['total'], 2, ',', '.') }}</div>
                <div class="kpi-sub">{{ $metrics['sales']['orders_count'] }} pedidos | Prom: ${{ number_format($metrics['sales']['average_ticket'], 2, ',', '.') }}</div>
            </td>

            <!-- Cobranzas Totales -->
            <td class="kpi-card">
                <div class="kpi-label">Ingresos Cobrados</div>
                <div class="kpi-value">${{ number_format($metrics['revenues']['total'], 2, ',', '.') }}</div>
                <div class="kpi-sub">Total liquidez percibida</div>
            </td>

            @if($canViewMargins)
                <!-- Gastos en Compras -->
                <td class="kpi-card">
                    <div class="kpi-label">Gastos / Compras</div>
                    <div class="kpi-value">${{ number_format($metrics['expenses']['total'], 2, ',', '.') }}</div>
                    <div class="kpi-sub">{{ $metrics['expenses']['purchases_count'] }} compras recibidas</div>
                </td>

                <!-- Margen Operativo Bruto -->
                <td class="kpi-card">
                    <div class="kpi-label">Margen Operativo</div>
                    <div class="kpi-value" style="color: {{ $metrics['margin']['operating_margin'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        ${{ number_format($metrics['margin']['operating_margin'], 2, ',', '.') }}
                    </div>
                    <div class="kpi-sub">{{ $metrics['margin']['margin_percentage'] }}% sobre ventas</div>
                </td>
            @endif
        </tr>
    </table>

    <!-- Content Sections -->
    <table class="layout-table">
        <tr>
            <!-- Left Column: Top 10 Products -->
            <td class="left-col">
                <div class="section-title">Top 10 Productos Más Vendidos</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Código</th>
                            <th style="width: 50%;">Producto</th>
                            <th style="width: 15%;" class="text-right">Cantidad</th>
                            <th style="width: 20%;" class="text-right">Total Facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topProducts as $prod)
                            <tr>
                                <td class="tabular-nums">{{ $prod['sku'] ?? '-' }}</td>
                                <td>{{ $prod['name'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($prod['total_quantity'], 2, ',', '.') }}</td>
                                <td class="text-right tabular-nums font-bold">${{ number_format($prod['total_subtotal'], 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8; padding: 12px;">No se registraron ventas en el período seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <!-- Right Column: Revenues by Payment Method -->
            <td class="right-col">
                <div class="section-title">Cobranzas por Medio de Pago</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Medio de Pago</th>
                            <th style="width: 20%;" class="text-center">Comp.</th>
                            <th style="width: 35%;" class="text-right">Total Cobrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($metrics['revenues']['by_method'] as $pm)
                            <tr>
                                <td>{{ $pm['label'] }}</td>
                                <td class="text-center tabular-nums">{{ $pm['count'] }}</td>
                                <td class="text-right tabular-nums">${{ number_format($pm['amount'], 2, ',', '.') }} ({{ $pm['percentage'] }}%)</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center" style="color: #94a3b8; padding: 12px;">Sin registros de cobro en este período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        <div>Generado automáticamente por {{ config('app.name', 'Papelera') }} el {{ now()->format('d/m/Y H:i') }} hs.</div>
        <div>Documento de uso interno y auditoría comercial.</div>
    </div>

</body>
</html>
