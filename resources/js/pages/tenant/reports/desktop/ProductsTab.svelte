<script lang="ts">
    import { Card } from '@/components/ui/card';
    import ChartCanvas from '@/components/charts/ChartCanvas.svelte';
    import { router } from '@inertiajs/svelte';
    import { PackageCheck, Scale, Package } from 'lucide-svelte';

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
                cost_price?: number;
                stock?: number;
                thumbnail_url?: string | null;
                image_url?: string | null;
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
            minimumFractionDigits: 2,
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
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold tracking-tight text-foreground">Top 10 Productos más vendidos</h3>

            <!-- Selector de ordenamiento estandarizado (por default: Más vendidos) -->
            <select
                value={productsSort}
                onchange={(e) => updateSort((e.target as HTMLSelectElement).value)}
                aria-label="Ordenar productos"
                class="w-full sm:w-[210px] min-h-[40px] px-3.5 py-1.5 text-sm rounded-lg border border-input/80 bg-select-background text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer truncate font-medium shrink-0"
            >
                <option value="desc">Más vendidos</option>
                <option value="asc">Menos vendidos</option>
            </select>
        </div>

        {#if productsData.top_products.length === 0}
            <Card class="p-12 text-center text-sm text-muted-foreground">
                No hay productos vendidos en el período seleccionado.
            </Card>
        {:else}
            <div class="flex flex-col gap-2.5">
                {#each productsData.top_products as p, i (p.product_id)}
                    <div class="bento-card group flex items-center justify-between gap-4 rounded-xl border border-border/70 bg-card p-4.5 shadow-xs transition-all duration-200 hover:border-primary/40 hover:shadow-md">
                        <!-- Lado Izquierdo: Ranking + Imagen/Icono + Info Principal -->
                        <div class="flex items-center gap-3.5 min-w-0 flex-1">
                            <span class="size-8 rounded-full bg-muted/80 text-foreground text-xs flex items-center justify-center font-bold shrink-0 border border-border/60 tabular-nums">
                                #{i + 1}
                            </span>

                            <!-- Imagen o placeholder de producto -->
                            {#if p.thumbnail_url || p.image_url}
                                <div class="relative size-12 rounded-xl border border-border/80 overflow-hidden shrink-0 bg-muted/30">
                                    <img
                                        src={p.thumbnail_url || p.image_url}
                                        alt={p.name}
                                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                </div>
                            {:else}
                                <div class="relative size-12 rounded-xl border border-dashed border-border/80 flex items-center justify-center bg-muted/20 shrink-0 text-muted-foreground">
                                    {#if p.sale_type === 'weight'}
                                        <Scale class="size-5 text-primary/80" />
                                    {:else}
                                        <Package class="size-5 text-primary/80" />
                                    {/if}
                                </div>
                            {/if}

                            <!-- Detalle y Metadatos en filas ordenadas como en Catálogo -->
                            <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                                <!-- Fila 1: Nombre + Badges -->
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h4 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate" title={p.name}>
                                        {p.name}
                                    </h4>

                                    {#if p.sale_type === 'weight'}
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                            <Scale class="size-3" />
                                            Pesable
                                        </span>
                                    {/if}

                                    {#if p.category_name}
                                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Cat: <strong class="text-foreground font-semibold">{p.category_name}</strong>
                                        </span>
                                    {/if}
                                </div>

                                <!-- Fila 2: Badges Unidades Vendidas, SKU, Barcode y Stock -->
                                <div class="flex items-center gap-2 flex-wrap text-xs text-muted-foreground">
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 tabular-nums">
                                        <PackageCheck class="size-3.5" />
                                        <strong>{p.total_quantity.toLocaleString('es-AR')}</strong> {p.sale_type === 'weight' ? 'Kg' : 'unid.'} vendidas
                                    </span>

                                    {#if p.sku}
                                        <span class="bg-muted/60 px-2 py-0.5 rounded text-xs tabular-nums font-mono border border-border/40">
                                            SKU: {p.sku}
                                        </span>
                                    {/if}

                                    {#if p.barcode}
                                        <span class="bg-muted/60 px-2 py-0.5 rounded text-xs tabular-nums border border-border/40">
                                            BAR: {p.barcode}
                                        </span>
                                    {/if}

                                    <span class="text-muted-foreground/40 font-light select-none">|</span>
                                    <span class="text-xs font-medium tabular-nums">
                                        Stock: {p.stock ?? 0} {p.sale_type === 'weight' ? 'Kg' : 'u.'}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Lado Derecho: Ingresos Generados y Precio Unitario -->
                        <div class="flex flex-col items-end text-right shrink-0 pl-3">
                            <span class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Ingresos Generados</span>
                            <span class="text-lg sm:text-xl font-bold tabular-nums text-foreground">{formatMoney(p.total_subtotal)}</span>
                            {#if p.price && p.price > 0}
                                <span class="text-xs text-muted-foreground tabular-nums">
                                    P. Unit: {formatMoney(p.price)} {p.sale_type === 'weight' ? '/Kg' : ''}
                                </span>
                            {/if}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</div>
