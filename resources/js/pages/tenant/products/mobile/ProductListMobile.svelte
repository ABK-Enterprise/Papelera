<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
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
        warehouses_count?: number;
        category?: CategorySummary | null;
    }


    let {
        products = [],
        searchQuery = '',
    }: {
        products: ProductItem[];
        searchQuery?: string;
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
        router.delete(`/tenant/productos/${productToDelete.id}`, {
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
        <div class="flex items-center gap-3.5 rounded-2xl border transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25 {isIncomplete ? 'border-amber-500/40 bg-amber-500/5' : 'border-border/70 bg-card'} p-3.5 shadow-2xs">

            {#if product.image_url}
                <div class="size-12 shrink-0 rounded-xl border border-border/80 overflow-hidden">
                    <img src={product.image_url} alt={product.name} class="h-full w-full object-cover" />
                </div>
            {:else}
                <IconContainer
                    size="lg"
                    variant={isWeighable ? 'info' : 'primary'}
                    icon={isWeighable ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                />
            {/if}

            <div class="flex flex-1 flex-col min-w-0 justify-center">
                <div class="flex items-center gap-1.5 truncate">
                    <h4 class="font-semibold text-sm text-foreground truncate leading-snug">
                        <HighlightMatch text={product.name} query={searchQuery} />
                    </h4>
                    {#if isWeighable}
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-primary/10 text-primary border border-primary/20 shrink-0 tabular-nums">
                            <Scale class="h-2.5 w-2.5" />
                            Kg
                        </span>
                    {/if}
                    {#if isIncomplete}
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30 shrink-0">
                            <AlertTriangle class="h-2.5 w-2.5" />
                            Faltan datos
                        </span>
                    {/if}
                </div>
                <span class="font-semibold text-base mt-0.5 tabular-nums {Number(product.price) <= 0 ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}">
                    {formatPrice(product.price)} {isWeighable ? '/Kg' : ''}
                </span>
                {#if (product.effective_bulk_discount_percentage ?? 0) > 0}
                    <span class="text-[11px] font-medium text-emerald-600 dark:text-emerald-400 tabular-nums">
                        Bulto (-{product.effective_bulk_discount_percentage}%): {formatPrice(product.bulk_discount_price ?? product.price)}
                    </span>
                {/if}

                <div class="flex items-center gap-1.5 mt-1.5 flex-wrap text-xs">
                    {#if product.stock <= 0}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 tabular-nums">
                            Agotado
                        </span>
                    {:else if product.stock <= product.min_stock}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 tabular-nums">
                            Bajo stock ({product.stock} {isWeighable ? 'Kg' : 'u'})
                        </span>
                    {:else}
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 tabular-nums">
                            Stock: {product.stock} {isWeighable ? 'Kg' : 'u'}
                        </span>
                    {/if}

                    <span class="text-muted-foreground/40 font-light select-none">|</span>
                    <span class="text-xs text-muted-foreground tabular-nums">
                        {product.warehouses_count === 1 ? '1 depósito' : `${product.warehouses_count ?? 0} depósitos`}
                    </span>

                    {#if product.sku}
                        <span class="text-muted-foreground/40 font-light select-none">|</span>
                        <span class="text-xs text-muted-foreground tracking-wide truncate tabular-nums">
                            <HighlightMatch text={product.sku} query={searchQuery} />
                        </span>
                    {/if}

                </div>
            </div>


            <div class="flex flex-col gap-1.5 shrink-0">
                {#if isIncomplete}
                    <Link
                        href={`/tenant/productos/${product.id}/editar`}
                        class="inline-flex items-center justify-center min-h-[44px] px-3 text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white rounded-xl transition-colors"
                    >
                        <span>Completar datos</span>
                    </Link>
                {:else}
                    <Link
                        href={`/tenant/productos/${product.id}/editar`}
                        class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] p-0 rounded-xl border border-border/70 text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors shadow-2xs"
                    >
                        <Edit2 class="h-4 w-4" />
                        <span class="sr-only">Editar</span>
                    </Link>
                {/if}
                <Button
                    variant="ghost"
                    class="min-h-[44px] min-w-[44px] p-0 rounded-xl text-muted-foreground hover:bg-destructive/10 hover:text-destructive justify-center"
                    onclick={() => promptDelete(product)}
                >
                    <Trash2 class="h-4 w-4" />
                    <span class="sr-only">Eliminar</span>
                </Button>
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron productos"
            description="No hay productos disponibles en tu catálogo o filtros seleccionados."
            actionLabel="Crear producto"
            actionHref="/tenant/productos/crear"
        />
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
    confirmText="Eliminar producto"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>
