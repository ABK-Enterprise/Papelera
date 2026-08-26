<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Layers from 'lucide-svelte/icons/layers';
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
        barcode: string | null;
        price: string | number;
        cost_price: string | number | null;
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
    const perPage = 10;
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

<div class="flex flex-col gap-4">
    {#each paginatedProducts as product (product.id)}
        {@const isIncomplete = product.is_incomplete ?? (!product.category || Number(product.price) <= 0)}
        {@const isWeighable = product.sale_type === 'weight'}
        <div class="bento-card group flex flex-col md:flex-row items-center gap-5 rounded-xl border {isIncomplete ? 'border-amber-500/40 bg-amber-500/5' : 'border-border/60 bg-card'} p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] transition-all hover:border-primary/30 hover:shadow-md">
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
                        {#if isWeighable}
                            <Scale class="size-8 stroke-[1.5]" />
                        {:else}
                            <Package class="size-8 stroke-[1.5]" />
                        {/if}
                        <span class="text-[10px]">Sin foto</span>
                    </div>
                {/if}
            </div>

            <!-- Detalles del Producto -->
            <div class="flex flex-1 flex-col justify-center min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 mb-1.5">
                    <div class="flex items-center gap-2 truncate pr-2">
                        <h4 class="font-bold text-base text-foreground truncate">
                            {product.name}
                        </h4>
                        {#if isWeighable}
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 shrink-0">
                                <Scale class="size-3" />
                                Por Pesaje (Kg)
                            </span>
                        {/if}
                        {#if isIncomplete}
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30 shrink-0">
                                <AlertTriangle class="size-3" />
                                Datos incompletos
                            </span>
                        {/if}
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-lg font-mono {Number(product.price) <= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}">
                            {formatPrice(product.price)}
                        </span>
                        {#if isWeighable}
                            <span class="text-xs text-muted-foreground block -mt-1 font-mono">/ Kg</span>
                        {/if}
                    </div>
                </div>

                <div class="flex items-center gap-3 text-xs text-muted-foreground mb-3 flex-wrap">
                    {#if product.sku}
                        <span class="font-mono bg-muted/60 px-1.5 py-0.5 rounded text-[11px]">
                            SKU: {product.sku}
                        </span>
                    {/if}
                    {#if product.category}
                        <span class="truncate">
                            Cat: <strong class="text-foreground">{product.category.name}</strong>
                        </span>
                    {:else}
                        <span class="text-amber-600 dark:text-amber-400 font-medium">
                            Sin categoría
                        </span>
                    {/if}
                    {#if isWeighable}
                        <span class="inline-flex items-center gap-1 font-mono text-[11px] bg-muted/70 px-1.5 py-0.5 rounded text-foreground">
                            <Layers class="size-3 text-primary" />
                            1 Bulto = {product.package_quantity || product.units_per_package || 1} Kg
                        </span>
                    {:else if product.units_per_package && product.units_per_package > 1}
                        <span class="inline-flex items-center gap-1 font-mono text-[11px] bg-muted/70 px-1.5 py-0.5 rounded text-foreground">
                            <Layers class="size-3 text-primary" />
                            1 Bulto = {product.units_per_package} unid.
                        </span>
                    {/if}
                </div>

                <!-- Badges de Stock -->
                <div class="flex items-center gap-2">
                    {#if product.stock <= 0}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200/50">
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                            Agotado
                        </span>
                    {:else if product.stock <= product.min_stock}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200/50">
                            <span class="size-1.5 rounded-full bg-amber-500"></span>
                            Bajo Stock ({product.stock} {isWeighable ? 'Kg' : 'unid.'})
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50">
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                            Stock: {product.stock} {isWeighable ? 'Kg' : 'unidades'}
                        </span>
                    {/if}

                    {#if Number(product.cost_price) > 0}
                        <span class="text-xs text-muted-foreground">
                            Costo: <strong class="font-mono text-foreground">{formatPrice(product.cost_price)} {isWeighable ? '/Kg' : ''}</strong>
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex md:flex-col items-center justify-end gap-2 shrink-0 w-full md:w-auto border-t md:border-t-0 pt-2 md:pt-0">
                {#if isIncomplete}
                    <Link href={`/tenant/products/${product.id}/edit`}>
                        <Button size="sm" variant="default" class="h-8 text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white gap-1 shadow-2xs">
                            <Edit2 class="size-3" />
                            <span>Completar</span>
                        </Button>
                    </Link>
                {:else}
                    <Link href={`/tenant/products/${product.id}/edit`}>
                        <Button size="icon" variant="ghost" class="size-9 text-muted-foreground hover:text-foreground" title="Editar Producto">
                            <Edit2 class="size-4" />
                        </Button>
                    </Link>
                {/if}
                <Button
                    size="icon"
                    variant="ghost"
                    class="size-9 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                    title="Eliminar Producto"
                    onclick={() => promptDelete(product)}
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <div class="rounded-xl border border-dashed p-12 text-center bg-card/50">
            <Package class="mx-auto size-12 text-muted-foreground/50 mb-3" />
            <h3 class="font-semibold text-lg text-foreground">No se encontraron productos</h3>
            <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay productos que coincidan con los filtros seleccionados o el catálogo está vacío.
            </p>
            <Link href="/tenant/products/create" class="mt-4 inline-block">
                <Button class="gap-2">+ Crear Primer Producto</Button>
            </Link>
        </div>
    {/if}

    {#if products.length > perPage}
        <AppPagination count={products.length} {perPage} bind:page />
    {/if}
</div>

<!-- Modal de Confirmación de Eliminación de Producto -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar producto del catálogo?"
    description={`¿Estás seguro de eliminar "${productToDelete?.name}"? Esta acción no se puede deshacer y removerá el artículo del inventario.`}
    confirmText="Eliminar Producto"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
