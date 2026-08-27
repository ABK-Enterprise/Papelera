<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { Button } from '@/components/ui/button';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
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

<div class="flex flex-col gap-3">
    {#each paginatedProducts as product (product.id)}
        {@const isWeighable = product.sale_type === 'weight'}
        {@const isIncomplete = Boolean(product.is_incomplete)}
        <div
            class="flex flex-col md:flex-row items-start md:items-center justify-between p-4 rounded-2xl border transition-all duration-200 gap-4 bg-card shadow-xs hover:shadow-md hover:border-border {isIncomplete ? 'border-amber-300 dark:border-amber-700/60 bg-amber-50/20 dark:bg-amber-950/10' : 'border-border/70'}"
        >
            <!-- Lado Izquierdo: Imagen / Avatar Placeholder -->
            <div class="flex items-center gap-4 min-w-0 flex-1">
                <div class="relative size-16 rounded-xl bg-muted/60 border border-border/60 flex items-center justify-center shrink-0 overflow-hidden shadow-2xs">
                    {#if product.image_url}
                        <img
                            src={product.image_url}
                            alt={product.name}
                            class="h-full w-full object-cover"
                        />
                    {:else if isWeighable}
                        <Scale class="h-7 w-7 text-primary/70 stroke-[1.75]" />
                    {:else}
                        <Package class="h-7 w-7 text-muted-foreground/60 stroke-[1.75]" />
                    {/if}

                    {#if isWeighable}
                        <span class="absolute bottom-0 right-0 bg-primary text-primary-foreground text-[9px] font-bold px-1 rounded-tl">
                            Kg
                        </span>
                    {/if}
                </div>

                <!-- Info Principal -->
                <div class="space-y-1 min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="font-bold text-base text-foreground truncate">
                            {product.name}
                        </h4>

                        {#if isWeighable}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold bg-primary/10 text-primary border border-primary/20">
                                <Scale class="h-3 w-3" />
                                Pesable
                            </span>
                        {/if}

                        {#if isIncomplete}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                <AlertTriangle class="h-3 w-3 text-amber-600" />
                                Requiere Completar Datos
                            </span>
                        {/if}
                    </div>

                    <!-- Código de Barras / SKU / Categoría / Bulto -->
                    <div class="flex items-center gap-2.5 text-xs text-muted-foreground flex-wrap">
                        {#if product.barcode}
                            <span class="font-mono bg-muted/60 px-1.5 py-0.5 rounded text-[11px]">
                                BAR: {product.barcode}
                            </span>
                        {/if}
                        {#if product.sku}
                            <span class="font-mono bg-muted/60 px-1.5 py-0.5 rounded text-[11px]">
                                SKU: {product.sku}
                            </span>
                        {/if}
                        {#if product.category}
                            <span>
                                Cat: <strong class="text-foreground">{product.category.name}</strong>
                            </span>
                        {:else}
                            <span class="text-amber-600 dark:text-amber-400 font-medium">
                                Sin categoría
                            </span>
                        {/if}
                        {#if isWeighable}
                            <span class="inline-flex items-center gap-1 font-mono text-[11px] bg-muted/70 px-1.5 py-0.5 rounded text-foreground tabular-nums">
                                <Layers class="h-3 w-3 text-primary" />
                                1 Bulto = {product.package_quantity || product.units_per_package || 1} Kg
                            </span>
                        {:else if product.units_per_package && product.units_per_package > 1}
                            <span class="inline-flex items-center gap-1 font-mono text-[11px] bg-muted/70 px-1.5 py-0.5 rounded text-foreground tabular-nums">
                                <Layers class="h-3 w-3 text-primary" />
                                1 Bulto = {product.units_per_package} unid.
                            </span>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Centro: Precios y Stock con Tabular Numbers -->
            <div class="flex items-center gap-6 px-2">
                <div class="flex flex-col items-end">
                    <span class="text-xs text-muted-foreground">Precio de Venta</span>
                    <span class="font-bold text-base text-foreground tabular-nums">
                        {formatPrice(product.price)} {isWeighable ? '/Kg' : ''}
                    </span>
                    {#if Number(product.cost_price) > 0}
                        <span class="text-[11px] text-muted-foreground tabular-nums">
                            Costo: {formatPrice(product.cost_price)}
                        </span>
                    {/if}
                </div>

                <div class="flex flex-col items-end min-w-[100px]">
                    <span class="text-xs text-muted-foreground">Stock Físico</span>
                    {#if product.stock <= 0}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 tabular-nums">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Agotado
                        </span>
                    {:else if product.stock <= product.min_stock}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 tabular-nums">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            {product.stock} {isWeighable ? 'Kg' : 'unid.'}
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 tabular-nums">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            {product.stock} {isWeighable ? 'Kg' : 'unid.'}
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Botones de Acción con Touch Target >= 40px -->
            <div class="flex items-center justify-end gap-2 shrink-0">
                {#if isIncomplete}
                    <Link href={`/tenant/products/${product.id}/edit`}>
                        <Button size="sm" class="min-h-[40px] px-3.5 text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white gap-1.5 rounded-xl shadow-xs">
                            <Edit2 class="h-3.5 w-3.5" />
                            <span>Completar Datos</span>
                        </Button>
                    </Link>
                {:else}
                    <Link href={`/tenant/products/${product.id}/edit`}>
                        <Button
                            variant="outline"
                            size="sm"
                            class="min-h-[40px] min-w-[40px] px-3 gap-1.5 rounded-xl border-border/70 text-xs font-medium hover:bg-muted/60"
                        >
                            <Edit2 class="h-3.5 w-3.5" />
                            <span>Editar</span>
                        </Button>
                    </Link>
                {/if}
                <Button
                    variant="ghost"
                    size="sm"
                    class="min-h-[40px] min-w-[40px] px-3 gap-1.5 rounded-xl text-xs font-medium text-destructive hover:bg-destructive/10 hover:text-destructive"
                    onclick={() => promptDelete(product)}
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    <span>Eliminar</span>
                </Button>
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron productos"
            description="No hay productos que coincidan con los filtros seleccionados o el catálogo de tu comercio está vacío."
            actionLabel="Crear Primer Producto"
            actionHref="/tenant/products/create"
        />
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
