<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import { Badge } from '@/components/ui/badge';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Package from 'lucide-svelte/icons/package';
    import ImageOff from 'lucide-svelte/icons/image-off';

    interface CategorySummary {
        id: number;
        name: string;
    }

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
        barcode: string | null;
        price: string | number;
        cost_price: string | number | null;
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
        if (confirm(`¿Estás seguro de eliminar el producto "${product.name}"?`)) {
            router.delete(`/tenant/products/${product.id}`);
        }
    }
</script>

<div class="flex flex-col gap-4">
    {#each products as product (product.id)}
        <div class="bento-card group flex flex-col md:flex-row items-center gap-5 rounded-xl border border-border/60 bg-card p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] transition-all hover:border-primary/30 hover:shadow-md">
            <!-- Imagen del Producto -->
            <div class="h-28 w-28 shrink-0 overflow-hidden rounded-lg bg-muted/50 border border-border/40 flex items-center justify-center">
                {#if product.image_url}
                    <img
                        src={product.image_url}
                        alt={product.name}
                        class="h-full w-full object-cover transition-transform group-hover:scale-105"
                    />
                {:else}
                    <div class="flex flex-col items-center justify-center text-muted-foreground/60 gap-1">
                        <Package class="h-8 w-8 stroke-[1.5]" />
                        <span class="text-[10px]">Sin foto</span>
                    </div>
                {/if}
            </div>

            <!-- Detalles del Producto -->
            <div class="flex flex-1 flex-col justify-center min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 mb-1.5">
                    <h4 class="font-bold text-base text-foreground truncate pr-2">
                        {product.name}
                    </h4>
                    <span class="font-bold text-lg text-foreground font-mono">
                        {formatPrice(product.price)}
                    </span>
                </div>

                <div class="flex items-center gap-3 text-xs text-muted-foreground mb-3">
                    {#if product.sku}
                        <span class="font-mono bg-muted/60 px-1.5 py-0.5 rounded text-[11px]">
                            SKU: {product.sku}
                        </span>
                    {/if}
                    {#if product.category}
                        <span class="truncate">
                            Cat: <strong class="text-foreground">{product.category.name}</strong>
                        </span>
                    {/if}
                </div>

                <!-- Badge de Stock -->
                <div class="flex items-center gap-2">
                    {#if product.stock <= 0}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200/50">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Agotado
                        </span>
                    {:else if product.stock <= product.min_stock}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200/50">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            Bajo Stock ({product.stock})
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            En Stock ({product.stock})
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex md:flex-col items-center justify-end gap-1.5 shrink-0 w-full md:w-auto border-t md:border-t-0 pt-2 md:pt-0">
                <Link href={`/tenant/products/${product.id}/edit`}>
                    <Button size="icon" variant="ghost" class="h-9 w-9 text-muted-foreground hover:text-foreground" title="Editar Producto">
                        <Edit2 class="h-4 w-4" />
                    </Button>
                </Link>
                <Button
                    size="icon"
                    variant="ghost"
                    class="h-9 w-9 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                    title="Eliminar Producto"
                    onclick={() => deleteProduct(product)}
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <div class="rounded-xl border border-dashed p-12 text-center bg-card/50">
            <Package class="mx-auto h-12 w-12 text-muted-foreground/50 mb-3" />
            <h3 class="font-semibold text-lg text-foreground">No se encontraron productos</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay productos que coincidan con los filtros seleccionados o el catálogo está vacío.
            </p>
            <Link href="/tenant/products/create" class="mt-4 inline-block">
                <Button class="gap-2">+ Crear Primer Producto</Button>
            </Link>
        </div>
    {/if}
</div>
