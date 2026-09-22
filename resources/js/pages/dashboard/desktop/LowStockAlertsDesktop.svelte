<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import MinimalDataTable from '@/components/common/MinimalDataTable.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Package from 'lucide-svelte/icons/package';
    import ExternalLink from 'lucide-svelte/icons/external-link';
    import Building2 from 'lucide-svelte/icons/building-2';

    import { Button } from '@/components/ui/button';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';

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

    let {
        products = [],
    }: {
        products?: LowStockProduct[];
    } = $props();

    const perPage = 9;
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
</script>

<div class="flex flex-col gap-3.5">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                <AlertTriangle class="size-4" />
            </div>
            <div>
                <h2 class="text-base font-semibold tracking-tight text-foreground sm:text-lg">Alertas de stock mínimo y crítico</h2>
            </div>
        </div>

        {#if products.length > 0}
            <div class="flex items-center gap-2">
                {#if criticalCount > 0}
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-600/30 bg-rose-600/15 px-3 py-1 text-xs font-bold tracking-wide tabular-nums text-rose-700 dark:text-rose-300">
                        <span class="size-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                        {criticalCount} {criticalCount === 1 ? 'crítico (negativo)' : 'críticos (negativos)'}
                    </span>
                {/if}
                <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/20 bg-rose-500/10 px-3 py-1 text-xs font-semibold tracking-wide tabular-nums text-rose-600 dark:text-rose-400">
                    {products.length} {products.length === 1 ? 'alerta en total' : 'alertas en total'}
                </span>
            </div>
        {/if}
    </div>

    <MinimalDataTable
        items={paginatedProducts}
        rowClass={(product) => (product.is_critical || product.stock < 0)
            ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 hover:bg-rose-500/20'
            : 'bg-rose-500/8 text-rose-600 dark:text-rose-400 border-rose-500/15 hover:bg-rose-500/12'}
    >
        {#snippet header()}
            <th class="py-3.5 px-6 font-semibold">Producto</th>
            <th class="py-3.5 px-6 font-semibold">Depósito</th>
            <th class="py-3.5 px-6 font-semibold">Stock actual</th>
            <th class="py-3.5 px-6 font-semibold text-right">Stock mínimo</th>
        {/snippet}

        {#snippet row(product)}
            <!-- Columna: Producto -->
            <td class="py-3.5 px-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/50 bg-muted/60">
                        {#if product.image_url}
                            <img src={product.image_url} alt={product.name} class="h-full w-full object-cover" />
                        {:else}
                            <Package class="size-4 text-muted-foreground/60" />
                        {/if}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-foreground truncate max-w-xs">{product.name}</span>
                            {#if product.is_critical || product.stock < 0}
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-600 text-white uppercase tracking-wider">
                                    Crítico
                                </span>
                            {/if}
                            <Link
                                href={`/tenant/productos/${product.id}/editar`}
                                class="text-muted-foreground hover:text-foreground transition-colors"
                                title="Editar producto"
                            >
                                <ExternalLink class="size-3.5" />
                            </Link>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                            {#if product.category}
                                <span class="rounded bg-muted/70 px-1.5 py-0.5 text-xs font-medium text-foreground">
                                    {product.category.name}
                                </span>
                            {/if}
                            {#if product.sku}
                                <span class="text-xs tabular-nums">SKU: {product.sku}</span>
                            {/if}
                        </div>
                    </div>
                </div>
            </td>

            <!-- Columna: Depósito -->
            <td class="py-3.5 px-6">
                {#if product.warehouse}
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-muted/80 text-foreground border border-border/80 shadow-2xs">
                        <Building2 class="size-3.5 text-muted-foreground shrink-0" />
                        {product.warehouse.name}
                    </span>
                {:else}
                    <span class="text-xs text-muted-foreground italic">General</span>
                {/if}
            </td>

            <!-- Columna: Stock Actual -->
            <td class="py-3.5 px-6">
                {#if product.is_critical || product.stock < 0}
                    <span class="inline-flex items-center gap-1 rounded-md border border-rose-600/40 bg-rose-600/25 px-2.5 py-1 text-xs font-extrabold tabular-nums text-rose-800 dark:text-rose-200">
                        <span class="size-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                        {product.stock} {product.stock === 1 ? 'unidad' : 'unidades'}
                    </span>
                {:else}
                    <span class="inline-flex items-center gap-1 rounded-md border border-rose-500/30 bg-rose-500/20 px-2.5 py-1 text-xs font-bold tabular-nums text-rose-700 dark:text-rose-300">
                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                        {product.stock} {product.stock === 1 ? 'unidad' : 'unidades'}
                    </span>
                {/if}
            </td>

            <!-- Columna: Stock Mínimo -->
            <td class="py-3.5 px-6 text-right">
                <span class="text-xs font-semibold tabular-nums text-foreground">
                    {product.min_stock} {product.min_stock === 1 ? 'unidad' : 'unidades'}
                </span>
            </td>
        {/snippet}

        {#snippet empty()}
            <div class="flex flex-col items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-950/20 p-8 text-center shadow-xs">
                <div class="mb-3 flex size-12 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-foreground">Todo en orden</h3>
                <p class="mt-1 text-sm text-muted-foreground max-w-md leading-relaxed">
                    No hay productos por debajo del stock mínimo.
                </p>
            </div>
        {/snippet}
    </MinimalDataTable>

    {#if totalPages > 1}
        <div class="flex items-center justify-between pt-2 px-1 text-xs text-muted-foreground">
            <span class="tabular-nums">
                Mostrando <strong class="text-foreground">{startItem}</strong> a <strong class="text-foreground">{endItem}</strong> de <strong class="text-foreground">{products.length}</strong> alertas
            </span>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-[36px] px-3 gap-1 rounded-lg text-xs font-medium cursor-pointer"
                    disabled={currentPage <= 1}
                    onclick={() => currentPage--}
                >
                    <ChevronLeft class="size-3.5" />
                    <span>Anterior</span>
                </Button>

                <span class="tabular-nums px-2 font-medium text-foreground">
                    Página {currentPage} de {totalPages}
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-[36px] px-3 gap-1 rounded-lg text-xs font-medium cursor-pointer"
                    disabled={currentPage >= totalPages}
                    onclick={() => currentPage++}
                >
                    <span>Siguiente</span>
                    <ChevronRight class="size-3.5" />
                </Button>
            </div>
        </div>
    {/if}
</div>
