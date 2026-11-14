<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import { TrendingUp, TrendingDown } from 'lucide-svelte';

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
            minimumFractionDigits: 0,
        }).format(val);
    }

    // Chart 1: Daily/Dynamic Sales Trend (Mobile)
    const salesChartData = $derived({
        labels: overviewData.sales_trend.map((d) => d.label),
        datasets: [
            {
                label: 'Ventas ($)',
                data: overviewData.sales_trend.map((d) => d.value),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 2,
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
                    font: { size: 10 },
                },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 10 }, maxRotation: 45 },
                grid: { display: false },
            },
        },
    };

    // Chart 2: Monthly Comparison (Mobile)
    const monthlyChartData = $derived({
        labels: overviewData.monthly_comparison.map((m) => m.label.split(' ')[0]),
        datasets: [
            {
                label: 'Ingresos',
                data: overviewData.monthly_comparison.map((m) => m.sales),
                backgroundColor: 'rgba(37, 99, 235, 0.75)',
                borderRadius: 3,
            },
            ...(canViewMargins
                ? [
                      {
                          label: 'Gastos',
                          data: overviewData.monthly_comparison.map((m) => m.expenses ?? 0),
                          backgroundColor: 'rgba(239, 68, 68, 0.75)',
                          borderRadius: 3,
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
                labels: { boxWidth: 10, font: { size: 11 } },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value: any) => `$${value.toLocaleString('es-AR')}`,
                    font: { size: 10 },
                },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 10 } },
                grid: { display: false },
            },
        },
    };
</script>

<div class="flex flex-col gap-5 w-full">
    <!-- 1. KPIs Mobile (Stack) -->
    <div class="flex flex-col gap-3">
        <!-- Gastos Totales -->
        <Card class="p-4 flex items-center justify-between bg-card border-border/70 shadow-xs">
            <div>
                <span class="text-sm font-semibold text-muted-foreground">Gastos Totales</span>
                {#if canViewMargins}
                    <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">
                        {formatMoney(overviewData.kpis.total_expenses)}
                    </div>
                {:else}
                    <div class="text-xs text-muted-foreground italic mt-0.5">Restringido</div>
                {/if}
            </div>
            {#if canViewMargins}
                <div class="size-8 rounded-full bg-rose-500/10 flex items-center justify-center text-rose-700 dark:text-rose-400">
                    <TrendingDown class="size-4" />
                </div>
            {/if}
        </Card>

        <!-- Ingresos Totales -->
        <Card class="p-4 flex items-center justify-between bg-card border-border/70 shadow-xs">
            <div>
                <span class="text-sm font-semibold text-muted-foreground">Ingresos Totales</span>
                <div class="text-xl font-bold tabular-nums text-foreground mt-0.5">
                    {formatMoney(overviewData.kpis.total_revenues)}
                </div>
            </div>
            <div class="size-8 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-700 dark:text-emerald-400">
                <TrendingUp class="size-4" />
            </div>
        </Card>

        <!-- Margen Operativo -->
        <Card class="p-4 flex items-center justify-between bg-card border-border/70 shadow-xs">
            <div>
                <span class="text-sm font-semibold text-muted-foreground">Margen Operativo</span>
                {#if canViewMargins}
                    <div class="text-xl font-bold tabular-nums {overviewData.kpis.operating_margin !== null && overviewData.kpis.operating_margin < 0 ? 'text-rose-700 dark:text-rose-400' : 'text-foreground'} mt-0.5">
                        {formatMoney(overviewData.kpis.operating_margin)}
                    </div>
                {:else}
                    <div class="text-xs text-muted-foreground italic mt-0.5">Restringido</div>
                {/if}
            </div>
            {#if canViewMargins && overviewData.kpis.operating_margin !== null}
                {@const isPositive = overviewData.kpis.operating_margin >= 0}
                <div class="size-8 rounded-full flex items-center justify-center {isPositive ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'}">
                    {#if isPositive}
                        <TrendingUp class="size-4" />
                    {:else}
                        <TrendingDown class="size-4" />
                    {/if}
                </div>
            {/if}
        </Card>
    </div>

    <!-- 2. Gráfico 1 Mobile: Evolución de Ventas -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <div class="flex flex-col gap-0.5 mb-3">
            <h3 class="text-base font-semibold text-foreground">Evolución de Ventas</h3>
            <p class="text-xs text-muted-foreground">Ventas facturadas/cobradas día a día</p>
        </div>
        <div class="h-[220px] w-full">
            <ChartCanvas type="line" data={salesChartData} options={salesChartOptions} />
        </div>
    </Card>

    <!-- 3. Gráfico 2 Mobile: Comparativa Mensual -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <div class="flex flex-col gap-0.5 mb-3">
            <h3 class="text-base font-semibold text-foreground">Comparativa Mensual</h3>
            <p class="text-xs text-muted-foreground">Ventas vs Gastos a lo largo del año</p>
        </div>
        <div class="h-[240px] w-full">
            <ChartCanvas type="bar" data={monthlyChartData} options={monthlyChartOptions} />
        </div>
    </Card>

    <!-- 4. Detalle de Cobranzas Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <h3 class="text-base font-semibold text-foreground mb-3">Detalle de Cobranzas</h3>
        {#if overviewData.receipts_detail.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">
                No se registraron cobranzas en este período.
            </div>
        {:else}
            <div class="flex flex-col gap-2">
                {#each overviewData.receipts_detail.slice(0, 5) as r (r.id)}
                    <div class="p-2.5 rounded-lg bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center justify-between text-xs">
                        <div class="min-w-0">
                            <span class="font-semibold text-foreground block">{r.receipt_number}</span>
                            <span class="text-muted-foreground capitalize">{r.payment_method.replace('_', ' ')} • {r.date}</span>
                        </div>
                        <span class="font-bold text-foreground tabular-nums text-sm shrink-0 pl-2">
                            {formatMoney(r.amount)}
                        </span>
                    </div>
                {/each}
            </div>
        {/if}
    </Card>

    <!-- 5. Top 10 Productos Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <h3 class="text-base font-semibold text-foreground mb-3">Top 10 Productos Más Vendidos</h3>
        {#if overviewData.top_products.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">
                No hay ventas de productos registradas.
            </div>
        {:else}
            <div class="flex flex-col gap-2">
                {#each overviewData.top_products as p, i (p.product_id)}
                    <div class="p-2.5 rounded-lg bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-5 rounded-full bg-card dark:bg-secondary/60 border border-border/80 dark:border-border/90 text-foreground text-[10px] flex items-center justify-center font-bold shrink-0 tabular-nums">
                                {i + 1}
                            </span>
                            <div class="min-w-0">
                                <span class="font-medium text-foreground truncate block max-w-[160px]">{p.name}</span>
                                <span class="text-muted-foreground">{p.total_quantity} u.</span>
                            </div>
                        </div>
                        <span class="font-bold text-foreground tabular-nums text-sm shrink-0 pl-2">
                            {formatMoney(p.total_subtotal)}
                        </span>
                    </div>
                {/each}
            </div>
        {/if}
    </Card>

    <!-- 6. Top 10 Compradores Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <h3 class="text-base font-semibold text-foreground mb-3">Top 10 Compradores</h3>
        {#if overviewData.top_buyers.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">
                No hay clientes compradores registrados.
            </div>
        {:else}
            <div class="flex flex-col gap-2">
                {#each overviewData.top_buyers as b, i (b.customer_id)}
                    <div class="p-2.5 rounded-lg bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-5 rounded-full bg-card dark:bg-secondary/60 border border-border/80 dark:border-border/90 text-foreground text-[10px] flex items-center justify-center font-bold shrink-0 tabular-nums">
                                {i + 1}
                            </span>
                            <div class="min-w-0">
                                <span class="font-medium text-foreground truncate block max-w-[160px]">{b.name}</span>
                                <span class="text-muted-foreground">{b.orders_count} pedidos</span>
                            </div>
                        </div>
                        <span class="font-bold text-foreground tabular-nums text-sm shrink-0 pl-2">
                            {formatMoney(b.total_revenue)}
                        </span>
                    </div>
                {/each}
            </div>
        {/if}
    </Card>
</div>
