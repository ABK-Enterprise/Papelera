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
            minimumFractionDigits: 0,
        }).format(val);
    }

    const unitsChartData = $derived({
        labels: productsData.products_sold_trend.map((d) => d.label),
        datasets: [
            {
                label: 'Unidades',
                data: productsData.products_sold_trend.map((d) => d.value),
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 2,
            },
        ],
    });

    const unitsChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0, font: { size: 10 } },
                grid: { color: 'rgba(150, 150, 150, 0.1)' },
            },
            x: {
                ticks: { font: { size: 10 } },
                grid: { display: false },
            },
        },
    };
</script>

<div class="flex flex-col gap-4 w-full">
    <!-- KPIs Mobile -->
    <div class="grid grid-cols-3 gap-2">
        <Card class="p-3 bg-card border-border/70 shadow-xs">
            <span class="text-[11px] font-semibold text-muted-foreground block truncate">Catálogo</span>
            <div class="text-lg font-bold tabular-nums text-foreground mt-0.5">
                {productsData.kpis.catalog_products_count}
            </div>
        </Card>
        <Card class="p-3 bg-card border-border/70 shadow-xs">
            <span class="text-[11px] font-semibold text-muted-foreground block truncate">Ingreso</span>
            <div class="text-lg font-bold tabular-nums text-foreground mt-0.5">
                {productsData.kpis.stock_inflow} u.
            </div>
        </Card>
        <Card class="p-3 bg-card border-border/70 shadow-xs">
            <span class="text-[11px] font-semibold text-muted-foreground block truncate">Egreso</span>
            <div class="text-lg font-bold tabular-nums text-foreground mt-0.5">
                {productsData.kpis.stock_outflow} u.
            </div>
        </Card>
    </div>

    <!-- Gráfico Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <h3 class="text-base font-semibold text-foreground mb-3">Cantidad de Productos Vendidos</h3>
        <div class="h-[220px] w-full">
            <ChartCanvas type="line" data={unitsChartData} options={unitsChartOptions} />
        </div>
    </Card>

    <!-- Top Productos Mobile -->
    <Card class="p-4 bg-card border-border/70 shadow-xs">
        <h3 class="text-base font-semibold text-foreground mb-3">Top 10 Productos Más Vendidos</h3>
        {#if productsData.top_products.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">Sin ventas registradas.</div>
        {:else}
            <div class="flex flex-col divide-y divide-border/40">
                {#each productsData.top_products as p, i (p.product_id)}
                    <div class="py-2.5 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-5 rounded-full bg-muted text-muted-foreground text-[10px] flex items-center justify-center font-bold shrink-0">
                                {i + 1}
                            </span>
                            <div class="min-w-0">
                                <span class="font-medium text-foreground truncate block max-w-[150px]">{p.name}</span>
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
</div>
