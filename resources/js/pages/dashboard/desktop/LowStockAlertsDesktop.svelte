<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import MinimalDataTable from '@/components/common/MinimalDataTable.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Package from 'lucide-svelte/icons/package';
    import ExternalLink from 'lucide-svelte/icons/external-link';

    interface CategorySummary {
        id: number;
        name: string;
    }

    interface LowStockProduct {
        id: number;
        name: string;
        sku?: string | null;
        barcode?: string | null;
        stock: number;
        min_stock: number;
        image_url?: string | null;
        category?: CategorySummary | null;
    }

    let {
        products = [],
    }: {
        products?: LowStockProduct[];
    } = $props();
</script>

<div class="flex flex-col gap-3.5">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="flex size-8 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                <AlertTriangle class="size-4" />
            </div>
            <div>
                <h2 class="text-base font-semibold tracking-tight text-foreground sm:text-lg">Alertas de stock mínimo</h2>
                <p class="text-xs text-muted-foreground leading-relaxed">Productos que han alcanzado o están por debajo de su umbral de reposición.</p>
            </div>
        </div>

        {#if products.length > 0}
            <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/20 bg-rose-500/10 px-3 py-1 text-xs font-semibold tracking-wide tabular-nums text-rose-600 dark:text-rose-400">
                <span class="size-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                {products.length} {products.length === 1 ? 'producto crítico' : 'productos críticos'}
            </span>
        {/if}
    </div>

    <MinimalDataTable
        items={products}
        rowClass="bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20 hover:bg-rose-500/15"
    >
        {#snippet header()}
            <th class="py-3.5 px-6 font-semibold">Producto</th>
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
                            <Link
                                href={`/tenant/products/${product.id}/edit`}
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

            <!-- Columna: Stock Actual -->
            <td class="py-3.5 px-6">
                <span class="inline-flex items-center gap-1 rounded-md border border-rose-500/30 bg-rose-500/20 px-2.5 py-1 text-xs font-bold tabular-nums text-rose-700 dark:text-rose-300">
                    <span class="size-1.5 rounded-full bg-rose-500"></span>
                    {product.stock} {product.stock === 1 ? 'unidad' : 'unidades'}
                </span>
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
</div>
