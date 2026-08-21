<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Package from 'lucide-svelte/icons/package';

    interface CategorySummary {
        id: number;
        name: string;
    }

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
        price: string | number;
        stock: number;
        min_stock: number;
        image_url: string | null;
        category?: CategorySummary | null;
    }

    let {
        products = [],
    }: {
        products: ProductItem[];
    } = $props();

    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function deleteProduct(product: ProductItem) {
        if (confirm(`¿Eliminar "${product.name}"?`)) {
            router.delete(`/tenant/products/${product.id}`);
        }
    }
</script>

<div class="flex flex-col gap-3">
    {#each products as product (product.id)}
        <div class="flex items-center gap-3.5 rounded-xl border border-border/70 bg-card p-3 shadow-sm">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-muted/60 border border-border/40 flex items-center justify-center">
                {#if product.image_url}
                    <img src={product.image_url} alt={product.name} class="h-full w-full object-cover" />
                {:else}
                    <Package class="h-6 w-6 text-muted-foreground/60" />
                {/if}
            </div>

            <div class="flex flex-1 flex-col min-w-0 justify-center">
                <h4 class="font-bold text-sm text-foreground truncate">{product.name}</h4>
                <span class="font-bold text-base text-foreground font-mono mt-0.5">
                    {formatPrice(product.price)}
                </span>

                <div class="flex items-center gap-2 mt-1.5">
                    {#if product.stock <= 0}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400">
                            Agotado
                        </span>
                    {:else if product.stock <= product.min_stock}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                            Bajo Stock ({product.stock})
                        </span>
                    {:else}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            Stock: {product.stock}
                        </span>
                    {/if}
                    {#if product.sku}
                        <span class="font-mono text-[10px] text-muted-foreground truncate">
                            {product.sku}
                        </span>
                    {/if}
                </div>
            </div>

            <div class="flex flex-col gap-1 shrink-0">
                <Link href={`/tenant/products/${product.id}/edit`}>
                    <Button size="icon" variant="ghost" class="h-8 w-8 text-muted-foreground">
                        <Edit2 class="h-3.5 w-3.5" />
                    </Button>
                </Link>
                <Button
                    size="icon"
                    variant="ghost"
                    class="h-8 w-8 text-muted-foreground hover:text-destructive"
                    onclick={() => deleteProduct(product)}
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <div class="rounded-xl border border-dashed p-8 text-center bg-card/40">
            <Package class="mx-auto h-10 w-10 text-muted-foreground/50 mb-2" />
            <p class="text-xs text-muted-foreground">No se encontraron productos.</p>
            <Link href="/tenant/products/create" class="mt-3 inline-block">
                <Button size="sm">+ Nuevo Producto</Button>
            </Link>
        </div>
    {/if}
</div>
