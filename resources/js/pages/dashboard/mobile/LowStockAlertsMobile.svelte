<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Package from 'lucide-svelte/icons/package';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Building2 from 'lucide-svelte/icons/building-2';

    interface CategorySummary {
        id: number;
        name: string;
    }

    interface WarehouseSummary {
        id: number;
        name: string;
        code?: string | null;
    }

    interface LowStockProduct {
        id: number;
        product_warehouse_id?: number | null;
        name: string;
        sku?: string | null;
        barcode?: string | null;
        stock: number;
        min_stock: number;
        image_url?: string | null;
        category?: CategorySummary | null;
        warehouse?: WarehouseSummary | null;
        is_critical?: boolean;
    }

    import { Button } from '@/components/ui/button';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';

    let {
        products = [],
    }: {
        products?: LowStockProduct[];
    } = $props();

    const perPage = 4;
    let currentPage = $state(1);

    let totalPages = $derived(Math.max(1, Math.ceil(products.length / perPage)));
    let paginatedProducts = $derived(products.slice((currentPage - 1) * perPage, currentPage * perPage));

    $effect(() => {
        if (currentPage > totalPages) {
            currentPage = totalPages;
        }
    });

    let startItem = $derived(products.length === 0 ? 0 : (currentPage - 1) * perPage + 1);
    let endItem = $derived(Math.min(currentPage * perPage, products.length));
    let criticalCount = $derived(products.filter((p) => p.is_critical || p.stock < 0).length);
    let minimumCount = $derived(products.filter((p) => !(p.is_critical || p.stock < 0)).length);
</script>

<div class="flex flex-col gap-3">
    <!-- Encabezado con Icono Danger, Título y Stack Vertical de Pills [X mínimo, Y crítico] -->
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0 flex-1">
            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                <AlertTriangle class="size-4" />
            </div>
            <h2 class="text-sm font-semibold tracking-tight text-foreground leading-snug break-words">
                Alertas de stock mínimo y crítico
            </h2>
        </div>

        {#if products.length > 0}
            <div class="flex flex-col items-end gap-1 shrink-0">
                <span class="inline-flex items-center justify-center rounded-full border border-amber-500/30 bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold tracking-wide tabular-nums text-amber-700 dark:text-amber-400 shrink-0 whitespace-nowrap">
                    {minimumCount} mínimo
                </span>
                <span class="inline-flex items-center justify-center gap-1 rounded-full border border-rose-600/30 bg-rose-600/15 px-2.5 py-0.5 text-xs font-bold tracking-wide tabular-nums text-rose-700 dark:text-rose-400 shrink-0 whitespace-nowrap">
                    {#if criticalCount > 0}
                        <span class="size-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                    {/if}
                    {criticalCount} crítico
                </span>
            </div>
        {/if}
    </div>

    {#if products.length > 0}
        <div class="flex flex-col gap-2.5">
            {#each paginatedProducts as product (product.product_warehouse_id ?? product.id)}
                {@const isCrit = product.is_critical || product.stock < 0}
                <div class={`flex flex-col gap-2.5 rounded-xl border p-3.5 shadow-xs ${
                    isCrit
                        ? 'border-rose-600/35 bg-rose-500/12 dark:bg-rose-500/15 text-rose-700 dark:text-rose-400'
                        : 'border-border/80 dark:border-border/90 bg-card text-rose-700 dark:text-rose-400'
                }`}>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35">
                                {#if product.image_url}
                                    <img src={product.image_url} alt={product.name} class="h-full w-full object-cover rounded-full" />
                                {:else}
                                    <Package class="size-4 text-muted-foreground/60" />
                                {/if}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h4 class="text-sm font-semibold text-foreground truncate">{product.name}</h4>
                                    {#if isCrit}
                                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold bg-rose-600 text-white uppercase tracking-wider">
                                            Crítico
                                        </span>
                                    {/if}
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5 flex-wrap">
                                    {#if product.category}
                                        <span class="rounded bg-muted/40 dark:bg-secondary/35 border border-border/80 dark:border-border/90 px-1.5 py-0.5 font-medium text-foreground">
                                            {product.category.name}
                                        </span>
                                    {/if}
                                    {#if product.sku}
                                        <span class="tabular-nums">SKU: {product.sku}</span>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <Link
                            href={`/tenant/productos/${product.id}/editar`}
                            class="flex size-9 items-center justify-center rounded-full bg-muted/40 dark:bg-secondary/35 text-muted-foreground hover:text-foreground border border-border/80 dark:border-border/90 hover:bg-muted/70 dark:hover:bg-secondary/55 transition-colors shrink-0 shadow-2xs cursor-pointer"
                            title="Editar producto"
                        >
                            <Edit2 class="size-3.5" />
                        </Link>
                    </div>

                    <!-- Fila de Depósito y Métricas de Stock -->
                    <div class="flex items-center justify-between gap-2 pt-0.5">
                        {#if product.warehouse}
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-muted/40 dark:bg-secondary/35 text-foreground border border-border/80 dark:border-border/90 shadow-2xs">
                                <Building2 class="size-3 text-muted-foreground shrink-0" />
                                {product.warehouse.name}
                            </span>
                        {:else}
                            <span class="text-xs text-muted-foreground italic">General</span>
                        {/if}
                    </div>

                    <div class={`grid grid-cols-2 gap-2 rounded-lg bg-muted/40 dark:bg-secondary/35 p-2.5 border ${
                        isCrit ? 'border-rose-600/30' : 'border-border/80 dark:border-border/90'
                    }`}>
                        <div class="flex flex-col">
                            <span class="text-xs font-medium tracking-wider text-muted-foreground uppercase">Stock actual</span>
                            <span class={`text-sm font-bold tabular-nums ${
                                isCrit ? 'text-rose-700 dark:text-rose-400 font-extrabold' : 'text-rose-700 dark:text-rose-400'
                            }`}>
                                {#if isCrit}
                                    <span class="inline-block size-1.5 rounded-full bg-rose-600 animate-pulse mr-1 align-middle"></span>
                                {/if}
                                {product.stock} {product.stock === 1 ? 'ud.' : 'uds.'}
                            </span>
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-xs font-medium tracking-wider text-muted-foreground uppercase">Stock mínimo</span>
                            <span class="text-sm font-bold tabular-nums text-foreground">
                                {product.min_stock} {product.min_stock === 1 ? 'ud.' : 'uds.'}
                            </span>
                        </div>
                    </div>
                </div>
            {/each}
        </div>

        {#if totalPages > 1}
            <div class="flex items-center justify-between pt-2 px-1 text-xs text-muted-foreground">
                <span class="tabular-nums">
                    Mostrando <strong class="text-foreground">{startItem}</strong> a <strong class="text-foreground">{endItem}</strong> de <strong class="text-foreground">{products.length}</strong>
                </span>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="min-h-[44px] px-3 gap-1 rounded-xl text-sm font-medium cursor-pointer"
                        disabled={currentPage <= 1}
                        onclick={() => currentPage--}
                        aria-label="Página anterior"
                    >
                        <ChevronLeft class="size-4" />
                        <span class="hidden sm:inline">Anterior</span>
                    </Button>

                    <span class="tabular-nums px-1.5 font-medium text-foreground">
                        {currentPage} / {totalPages}
                    </span>

                    <Button
                        variant="outline"
                        size="sm"
                        class="min-h-[44px] px-3 gap-1 rounded-xl text-sm font-medium cursor-pointer"
                        disabled={currentPage >= totalPages}
                        onclick={() => currentPage++}
                        aria-label="Página siguiente"
                    >
                        <span class="hidden sm:inline">Siguiente</span>
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        {/if}
    {:else}
        <!-- Estado Positivo Vacío -->
        <div class="flex flex-col items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-950/20 p-6 text-center shadow-xs">
            <div class="mb-2 flex size-10 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 class="size-5" />
            </div>
            <h3 class="text-sm font-semibold text-foreground">Todo en orden</h3>
            <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed">
                No hay productos por debajo del stock mínimo.
            </p>
        </div>
    {/if}
</div>
