<script lang="ts">
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogBody,
        DialogFooter,
    } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import EditItemPriceModal from '@/components/tenant/products/EditItemPriceModal.svelte';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Package from 'lucide-svelte/icons/package';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Tag from 'lucide-svelte/icons/tag';

    export interface OrderItemData {
        id: number;
        product_id?: number | null;
        product_name?: string;
        packaging_type?: string;
        units_per_package?: number;
        quantity: number | string;
        unit_price: number | string;
        subtotal: number | string;
        sku?: string | null;
        product?: {
            id: number;
            name: string;
            price: number | string;
            sku?: string | null;
        } | null;
    }

    export interface OrderData {
        id: number;
        order_number: string;
        customer_name: string;
        status: string;
        fiscal_status?: string | null;
        items?: OrderItemData[];
    }

    interface Props {
        open?: boolean;
        order: OrderData | null;
        onPriceUpdated?: (updatedOrder: any) => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onPriceUpdated = undefined,
        onclose = undefined,
    }: Props = $props();

    let editingItem = $state<OrderItemData | null>(null);
    let isEditItemPriceOpen = $state(false);
    let activeOrder = $state<OrderData | null>(order);
    let searchQuery = $state('');

    $effect(() => {
        activeOrder = order;
    });

    const isOrderImmutable = $derived.by(() => {
        if (!activeOrder) return true;
        return (
            activeOrder.status === 'entregado' ||
            activeOrder.status === 'cancelado' ||
            activeOrder.fiscal_status === 'facturado'
        );
    });

    const orderSubtotal = $derived.by(() => {
        if (!activeOrder?.items || activeOrder.items.length === 0) return 0;
        return activeOrder.items.reduce(
            (sum, item) =>
                sum +
                (Number(item.subtotal) ||
                    Number(item.quantity) * Number(item.unit_price) ||
                    0),
            0
        );
    });

    const comboboxItems = $derived.by(() => {
        if (!activeOrder?.items) return [];
        const q = searchQuery.trim().toLowerCase();
        return activeOrder.items
            .map((it) => ({
                id: it.id,
                name: it.product_name || it.product?.name || 'Producto',
                sku: it.sku || it.product?.sku || null,
                unit_price: it.unit_price,
                quantity: it.quantity,
            }))
            .filter((it) => {
                if (!q) return true;
                return it.name.toLowerCase().includes(q) || (it.sku && it.sku.toLowerCase().includes(q));
            });
    });

    const filteredItems = $derived.by(() => {
        if (!activeOrder?.items || activeOrder.items.length === 0) return [];
        const q = searchQuery.trim().toLowerCase();
        if (!q) return activeOrder.items;
        return activeOrder.items.filter((item) => {
            const name = (item.product_name || item.product?.name || '').toLowerCase();
            const sku = (item.sku || item.product?.sku || '').toLowerCase();
            return name.includes(q) || sku.includes(q);
        });
    });

    function formatCurrency(amount: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount) || 0);
    }

    function handleOpenEditPrice(item: OrderItemData) {
        if (isOrderImmutable) {
            toast.error('No se pueden modificar precios de un pedido en estado definitivo o facturado.');
            return;
        }
        editingItem = item;
        isEditItemPriceOpen = true;
    }

    async function handleSaveItemPrice(newPrice: number, mode: 'order_only' | 'catalog') {
        if (!activeOrder || !editingItem) return;

        const csrfToken =
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        const res = await fetch(`/tenant/pedidos/${activeOrder.id}/items/${editingItem.id}/precio`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                unit_price: newPrice,
                update_catalog: mode === 'catalog',
            }),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            const err = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
            throw new Error(err || 'Error al actualizar el precio del producto.');
        }

        toast.success(
            mode === 'catalog'
                ? 'Precio actualizado en el pedido y sincronizado en el catálogo.'
                : 'Precio actualizado para este pedido.'
        );

        if (data.order) {
            activeOrder = data.order;
            onPriceUpdated?.(data.order);
        }

        router.reload({ only: ['orders'] });
    }

    function handleClose() {
        open = false;
        editingItem = null;
        searchQuery = '';
        onclose?.();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl px-5 sm:px-6 pt-[15px] pb-[15px] sm:pt-[19px] sm:pb-[19px] space-y-3.5 sm:space-y-4 rounded-2xl max-h-[90vh] flex flex-col">
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center gap-2.5 sm:gap-3.5">
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <Tag class="size-5 sm:size-6 text-primary" />
                </div>
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1">
                        Editar precios de productos
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <!-- Buscador Combobox de Productos del Pedido -->
        {#if activeOrder?.items && activeOrder.items.length > 0}
            <div class="shrink-0 relative z-30">
                <SearchCombobox
                    id="order-products-price-combobox"
                    bind:value={searchQuery}
                    items={comboboxItems}
                    filterLocally={false}
                    placeholder="Buscar producto por nombre o código SKU..."
                    showDropdown={true}
                    onsearch={(q) => (searchQuery = q)}
                    onselect={(p) => {
                        searchQuery = p.name;
                    }}
                    onclear={() => {
                        searchQuery = '';
                    }}
                    inputClass="h-10 sm:h-10 min-h-[40px] text-xs sm:text-sm font-medium w-full"
                >
                    {#snippet itemSnippet(p)}
                        <div class="flex items-start justify-between gap-2.5 w-full text-left py-0.5">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground line-clamp-1 break-words">
                                    {p.name}
                                </p>
                                <div class="flex items-center gap-2 mt-0.5 text-xs text-muted-foreground">
                                    {#if p.sku}
                                        <span class="tabular-nums">SKU: {p.sku}</span>
                                    {/if}
                                    <span class="tabular-nums">• Cant: {p.quantity}</span>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-foreground tabular-nums shrink-0">
                                {formatCurrency(p.unit_price)}
                            </span>
                        </div>
                    {/snippet}
                    {#snippet emptySnippet()}
                        <div class="p-3 text-center text-xs text-muted-foreground">
                            No se encontraron productos coincidentes en este pedido.
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>
        {/if}

        <DialogBody class="py-2 space-y-3 flex-1 overflow-y-auto min-h-0 relative z-10">
            {#if isOrderImmutable}
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/25 flex items-start gap-2.5 text-xs text-amber-900 dark:text-amber-300">
                    <AlertTriangle class="size-4 text-amber-700 dark:text-amber-400 shrink-0 mt-0.5" />
                    <p class="leading-relaxed">
                        Este pedido se encuentra en estado definitivo o posee comprobante fiscal emitido con CAE. Los precios de sus artículos no admiten modificaciones directas.
                    </p>
                </div>
            {/if}

            {#if !activeOrder?.items || activeOrder.items.length === 0}
                <div class="py-10 text-center rounded-xl border border-dashed border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-6">
                    <Package class="size-8 mx-auto text-muted-foreground/60 mb-2" />
                    <p class="text-xs font-semibold text-foreground">
                        No hay productos registrados en este pedido
                    </p>
                </div>
            {:else if filteredItems.length === 0}
                <div class="py-8 text-center rounded-xl border border-dashed border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-6">
                    <Package class="size-7 mx-auto text-muted-foreground/60 mb-2" />
                    <p class="text-xs font-semibold text-foreground">
                        No se encontraron productos coincidentes con "{searchQuery}"
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="mt-2 text-xs font-semibold text-primary cursor-pointer min-h-[36px]"
                        onclick={() => (searchQuery = '')}
                    >
                        Limpiar búsqueda
                    </Button>
                </div>
            {:else}
                <div class="space-y-2.5">
                    {#each filteredItems as item (item.id)}
                        {@const productName = item.product_name || item.product?.name || 'Producto'}
                        {@const productSku = item.sku || item.product?.sku}

                        <!-- Mobile Card (< sm) -->
                        <div class="sm:hidden flex flex-col gap-2 p-3.5 rounded-xl border border-border/80 bg-muted/40 hover:bg-muted/70 dark:bg-secondary/35 dark:hover:bg-secondary/55 dark:border-border/90 shadow-2xs transition-colors">
                            <!-- Renglón 1: Nombre producto arriba izquierda -->
                            <span class="text-sm font-semibold text-foreground leading-snug">
                                {productName}
                            </span>

                            <!-- Renglón 2: Debajo del nombre el SKU -->
                            {#if productSku}
                                <span class="text-xs text-muted-foreground tabular-nums -mt-0.5">
                                    SKU: {productSku}
                                </span>
                            {/if}

                            <!-- Renglón 3 e inferior: precio y cantidad, y botón de editar con lápiz abajo a la derecha -->
                            <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-border/40">
                                <span class="text-xs text-muted-foreground tabular-nums">
                                    precio: <strong class="text-foreground font-semibold">{formatCurrency(item.unit_price)}</strong> | cant: <strong class="text-foreground font-semibold">{item.quantity}</strong>
                                </span>

                                {#if !isOrderImmutable}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="min-h-[38px] size-9 p-0 rounded-xl cursor-pointer text-primary border-primary/30 hover:bg-primary/10 dark:hover:bg-primary/20 flex items-center justify-center shrink-0"
                                        aria-label="Editar precio de {productName}"
                                        onclick={() => handleOpenEditPrice(item)}
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                {/if}
                            </div>
                        </div>

                        <!-- Desktop Row (>= sm) -->
                        <div class="hidden sm:flex items-center justify-between gap-3 p-3.5 rounded-xl border border-border/80 bg-muted/40 hover:bg-muted/70 dark:bg-secondary/35 dark:hover:bg-secondary/55 dark:border-border/90 shadow-2xs transition-colors">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-sm font-semibold text-foreground">
                                        {productName}
                                    </span>
                                    {#if productSku}
                                        <span class="text-xs text-muted-foreground tabular-nums">
                                            ({productSku})
                                        </span>
                                    {/if}
                                </div>

                                <div class="flex items-center gap-3 text-xs text-muted-foreground mt-1 tabular-nums">
                                    <span>
                                        Cant: <strong class="text-foreground font-semibold">{item.quantity}</strong>
                                    </span>
                                    <span>•</span>
                                    <span>
                                        Precio: <strong class="text-foreground font-semibold">{formatCurrency(item.unit_price)}</strong>
                                    </span>
                                </div>
                            </div>

                            {#if !isOrderImmutable}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="min-h-[38px] px-3 rounded-xl text-sm gap-1.5 font-semibold cursor-pointer text-primary border-primary/30 hover:bg-primary/10 dark:hover:bg-primary/20 shrink-0"
                                    onclick={() => handleOpenEditPrice(item)}
                                >
                                    <Pencil class="size-3.5" />
                                    <span>Modificar</span>
                                </Button>
                            {/if}
                        </div>
                    {/each}
                </div>
            {/if}
        </DialogBody>

        <!-- Separador Horizontal y Nivel Propio del Subtotal -->
        <div class="border-t border-border/60 pt-2.5 sm:pt-3 shrink-0">
            <div class="flex items-center justify-between py-0.5 px-0.5">
                <span class="text-base sm:text-lg font-bold text-foreground tracking-tight">
                    Subtotal
                </span>
                <span class="text-lg sm:text-xl font-bold font-sans tabular-nums text-foreground">
                    {formatCurrency(orderSubtotal)}
                </span>
            </div>
        </div>

        <!-- Separador Horizontal y Footer con Botón Cerrar -->
        <DialogFooter class="pt-2.5 sm:pt-3 border-t border-border/60 shrink-0">
            <Button
                type="button"
                variant="secondary"
                class="min-h-[44px] rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto ml-auto"
                onclick={handleClose}
            >
                Cerrar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<!-- Submodal para Editar Precio del Producto seleccionado -->
{#if editingItem}
    <EditItemPriceModal
        bind:open={isEditItemPriceOpen}
        product={{
            id: editingItem.product_id ?? editingItem.product?.id ?? null,
            name: editingItem.product_name || editingItem.product?.name || 'Producto',
            price: editingItem.unit_price,
            sku: editingItem.sku || editingItem.product?.sku || null,
        }}
        isOrderContext={true}
        customUpdateHandler={handleSaveItemPrice}
    />
{/if}
