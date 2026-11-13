<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import { TrendingUp, TrendingDown, HelpCircle } from 'lucide-svelte';

    interface OverviewProps {
        overviewData: {
            kpis: {
                total_expenses: number | null;
                total_revenues: number;
                operating_margin: number | null;
            };
            sales_trend: Array<{ key: string; label: string; value: number }>;
            monthly_comparison: Array<{
                month_key: string;
                label: string;
                sales: number;
                orders_count: number;
                revenues: number;
                expenses: number | null;
                operating_margin: number | null;
            }>;
            receipts_detail: Array<{
                id: number;
                receipt_number: string;
                payment_method: string;
                amount: number;
                date: string;
            }>;
            top_products: Array<{
                product_id: number;
                name: string;
                sku: string | null;
                total_quantity: number;
                total_subtotal: number;
            }>;
            top_buyers: Array<{
                customer_id: number;
                name: string;
                orders_count: number;
                total_revenue: number;
            }>;
        };
        canViewMargins?: boolean;
    }

    let { overviewData, canViewMargins = true }: OverviewProps = $props();

    function formatMoney(val: number | null | undefined): string {
        if (val === null || val === undefined) return '-';
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    // Chart 1: Daily/Dynamic Sales Trend
    const salesChartData = $derived({
        labels: overviewData.sales_trend.map((d) => d.label),
        datasets: [
            {
                label: 'Ventas ($)',
                data: overviewData.sales_trend.map((d) => d.value),
                borderColor: '#2563eb', // blue-600
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#2563eb',
            },
        ],
    });

    const salesChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context: any) => `Ventas: ${formatMoney(context.parsed.y)}`,
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value: any) => `$${value.toLocaleString('es-AR')}`,
                    font: { size: 12 },
                },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 12 } },
                grid: { display: false },
            },
        },
    };

    // Chart 2: Monthly Comparison (12 Months)
    const monthlyChartData = $derived({
        labels: overviewData.monthly_comparison.map((m) => m.label),
        datasets: [
            {
                label: 'Ingresos',
                data: overviewData.monthly_comparison.map((m) => m.sales),
                backgroundColor: 'rgba(37, 99, 235, 0.75)', // blue-600
                borderRadius: 4,
            },
            ...(canViewMargins
                ? [
                      {
                          label: 'Gastos en Compras',
                          data: overviewData.monthly_comparison.map((m) => m.expenses ?? 0),
                          backgroundColor: 'rgba(239, 68, 68, 0.75)', // red-500
                          borderRadius: 4,
                      },
                      {
                          label: 'Margen Operativo',
                          data: overviewData.monthly_comparison.map((m) => m.operating_margin ?? 0),
                          backgroundColor: 'rgba(16, 185, 129, 0.75)', // emerald-500
                          borderRadius: 4,
                      },
                  ]
                : []),
        ],
    });

    const monthlyChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top' as const,
                labels: { boxWidth: 12, font: { size: 13 } },
            },
            tooltip: {
                callbacks: {
                    label: (context: any) => `${context.dataset.label}: ${formatMoney(context.parsed.y)}`,
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value: any) => `$${value.toLocaleString('es-AR')}`,
                    font: { size: 12 },
                },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 12 } },
                grid: { display: false },
            },
        },
    };
</script>

<div class="flex flex-col gap-6 w-full">
    <!-- 1. KPIs de Resumen Ejecutivo (Gastos, Ingresos, Margen) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Gastos Totales -->
        <Card class="p-5 flex flex-col justify-between gap-3 bg-card border-border/70 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Gastos Totales</span>
                {#if canViewMargins}
                    <div class="size-8 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-700 dark:text-rose-400">
                        <TrendingDown class="size-4.5" />
                    </div>
                {/if}
            </div>
            <div>
                {#if canViewMargins}
                    <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                        {formatMoney(overviewData.kpis.total_expenses)}
                    </div>
                {:else}
                    <div class="text-sm text-muted-foreground italic">Restringido</div>
                {/if}
            </div>
        </Card>

        <!-- Ingresos Totales -->
        <Card class="p-5 flex flex-col justify-between gap-3 bg-card border-border/70 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Ingresos Totales</span>
                <div class="size-8 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
                    <TrendingUp class="size-4.5" />
                </div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                    {formatMoney(overviewData.kpis.total_revenues)}
                </div>
            </div>
        </Card>

        <!-- Margen Operativo -->
        <Card class="p-5 flex flex-col justify-between gap-3 bg-card border-border/70 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-base font-semibold tracking-normal text-muted-foreground">Margen Operativo</span>
                {#if canViewMargins && overviewData.kpis.operating_margin !== null}
                    {@const isPositive = overviewData.kpis.operating_margin >= 0}
                    <div class="size-8 rounded-full flex items-center justify-center {isPositive ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'}">
                        {#if isPositive}
                            <TrendingUp class="size-4.5" />
                        {:else}
                            <TrendingDown class="size-4.5" />
                        {/if}
                    </div>
                {/if}
            </div>
            <div>
                {#if canViewMargins}
                    <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight {overviewData.kpis.operating_margin !== null && overviewData.kpis.operating_margin < 0 ? 'text-rose-700 dark:text-rose-400' : 'text-foreground'}">
                        {formatMoney(overviewData.kpis.operating_margin)}
                    </div>
                {:else}
                    <div class="text-sm text-muted-foreground italic">Restringido</div>
                {/if}
            </div>
        </Card>
    </div>

    <!-- 2. Gráfico 1: Evolución de Ventas -->
    <Card class="p-6 bg-card border-border/70 shadow-xs">
        <div class="flex flex-col gap-1 mb-5">
            <h3 class="text-lg font-semibold tracking-tight text-foreground">Evolución de Ventas</h3>
            <p class="text-sm text-muted-foreground leading-normal">
                Ventas facturadas/cobradas día a día en el rango de fechas seleccionado
            </p>
        </div>
        <div class="h-[280px] w-full">
            <ChartCanvas type="line" data={salesChartData} options={salesChartOptions} />
        </div>
    </Card>

    <!-- 3. Gráfico 2: Comparativa Mensual (+20px de altura = h-[300px]) -->
    <Card class="p-6 bg-card border-border/70 shadow-xs">
        <div class="flex flex-col gap-1 mb-5">
            <h3 class="text-lg font-semibold tracking-tight text-foreground">Comparativa Mensual</h3>
            <p class="text-sm text-muted-foreground leading-normal">
                Ventas vs Gastos en Compras vs Margen Operativo mes a mes
            </p>
        </div>
        <div class="h-[300px] w-full">
            <ChartCanvas type="bar" data={monthlyChartData} options={monthlyChartOptions} />
        </div>
    </Card>

    <!-- 4. Detalle de Cobranzas Registradas (Ascendido) -->
    <Card class="p-6 bg-card border-border/70 shadow-xs">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold tracking-tight text-foreground">Detalle de Cobranzas</h3>
                <p class="text-sm text-muted-foreground leading-normal">Últimas cobranzas registradas en el período</p>
            </div>
        </div>

        {#if overviewData.receipts_detail.length === 0}
            <div class="py-8 text-center text-sm text-muted-foreground">
                No se registraron cobranzas en el período seleccionado.
            </div>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 dark:bg-secondary/35">
                        <tr class="border-b border-border/60 text-muted-foreground text-xs uppercase tracking-wider font-semibold">
                            <th class="text-left py-2.5 px-3">Recibo</th>
                            <th class="text-left py-2.5 px-3">Método de Pago</th>
                            <th class="text-left py-2.5 px-3">Fecha y Hora</th>
                            <th class="text-right py-2.5 px-3">Monto Cobrado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        {#each overviewData.receipts_detail as r (r.id)}
                            <tr class="hover:bg-muted/40 dark:hover:bg-secondary/35 transition-colors">
                                <td class="py-2.5 px-3 font-semibold text-foreground">{r.receipt_number}</td>
                                <td class="py-2.5 px-3 capitalize text-muted-foreground">{r.payment_method.replace('_', ' ')}</td>
                                <td class="py-2.5 px-3 text-muted-foreground tabular-nums">{r.date}</td>
                                <td class="py-2.5 px-3 text-right font-bold text-foreground tabular-nums">
                                    {formatMoney(r.amount)}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </Card>

    <!-- 5. Grid Inferior Bicolumna: Top 10 Productos Más Vendidos & Top 10 Compradores -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Columna 1: Top 10 Productos Más Vendidos -->
        <Card class="p-6 bg-card border-border/70 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold tracking-tight text-foreground">Top 10 Productos Más Vendidos</h3>
                    <span class="text-xs text-muted-foreground font-medium">Cobrado y facturado</span>
                </div>

                {#if overviewData.top_products.length === 0}
                    <div class="py-8 text-center text-sm text-muted-foreground">
                        No hay ventas de productos registradas en este período.
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 dark:bg-secondary/35">
                                <tr class="border-b border-border/60 text-muted-foreground text-xs uppercase tracking-wider font-semibold">
                                    <th class="text-left py-2 px-2.5">Producto</th>
                                    <th class="text-right py-2 px-2.5">Unidades</th>
                                    <th class="text-right py-2 px-2.5">Total ($)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                {#each overviewData.top_products as p, i (p.product_id)}
                                    <tr class="hover:bg-muted/40 dark:hover:bg-secondary/35 transition-colors">
                                        <td class="py-2.5 px-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="size-5 rounded-full bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 text-foreground text-xs flex items-center justify-center font-bold shrink-0 tabular-nums">
                                                    {i + 1}
                                                </span>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-[15px] text-foreground truncate max-w-[220px]" title={p.name}>
                                                        {p.name}
                                                    </div>
                                                    {#if p.sku}
                                                        <div class="text-xs text-muted-foreground font-mono">{p.sku}</div>
                                                    {/if}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2.5 text-right font-medium text-foreground tabular-nums">
                                            {p.total_quantity.toLocaleString('es-AR')}
                                        </td>
                                        <td class="py-2.5 px-2.5 text-right font-bold text-foreground tabular-nums">
                                            {formatMoney(p.total_subtotal)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </div>
        </Card>

        <!-- Columna 2: Top 10 Compradores (Nuevo) -->
        <Card class="p-6 bg-card border-border/70 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold tracking-tight text-foreground">Top 10 Compradores</h3>
                    <span class="text-xs text-muted-foreground font-medium">Mayor volumen facturado</span>
                </div>

                {#if overviewData.top_buyers.length === 0}
                    <div class="py-8 text-center text-sm text-muted-foreground">
                        No hay clientes con compras registradas en este período.
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50 dark:bg-secondary/35">
                                <tr class="border-b border-border/60 text-muted-foreground text-xs uppercase tracking-wider font-semibold">
                                    <th class="text-left py-2 px-2.5">Cliente</th>
                                    <th class="text-right py-2 px-2.5">Pedidos</th>
                                    <th class="text-right py-2 px-2.5">Ingresos ($)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/40">
                                {#each overviewData.top_buyers as b, i (b.customer_id)}
                                    <tr class="hover:bg-muted/40 dark:hover:bg-secondary/35 transition-colors">
                                        <td class="py-2.5 px-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="size-5 rounded-full bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 text-foreground text-xs flex items-center justify-center font-bold shrink-0 tabular-nums">
                                                    {i + 1}
                                                </span>
                                                <span class="font-medium text-[15px] text-foreground truncate max-w-[220px]" title={b.name}>
                                                    {b.name}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2.5 text-right font-medium text-foreground tabular-nums">
                                            {b.orders_count}
                                        </td>
                                        <td class="py-2.5 px-2.5 text-right font-bold text-foreground tabular-nums">
                                            {formatMoney(b.total_revenue)}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </div>
        </Card>
    </div>
</div>
