<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Package from 'lucide-svelte/icons/package';
    import Edit2 from 'lucide-svelte/icons/edit-2';

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

<div class="flex flex-col gap-3">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="flex size-7 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                <AlertTriangle class="size-3.5" />
            </div>
            <h2 class="text-sm font-semibold tracking-tight text-foreground">Alertas de stock mínimo</h2>
        </div>

        {#if products.length > 0}
            <span class="inline-flex items-center gap-1 rounded-full border border-rose-500/20 bg-rose-500/10 px-2 py-0.5 text-xs font-semibold tracking-wide tabular-nums text-rose-600 dark:text-rose-400">
                <span class="size-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                {products.length}
            </span>
        {/if}
    </div>

    {#if products.length > 0}
        <div class="flex flex-col gap-2.5">
            {#each products as product (product.id)}
                <div class="flex flex-col gap-2.5 rounded-xl border border-rose-500/20 bg-rose-500/10 p-3.5 shadow-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/50 bg-background/60">
                                {#if product.image_url}
                                    <img src={product.image_url} alt={product.name} class="h-full w-full object-cover" />
                                {:else}
                                    <Package class="size-4 text-muted-foreground/60" />
                                {/if}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-xs text-foreground truncate">{product.name}</h4>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5">
                                    {#if product.category}
                                        <span class="rounded bg-background/80 px-1.5 py-0.5 font-medium text-foreground">
                                            {product.category.name}
                                        </span>
                                    {/if}
                                    {#if product.sku}
                                        <span class="font-mono tabular-nums">SKU: {product.sku}</span>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <Link
                            href={`/tenant/products/${product.id}/edit`}
                            class="flex size-7 items-center justify-center rounded-lg bg-background/60 text-muted-foreground hover:text-foreground border border-border/40"
                            title="Editar producto"
                        >
                            <Edit2 class="size-3" />
                        </Link>
                    </div>

                    <!-- Métricas de Stock: Actual vs Mínimo -->
                    <div class="grid grid-cols-2 gap-2 rounded-lg bg-background/50 p-2 border border-rose-500/15 text-xs">
                        <div class="flex flex-col">
                            <span class="text-xs font-medium tracking-wider text-muted-foreground uppercase">Stock actual</span>
                            <span class="font-mono font-bold tabular-nums text-rose-600 dark:text-rose-400">
                                {product.stock} {product.stock === 1 ? 'ud.' : 'uds.'}
                            </span>
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-xs font-medium tracking-wider text-muted-foreground uppercase">Stock mínimo</span>
                            <span class="font-mono font-semibold tabular-nums text-foreground">
                                {product.min_stock} {product.min_stock === 1 ? 'ud.' : 'uds.'}
                            </span>
                        </div>
                    </div>
                </div>
            {/each}
        </div>
    {:else}
        <!-- Estado Positivo Vacío -->
        <div class="flex flex-col items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-950/20 p-6 text-center shadow-xs">
            <div class="mb-2 flex size-10 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                <CheckCircle2 class="size-5" />
            </div>
            <h3 class="text-sm font-semibold text-foreground">Todo en orden</h3>
            <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed">
                No hay productos por debajo del stock mínimo.
            </p>
        </div>
    {/if}
</div>
