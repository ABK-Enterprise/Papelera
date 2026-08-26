<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import Trash2 from 'lucide-svelte/icons/trash-2';

    interface CategorySummary {
        id: number;
        name: string;
    }

    interface ProductItem {
        id: number;
        name: string;
        sale_type?: 'unit' | 'weight';
        sku: string | null;
        price: string | number;
        stock: number;
        min_stock: number;
        units_per_package?: number;
        package_quantity?: number;
        image_url: string | null;
        is_incomplete?: boolean;
        category?: CategorySummary | null;
    }

    let {
        products = [],
    }: {
        products: ProductItem[];
    } = $props();

    let page = $state(1);
    const perPage = 6;
    let paginatedProducts = $derived(products.slice((page - 1) * perPage, page * perPage));

    let productToDelete = $state<ProductItem | null>(null);
    let isDeleteModalOpen = $state(false);
    let isDeleting = $state(false);

    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function promptDelete(product: ProductItem) {
        productToDelete = product;
        isDeleteModalOpen = true;
    }

    function executeDelete() {
        if (!productToDelete) return;
        isDeleting = true;
        router.delete(`/tenant/products/${productToDelete.id}`, {
            onFinish: () => {
                isDeleting = false;
                isDeleteModalOpen = false;
                productToDelete = null;
            },
        });
    }
</script>

<div class="flex flex-col gap-3">
    {#each paginatedProducts as product (product.id)}
        {@const isIncomplete = product.is_incomplete ?? (!product.category || Number(product.price) <= 0)}
        {@const isWeighable = product.sale_type === 'weight'}
        <div class="flex items-center gap-3.5 rounded-xl border {isIncomplete ? 'border-amber-500/40 bg-amber-500/5' : 'border-border/70 bg-card'} p-3 shadow-sm">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-muted/60 border border-border/40 flex items-center justify-center">
                {#if product.image_url}
                    <img src={product.image_url} alt={product.name} class="h-full w-full object-cover" />
                {:else}
                    {#if isWeighable}
                        <Scale class="size-6 text-muted-foreground/60" />
                    {:else}
                        <Package class="size-6 text-muted-foreground/60" />
                    {/if}
                {/if}
            </div>

            <div class="flex flex-1 flex-col min-w-0 justify-center">
                <div class="flex items-center gap-1.5 truncate">
                    <h4 class="font-bold text-sm text-foreground truncate">{product.name}</h4>
                    {#if isWeighable}
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 shrink-0">
                            <Scale class="size-2.5" />
                            Kg
                        </span>
                    {/if}
                    {#if isIncomplete}
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 shrink-0">
                            <AlertTriangle class="size-2.5" />
                            Faltan datos
                        </span>
                    {/if}
                </div>
                <span class="font-bold text-base font-mono mt-0.5 {Number(product.price) <= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}">
                    {formatPrice(product.price)} {isWeighable ? '<span class="text-xs font-normal text-muted-foreground">/Kg</span>' : ''}
                </span>

                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    {#if product.stock <= 0}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400">
                            Agotado
                        </span>
                    {:else if product.stock <= product.min_stock}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                            Bajo Stock ({product.stock} {isWeighable ? 'Kg' : 'u'})
                        </span>
                    {:else}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            Stock: {product.stock} {isWeighable ? 'Kg' : 'u'}
                        </span>
                    {/if}
                    {#if product.sku}
                        <span class="font-mono text-[10px] text-muted-foreground truncate">
                            {product.sku}
                        </span>
                    {/if}
                    {#if isWeighable}
                        <span class="font-mono text-[10px] bg-muted/60 px-1 py-0.2 rounded text-muted-foreground">
                            1 B = {product.package_quantity || product.units_per_package || 1}Kg
                        </span>
                    {:else if product.units_per_package && product.units_per_package > 1}
                        <span class="font-mono text-[10px] bg-muted/60 px-1 py-0.2 rounded text-muted-foreground">
                            1 B = {product.units_per_package}u
                        </span>
                    {/if}
                </div>
            </div>

            <div class="flex flex-col gap-1 shrink-0">
                {#if isIncomplete}
                    <Link href={`/tenant/products/${product.id}/edit`}>
                        <Button size="sm" variant="default" class="h-7 px-2 text-[11px] font-bold bg-amber-600 hover:bg-amber-700 text-white">
                            Completar
                        </Button>
                    </Link>
                {:else}
                    <Link href={`/tenant/products/${product.id}/edit`}>
                        <Button size="icon" variant="ghost" class="h-8 w-8 text-muted-foreground">
                            <Edit2 class="size-3.5" />
                        </Button>
                    </Link>
                {/if}
                <Button
                    size="icon"
                    variant="ghost"
                    class="h-8 w-8 text-muted-foreground hover:text-destructive"
                    onclick={() => promptDelete(product)}
                >
                    <Trash2 class="size-3.5" />
                </Button>
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <div class="rounded-xl border border-dashed p-8 text-center bg-card/40">
            <Package class="mx-auto size-10 text-muted-foreground/50 mb-2" />
            <p class="text-xs text-muted-foreground">No se encontraron productos.</p>
            <Link href="/tenant/products/create" class="mt-3 inline-block">
                <Button size="sm">+ Nuevo Producto</Button>
            </Link>
        </div>
    {/if}

    {#if products.length > perPage}
        <AppPagination count={products.length} {perPage} bind:page />
    {/if}
</div>

<!-- Modal de Confirmación de Eliminación de Producto Mobile -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar producto?"
    description={`¿Estás seguro de eliminar "${productToDelete?.name}"? Esta acción no se puede deshacer.`}
    confirmText="Eliminar"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
