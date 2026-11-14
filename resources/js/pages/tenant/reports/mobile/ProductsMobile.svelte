<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import { router } from '@inertiajs/svelte';

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
                barcode?: string | null;
                sale_type?: string;
                price?: number;
                category_name?: string | null;
                total_quantity: number;
                total_subtotal: number;
            }>;
        };
        filters?: {
            products_sort?: string;
        };
    }

    let { productsData, filters = {} }: Props = $props();

    function formatMoney(val: number): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 0,
        }).format(val);
    }

    let productsSort = $state(filters.products_sort || 'desc');

    function updateSort(val: string) {
        productsSort = val;
        const url = new URL(window.location.href);
        url.searchParams.set('products_sort', val);
        router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true });
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
        <div class="flex items-center justify-between gap-2 mb-3">
            <h3 class="text-base font-semibold text-foreground">Top Productos</h3>
            
            <select
                value={productsSort}
                onchange={(e) => updateSort((e.target as HTMLSelectElement).value)}
                aria-label="Ordenar productos"
                class="min-h-[36px] px-2.5 py-1 text-xs rounded-lg border border-input bg-field text-foreground font-medium cursor-pointer focus:border-primary focus-visible:border-primary"
            >
                <option value="desc">Más vendidos</option>
                <option value="asc">Menos vendidos</option>
            </select>
        </div>

        {#if productsData.top_products.length === 0}
            <div class="py-6 text-center text-xs text-muted-foreground">Sin ventas registradas.</div>
        {:else}
            <div class="flex flex-col gap-2">
                {#each productsData.top_products as p, i (p.product_id)}
                    <div class="p-2.5 rounded-lg bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-5 rounded-full bg-card dark:bg-secondary/60 border border-border/80 dark:border-border/90 text-foreground text-[10px] flex items-center justify-center font-bold shrink-0 tabular-nums">
                                {i + 1}
                            </span>
                            <div class="min-w-0">
                                <span class="font-medium text-foreground truncate block max-w-[170px]">{p.name}</span>
                                <span class="text-muted-foreground">
                                    {p.total_quantity} {p.sale_type === 'weight' ? 'Kg' : 'u.'}
                                    {#if p.category_name}
                                        • {p.category_name}
                                    {/if}
                                </span>
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
