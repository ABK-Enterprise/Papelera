<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';

    interface Props {
        productsData: {
            kpis: {
                catalog_products_count: number;
                stock_inflow: number;
                stock_outflow: number;
            };
            products_sold_trend: Array<{ key: string; label: string; value: number }>;
            top_products: Array<{
                product_id: number;
                name: string;
                sku: string | null;
                total_quantity: number;
                total_subtotal: number;
            }>;
        };
    }

    let { productsData }: Props = $props();

    function formatMoney(val: number): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    const unitsChartData = $derived({
        labels: productsData.products_sold_trend.map((d) => d.label),
        datasets: [
            {
                label: 'Unidades Vendidas',
                data: productsData.products_sold_trend.map((d) => d.value),
                borderColor: '#10b981', // emerald-500
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#10b981',
            },
        ],
    });

    const unitsChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context: any) => `Unidades: ${context.parsed.y.toLocaleString('es-AR')}`,
                },
            },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0,
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
    <!-- 1. KPIs Productos y Stock -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Productos Catalogados -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Productos Catalogados</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {productsData.kpis.catalog_products_count}
            </div>
        </Card>

        <!-- Ingreso Total de Stock -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Ingreso Total de Stock</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {productsData.kpis.stock_inflow.toLocaleString('es-AR')} u.
            </div>
        </Card>

        <!-- Egreso Total de Stock -->
        <Card class="p-5 flex flex-col justify-between bg-card border-border/70 shadow-xs">
            <span class="text-base font-semibold tracking-normal text-muted-foreground">Egreso Total de Stock</span>
            <div class="text-2xl sm:text-3xl font-bold tabular-nums tracking-tight text-foreground">
                {productsData.kpis.stock_outflow.toLocaleString('es-AR')} u.
            </div>
        </Card>
    </div>

    <!-- 2. Gráfico: Cantidad de Productos Vendidos -->
    <Card class="p-6 bg-card border-border/70 shadow-xs">
        <div class="flex flex-col gap-1 mb-5">
            <h3 class="text-lg font-semibold tracking-tight text-foreground">Cantidad de Productos Vendidos</h3>
            <p class="text-sm text-muted-foreground leading-normal">
                Se mostrará la cantidad de productos vendidos en el rango de tiempo seleccionado
            </p>
        </div>
        <div class="h-[280px] w-full">
            <ChartCanvas type="line" data={unitsChartData} options={unitsChartOptions} />
        </div>
    </Card>

    <!-- 3. Listado Wide-Cards: Top 10 Productos Más Vendidos -->
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold tracking-tight text-foreground">Top 10 Productos más vendidos</h3>
            <span class="text-xs text-muted-foreground font-medium">Mayor volumen en unidades y recaudación</span>
        </div>

        {#if productsData.top_products.length === 0}
            <Card class="p-12 text-center text-sm text-muted-foreground">
                No hay productos vendidos en el período seleccionado.
            </Card>
        {:else}
            <div class="flex flex-col gap-2.5">
                {#each productsData.top_products as p, i (p.product_id)}
                    <Card class="p-4 flex items-center justify-between bg-card border-border/70 shadow-xs hover:border-border transition-colors">
                        <div class="flex items-center gap-3.5 min-w-0">
                            <span class="size-7 rounded-full bg-muted text-muted-foreground text-xs flex items-center justify-center font-bold shrink-0">
                                {i + 1}
                            </span>
                            <div class="min-w-0">
                                <div class="text-[17px] font-medium leading-snug text-foreground truncate max-w-[340px]" title={p.name}>
                                    {p.name}
                                </div>
                                <div class="text-sm text-muted-foreground flex items-center gap-2">
                                    {#if p.sku}
                                        <span class="font-mono text-xs">{p.sku}</span>
                                        <span>•</span>
                                    {/if}
                                    <span>{p.total_quantity.toLocaleString('es-AR')} unidades despachadas</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-right shrink-0 pl-3">
                            <div class="text-xs text-muted-foreground font-medium">Ingresos Generados</div>
                            <div class="text-lg font-bold tabular-nums text-foreground">
                                {formatMoney(p.total_subtotal)}
                            </div>
                        </div>
                    </Card>
                {/each}
            </div>
        {/if}
    </div>
</div>
