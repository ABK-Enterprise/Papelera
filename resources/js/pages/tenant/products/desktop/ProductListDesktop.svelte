<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import { hasPermission } from '@/lib/permissions';
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import AppPagination, { type PaginationMeta } from '@/components/common/AppPagination.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Camera from 'lucide-svelte/icons/camera';
    import Edit2 from 'lucide-svelte/icons/edit-2';
    import Layers from 'lucide-svelte/icons/layers';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
    import Package from 'lucide-svelte/icons/package';
    import Scale from 'lucide-svelte/icons/scale';
    import Tag from 'lucide-svelte/icons/tag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import ProductImageQuickEditModal from '@/components/tenant/products/ProductImageQuickEditModal.svelte';
    import EditItemPriceModal from '@/components/tenant/products/EditItemPriceModal.svelte';

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
        thumbnail_url?: string | null;
        image_position?: { zoom: number; x: number; y: number } | null;
        is_incomplete?: boolean;
        warehouses_count?: number;
        category?: CategorySummary | null;
        effective_bulk_discount_percentage?: number;
        bulk_discount_price?: string | number;
    }


    let {
        products = [],
        pagination = null,
        searchQuery = '',
    }: {
        products: ProductItem[];
        pagination?: PaginationMeta | null;
        searchQuery?: string;
    } = $props();

    let productToDelete = $state<ProductItem | null>(null);
    let isDeleteModalOpen = $state(false);
    let isDeleting = $state(false);

    let selectedImageProduct = $state<ProductItem | null>(null);
    let isImageModalOpen = $state(false);

    let productToEditPrice = $state<ProductItem | null>(null);
    let isEditPriceModalOpen = $state(false);

    function promptEditPrice(product: ProductItem) {
        productToEditPrice = product;
        isEditPriceModalOpen = true;
    }

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
    {#each products as product (product.id)}
        {@const isWeighable = product.sale_type === 'weight'}
        {@const isIncomplete = Boolean(product.is_incomplete)}
        <div
            class="flex items-center justify-between p-4 rounded-2xl border transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 hover:border-border gap-4 bg-card shadow-xs {isIncomplete ? 'border-amber-300 dark:border-amber-700/60 bg-amber-50/20 dark:bg-amber-950/10' : 'border-border/70 hover:bg-muted/40 dark:hover:bg-secondary/30'}"
        >

            <!-- Lado Izquierdo: Imagen / Avatar Placeholder + Info Principal -->
            <div class="flex items-center gap-4 min-w-0 flex-1">
                {#if product.thumbnail_url || product.image_url}
                    <div class="relative group size-12 rounded-xl border border-border/80 overflow-hidden shrink-0">
                        <img
                            src={product.thumbnail_url || product.image_url}
                            alt={product.name}
                            class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                            loading="lazy"
                        />
                        {#if isWeighable}
                            <span class="absolute bottom-0 right-0 bg-primary text-primary-foreground text-xs font-bold px-1.5 py-0.5 rounded-tl tabular-nums z-10">
                                Kg
                            </span>
                        {/if}

                        {#if hasPermission('productos.editar')}
                            <button
                                type="button"
                                onclick={() => openImageModal(product)}
                                class="absolute inset-0 bg-black/50 text-white opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity flex items-center justify-center cursor-pointer z-20 outline-none"
                                title="Cambiar o encuadrar foto"
                                aria-label={`Editar foto de ${product.name}`}
                            >
                                <Camera class="size-4.5 drop-shadow-sm text-white" />
                            </button>
                        {/if}
                    </div>
                {:else}
                    <div class="relative group size-12 rounded-xl border border-dashed border-border/80 hover:border-primary/50 overflow-hidden shrink-0 flex items-center justify-center bg-muted/40 dark:bg-secondary/35">
                        <IconContainer
                            size="lg"
                            variant={isWeighable ? 'info' : 'primary'}
                            icon={isWeighable ? 'solar:scale-bold-duotone' : 'solar:box-minimalistic-bold-duotone'}
                        />
                        {#if hasPermission('productos.editar')}
                            <button
                                type="button"
                                onclick={() => openImageModal(product)}
                                class="absolute inset-0 bg-black/50 text-white opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity flex items-center justify-center cursor-pointer z-20 outline-none"
                                title="Subir foto del producto"
                                aria-label={`Subir foto para ${product.name}`}
                            >
                                <Camera class="size-4.5 drop-shadow-sm text-white" />
                            </button>
                        {/if}
                    </div>
                {/if}

                <!-- Info Principal: Título, Categoría, Packaging y Stock Unificados -->
                <div class="space-y-1.5 min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="font-semibold text-base text-foreground truncate leading-snug">
                            <HighlightMatch text={product.name} query={searchQuery} />
                        </h4>

                        {#if isWeighable}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                <Scale class="h-3 w-3" />
                                Pesable
                            </span>
                        {/if}

                        {#if isIncomplete}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                                <AlertTriangle class="h-3 w-3 text-amber-700 dark:text-amber-400" />
                                Requiere completar datos
                            </span>
                        {/if}
                    </div>

                    <!-- Código de Barras / SKU / Categoría / Packaging | Stock -->
                    <div class="flex items-center gap-2 text-xs text-muted-foreground flex-wrap">
                        {#if product.barcode}
                            <span class="bg-muted/60 px-1.5 py-0.5 rounded text-xs tabular-nums tracking-wide">
                                BAR: <HighlightMatch text={product.barcode} query={searchQuery} />
                            </span>
                        {/if}
                        {#if product.sku}
                            <span class="bg-muted/60 px-1.5 py-0.5 rounded text-xs tabular-nums tracking-wide">
                                SKU: <HighlightMatch text={product.sku} query={searchQuery} />
                            </span>
                        {/if}

                        {#if product.category}
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Cat: <strong class="text-foreground font-semibold">{product.category.name}</strong>
                            </span>
                        {:else}
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                Sin categoría
                            </span>
                        {/if}

                        <span class="text-muted-foreground/40 font-light select-none">|</span>

                        <!-- Packaging y Stock físico unificados en la misma línea -->
                        {#if isWeighable}
                            <span class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider bg-muted/60 px-1.5 py-0.5 rounded text-foreground tabular-nums">
                                <Layers class="h-3 w-3 text-primary" />
                                1 Bulto = {product.package_quantity || product.units_per_package || 1} Kg
                            </span>
                        {:else if product.units_per_package && product.units_per_package > 1}
                            <span class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider bg-muted/60 px-1.5 py-0.5 rounded text-foreground tabular-nums">
                                <Layers class="h-3 w-3 text-primary" />
                                1 Bulto = {product.units_per_package} unid.
                            </span>
                        {/if}

                        <span class="text-muted-foreground/40 font-light select-none">|</span>

                        <!-- Estado y cantidad de stock físico -->
                        {#if product.stock <= 0}
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-sm font-semibold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20 tabular-nums">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                Stock: Agotado
                            </span>
                        {:else if product.stock <= product.min_stock}
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-sm font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 tabular-nums">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                Stock: {product.stock} {isWeighable ? 'Kg' : 'unid.'} (Bajo)
                            </span>
                        {:else}
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-sm font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 tabular-nums">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Stock: {product.stock} {isWeighable ? 'Kg' : 'unid.'}
                            </span>
                        {/if}

                        <span class="text-muted-foreground/40 font-light select-none">|</span>
                        <span class="text-xs font-medium text-muted-foreground tabular-nums">
                            {product.warehouses_count === 1 ? '1 depósito' : `${product.warehouses_count ?? 0} depósitos`}
                        </span>
                    </div>
                </div>
            </div>


            <!-- Lado Derecho: Precios (alineados a la derecha) + Menú Desplegable '...' -->
            <div class="flex items-center gap-5 shrink-0">
                <!-- Precios -->
                <div class="flex flex-col items-end text-right">
                    <span class="text-xs text-muted-foreground font-medium">Precio unitario</span>
                    <span class="text-sm font-semibold text-foreground tabular-nums">
                        {formatPrice(product.price)} {isWeighable ? '/Kg' : ''}
                    </span>
                    {#if (product.effective_bulk_discount_percentage ?? 0) > 0}
                        <span class="inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 tabular-nums">
                            Bulto (-{product.effective_bulk_discount_percentage}%): {formatPrice(product.bulk_discount_price ?? product.price)}
                        </span>
                    {/if}
                    {#if Number(product.cost_price) > 0}
                        <span class="text-xs font-medium text-muted-foreground tabular-nums">
                            Costo: {formatPrice(product.cost_price)}
                        </span>
                    {/if}
                </div>

                <!-- Menú Desplegable '...' con DropdownMenu (Touch Target >= 40px) -->
                {#if hasPermission('productos.editar') || hasPermission('productos.eliminar')}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    class="inline-flex items-center justify-center min-h-[40px] min-w-[40px] size-10 rounded-xl border border-border/70 text-muted-foreground hover:text-foreground hover:bg-muted/60 dark:hover:bg-secondary/60 transition-colors shadow-2xs cursor-pointer focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-hidden"
                                    aria-label={`Opciones de ${product.name}`}
                                >
                                    <MoreVertical class="h-4 w-4" />
                                </button>
                            {/snippet}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 shadow-lg">
                            {#if hasPermission('productos.editar')}
                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <Link
                                            {...props}
                                            href={`/tenant/productos/${product.id}/editar`}
                                            class="{props.class} flex items-center gap-2 text-sm font-medium cursor-pointer"
                                        >
                                            <Edit2 class="h-3.5 w-3.5 text-muted-foreground" />
                                            <span>{isIncomplete ? 'Completar datos' : 'Editar producto'}</span>
                                        </Link>
                                    {/snippet}
                                </DropdownMenuItem>

                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <button
                                            type="button"
                                            class="{props.class} w-full flex items-center gap-2 text-sm font-medium cursor-pointer"
                                            onclick={() => promptEditPrice(product)}
                                        >
                                            <Tag class="h-3.5 w-3.5 text-muted-foreground" />
                                            <span>Editar precio</span>
                                        </button>
                                    {/snippet}
                                </DropdownMenuItem>
                            {/if}

                            {#if hasPermission('productos.editar') && hasPermission('productos.eliminar')}
                                <DropdownMenuSeparator />
                            {/if}

                            {#if hasPermission('productos.eliminar')}
                                <DropdownMenuItem asChild>
                                    {#snippet children(props)}
                                        <button
                                            type="button"
                                            class="{props.class} w-full flex items-center text-rose-700 dark:text-rose-400 focus:text-rose-700 dark:focus:text-rose-300 focus:bg-rose-500/10 gap-2 text-sm font-medium cursor-pointer"
                                            onclick={() => {
                                                promptDelete(product);
                                            }}
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                            <span>Eliminar producto</span>
                                        </button>
                                    {/snippet}
                                </DropdownMenuItem>
                            {/if}
                        </DropdownMenuContent>
                    </DropdownMenu>
                {/if}
            </div>
        </div>
    {/each}

    {#if products.length === 0}
        <EmptyState
            icon={Package}
            title="No se encontraron productos"
            description="No hay productos que coincidan con los filtros seleccionados o el catálogo de tu comercio está vacío."
            actionLabel={hasPermission('productos.crear') ? 'Crear producto' : undefined}
            actionHref={hasPermission('productos.crear') ? '/tenant/productos/crear' : undefined}
        />
    {/if}


    <AppPagination {pagination} />
</div>

<!-- Modal de Confirmación de Eliminación de Producto -->
<ConfirmActionModal
    bind:open={isDeleteModalOpen}
    title="¿Eliminar producto del catálogo?"
    description={`¿Estás seguro de eliminar "${productToDelete?.name}"? Esta acción no se puede deshacer y removerá el artículo del inventario.`}
    confirmText="Eliminar producto"
    cancelText="Cancelar"
    variant="danger"
    loading={isDeleting}
    onconfirm={executeDelete}
/>

<!-- Modal de Edición Rápida de Imagen del Producto -->
<ProductImageQuickEditModal
    bind:open={isImageModalOpen}
    product={selectedImageProduct}
    onSuccess={handleImageUpdated}
/>

<!-- Modal de Edición de Precio de Catálogo -->
<EditItemPriceModal
    bind:open={isEditPriceModalOpen}
    product={productToEditPrice}
    isOrderContext={false}
/>
