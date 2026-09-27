<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { hasPermission } from '@/lib/permissions';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Camera from 'lucide-svelte/icons/camera';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import MoreHorizontal from 'lucide-svelte/icons/more-horizontal';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import ProductImageQuickEditModal from '@/components/tenant/products/ProductImageQuickEditModal.svelte';

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
        thumbnail_url?: string | null;
        image_position?: { zoom: number; x: number; y: number } | null;
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

    let selectedImageProduct = $state<ProductItem | null>(null);
    let isImageModalOpen = $state(false);

    function openImageModal(product: ProductItem) {
        selectedImageProduct = product;
        isImageModalOpen = true;
    }

    function handleImageUpdated(updatedProduct: any) {
        if (!updatedProduct) return;
        const target = products.find((p) => p.id === updatedProduct.id);
        if (target) {
            target.image_url = updatedProduct.image_url;
            target.thumbnail_url = updatedProduct.thumbnail_url;
            target.image_position = updatedProduct.image_position;
        }
        if (selectedImageProduct && selectedImageProduct.id === updatedProduct.id) {
            selectedImageProduct.image_url = updatedProduct.image_url;
            selectedImageProduct.thumbnail_url = updatedProduct.thumbnail_url;
            selectedImageProduct.image_position = updatedProduct.image_position;
        }
    }

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

        {#if isIncomplete}
            <!-- Card de Producto Incompleto (Datos pendientes / Al Vuelo) -->
            <div class="relative flex flex-col gap-3 rounded-2xl border border-amber-500/40 bg-amber-500/5 p-3.5 shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-amber-500/10">
                <!-- Menú discreto "..." en la esquina superior derecha: Únicamente "Eliminar producto" -->
                {#if hasPermission('productos.eliminar')}
                    <div class="absolute top-2.5 right-2.5 z-10">
                        <DropdownMenu>
                            <DropdownMenuTrigger>
                                {#snippet child({ props })}
                                    <button
                                        {...props}
                                        class="size-8 flex items-center justify-center rounded-lg hover:bg-amber-500/15 text-amber-700/80 dark:text-amber-400 transition-colors cursor-pointer"
                                        aria-label={`Acciones para producto incompleto ${product.name}`}
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </button>
                                {/snippet}
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-44 shadow-lg">
                                <DropdownMenuItem
                                    class="flex items-center gap-2 text-xs font-medium text-destructive cursor-pointer py-2.5 focus:text-destructive focus:bg-destructive/10"
                                    onclick={() => promptDelete(product)}
                                >
                                    <Trash2 class="size-4" />
                                    <span>Eliminar producto</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                {/if}

                <!-- Fila Superior: Imagen + Nombre + Badges + Precio -->
                <div class="flex items-start gap-3.5 pr-8">
                    {#if product.thumbnail_url || product.image_url}
                        <div class="relative size-12 shrink-0 rounded-xl border border-amber-500/30 overflow-hidden">
                            <img src={product.thumbnail_url || product.image_url} alt={product.name} class="h-full w-full object-cover" loading="lazy" />
                            {#if hasPermission('productos.editar')}
                                <button
                                    type="button"
                                    onclick={() => openImageModal(product)}
                                    class="absolute bottom-0 right-0 bg-neutral-900/80 hover:bg-neutral-900 text-white size-5.5 rounded-tl-lg flex items-center justify-center shadow-xs cursor-pointer outline-none"
                                    title="Editar foto"
                                    aria-label={`Editar foto de ${product.name}`}
                                >
                                    <Camera class="size-3" />
                                </button>
                            {/if}
                        </div>
                    {:else}
                        <div class="relative size-12 shrink-0 rounded-xl border border-dashed border-amber-500/40 overflow-hidden flex items-center justify-center bg-amber-500/10">
                            <IconContainer
                                size="lg"
                                variant="warning"
                                icon={isWeighable ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                            />
                            {#if hasPermission('productos.editar')}
                                <button
                                    type="button"
                                    onclick={() => openImageModal(product)}
                                    class="absolute inset-0 bg-transparent flex items-center justify-center cursor-pointer outline-none"
                                    title="Subir foto"
                                    aria-label={`Subir foto para ${product.name}`}
                                >
                                    <span class="absolute bottom-0 right-0 bg-amber-600 hover:bg-amber-700 text-white size-5.5 rounded-tl-lg flex items-center justify-center shadow-xs">
                                        <Camera class="size-3" />
                                    </span>
                                </button>
                            {/if}
                        </div>
                    {/if}

                    <div class="flex flex-1 flex-col min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <h4 class="font-semibold text-sm text-foreground leading-snug break-words">
                                <HighlightMatch text={product.name} query={searchQuery} />
                            </h4>
                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30 shrink-0">
                                <AlertTriangle class="h-2.5 w-2.5" />
                                Faltan datos
                            </span>
                            {#if isWeighable}
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold bg-primary/10 text-primary border border-primary/20 shrink-0 tabular-nums">
                                    <Scale class="h-2.5 w-2.5" />
                                    Kg
                                </span>
                            {/if}
                        </div>

                        <div class="flex items-baseline gap-2 mt-1">
                            {#if Number(product.price) > 0}
                                <span class="font-semibold text-base tabular-nums text-foreground">
                                    {formatPrice(product.price)} {isWeighable ? '/Kg' : ''}
                                </span>
                            {:else}
                                <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                    Sin precio de venta
                                </span>
                            {/if}
                            {#if !product.category}
                                <span class="text-[11px] text-muted-foreground font-normal">
                                    • Sin categoría
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- Fila Inferior: Metadata de Stock a la izquierda + Botón "Completar" debajo a la derecha -->
                <div class="flex items-center justify-between gap-3 pt-2 border-t border-amber-500/20">
                    <div class="flex items-center gap-1.5 flex-wrap text-xs min-w-0">
                        {#if product.stock <= 0}
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 tabular-nums">
                                Stock: {product.stock} {isWeighable ? 'Kg' : 'u'}
                            </span>
                        {:else}
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 tabular-nums">
                                Stock: {product.stock} {isWeighable ? 'Kg' : 'u'}
                            </span>
                        {/if}

                        <span class="text-muted-foreground/40 font-light select-none">|</span>
                        <span class="text-xs text-muted-foreground tabular-nums truncate">
                            {product.warehouses_count === 1 ? '1 depósito' : `${product.warehouses_count ?? 0} depósitos`}
                        </span>
                    </div>

                    {#if hasPermission('productos.editar')}
                        <Link
                            href={`/tenant/productos/${product.id}/editar`}
                            class="inline-flex items-center justify-center min-h-[36px] px-4 text-xs font-semibold bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white rounded-xl shadow-xs transition-colors shrink-0"
                        >
                            <span>Completar</span>
                        </Link>
                    {/if}
                </div>
            </div>
        {:else}
            <!-- Card de Producto Completo -->
            <div class="relative flex items-center gap-3.5 rounded-2xl border border-border/70 bg-card p-3.5 shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20">
                <!-- Menú discreto "..." en la esquina superior derecha -->
                {#if hasPermission('productos.editar') || hasPermission('productos.eliminar')}
                    <div class="absolute top-2.5 right-2.5 z-10">
                        <DropdownMenu>
                            <DropdownMenuTrigger>
                                {#snippet child({ props })}
                                    <button
                                        {...props}
                                        class="size-8 flex items-center justify-center rounded-lg hover:bg-muted text-muted-foreground transition-colors cursor-pointer"
                                        aria-label={`Acciones para ${product.name}`}
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </button>
                                {/snippet}
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-44 shadow-lg">
                                {#if hasPermission('productos.editar')}
                                    <DropdownMenuItem
                                        class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                        onclick={() => router.visit(`/tenant/productos/${product.id}/editar`)}
                                    >
                                        <Edit2 class="size-4 text-muted-foreground" />
                                        <span>Editar producto</span>
                                    </DropdownMenuItem>
                                {/if}
                                {#if hasPermission('productos.eliminar')}
                                    <DropdownMenuItem
                                        class="flex items-center gap-2 text-xs font-medium text-destructive cursor-pointer py-2.5 focus:text-destructive focus:bg-destructive/10"
                                        onclick={() => promptDelete(product)}
                                    >
                                        <Trash2 class="size-4" />
                                        <span>Eliminar producto</span>
                                    </DropdownMenuItem>
                                {/if}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                {/if}

                {#if product.thumbnail_url || product.image_url}
                    <div class="relative size-12 shrink-0 rounded-xl border border-border/80 overflow-hidden">
                        <img src={product.thumbnail_url || product.image_url} alt={product.name} class="h-full w-full object-cover" loading="lazy" />
                        {#if hasPermission('productos.editar')}
                            <button
                                type="button"
                                onclick={() => openImageModal(product)}
                                class="absolute bottom-0 right-0 bg-neutral-900/80 hover:bg-neutral-900 text-white size-5.5 rounded-tl-lg flex items-center justify-center shadow-xs cursor-pointer outline-none"
                                title="Editar foto"
                                aria-label={`Editar foto de ${product.name}`}
                            >
                                <Camera class="size-3" />
                            </button>
                        {/if}
                    </div>
                {:else}
                    <div class="relative size-12 shrink-0 rounded-xl border border-dashed border-border/80 overflow-hidden flex items-center justify-center bg-muted/20">
                        <IconContainer
                            size="lg"
                            variant={isWeighable ? 'info' : 'primary'}
                            icon={isWeighable ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                        />
                        {#if hasPermission('productos.editar')}
                            <button
                                type="button"
                                onclick={() => openImageModal(product)}
                                class="absolute inset-0 bg-transparent flex items-center justify-center cursor-pointer outline-none"
                                title="Subir foto"
                                aria-label={`Subir foto para ${product.name}`}
                            >
                                <span class="absolute bottom-0 right-0 bg-primary/90 hover:bg-primary text-primary-foreground size-5.5 rounded-tl-lg flex items-center justify-center shadow-xs">
                                    <Camera class="size-3" />
                                </span>
                            </button>
                        {/if}
                    </div>
                {/if}

                <div class="flex flex-1 flex-col min-w-0 justify-center pr-8">
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
                    </div>

                    <span class="font-semibold text-base mt-0.5 tabular-nums text-foreground">
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
            </div>
        {/if}
    {/each}

    {#if products.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron productos"
            description="No hay productos disponibles en tu catálogo o filtros seleccionados."
            actionLabel={hasPermission('productos.crear') ? 'Crear producto' : undefined}
            actionHref={hasPermission('productos.crear') ? '/tenant/productos/crear' : undefined}
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

<!-- Modal de Edición Rápida de Imagen del Producto Mobile -->
<ProductImageQuickEditModal
    bind:open={isImageModalOpen}
    product={selectedImageProduct}
    onSuccess={handleImageUpdated}
/>
