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
    import EditItemPriceModal from '@/components/tenant/products/EditItemPriceModal.svelte';
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Package from 'lucide-svelte/icons/package';
    import Pencil from 'lucide-svelte/icons/pencil';
    import Tag from 'lucide-svelte/icons/tag';
    import User from 'lucide-svelte/icons/user';

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

    const isOrderImmutable = $derived.by(() => {
        if (!order) return true;
        return (
            order.status === 'entregado' ||
            order.status === 'cancelado' ||
            order.fiscal_status === 'facturado'
        );
    });

    const orderSubtotal = $derived.by(() => {
        if (!order?.items || order.items.length === 0) return 0;
        return order.items.reduce(
            (sum, item) =>
                sum +
                (Number(item.subtotal) ||
                    Number(item.quantity) * Number(item.unit_price) ||
                    0),
            0
        );
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
        if (!order || !editingItem) return;

        const csrfToken =
            (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        const res = await fetch(`/tenant/pedidos/${order.id}/items/${editingItem.id}/precio`, {
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
            onPriceUpdated?.(data.order);
        }

        router.reload({ only: ['orders'] });
    }

    function handleClose() {
        open = false;
        editingItem = null;
        onclose?.();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl p-5 sm:p-6 space-y-4 rounded-2xl max-h-[90vh] flex flex-col">
        <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                        <Tag class="size-5 sm:size-6 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1 flex items-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                            Editar precios de productos
                        </DialogTitle>
                    </div>
                </div>

                {#if order}
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-primary/10 text-primary tabular-nums">
                            {order.order_number}
                        </span>
                        <span class="text-xs font-medium text-muted-foreground flex items-center gap-1 truncate max-w-[180px]">
                            <User class="size-3 text-muted-foreground shrink-0" />
                            {order.customer_name}
                        </span>
                    </div>
                {/if}
            </div>
        </DialogHeader>

        <DialogBody class="py-2 space-y-3 flex-1 overflow-y-auto min-h-0">
            {#if isOrderImmutable}
                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/25 flex items-start gap-2.5 text-xs text-amber-900 dark:text-amber-300">
                    <AlertTriangle class="size-4 text-amber-600 shrink-0 mt-0.5" />
                    <p class="leading-relaxed">
                        Este pedido se encuentra en estado definitivo o posee comprobante fiscal emitido con CAE. Los precios de sus artículos no admiten modificaciones directas.
                    </p>
                </div>
            {/if}

            {#if !order?.items || order.items.length === 0}
                <div class="py-10 text-center rounded-xl border border-dashed border-border/80 bg-muted/20 p-6">
                    <Package class="size-8 mx-auto text-muted-foreground/60 mb-2" />
                    <p class="text-xs font-semibold text-foreground">
                        No hay productos registrados en este pedido
                    </p>
                </div>
            {:else}
                <div class="space-y-2.5">
                    {#each order.items as item (item.id)}
                        {@const productName = item.product_name || item.product?.name || 'Producto'}
                        {@const productSku = item.sku || item.product?.sku}

                        <!-- Mobile Card (< sm) -->
                        <div class="sm:hidden flex flex-col gap-2 p-3.5 rounded-xl border border-border/70 bg-card shadow-2xs">
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
                                        class="min-h-[38px] size-9 p-0 rounded-xl cursor-pointer text-primary border-primary/30 hover:bg-primary/10 flex items-center justify-center shrink-0"
                                        aria-label="Editar precio de {productName}"
                                        onclick={() => handleOpenEditPrice(item)}
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                {/if}
                            </div>
                        </div>

                        <!-- Desktop Row (>= sm) -->
                        <div class="hidden sm:flex items-center justify-between gap-3 p-3.5 rounded-xl border border-border/70 bg-card hover:bg-muted/30 transition-colors">
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
                                    class="min-h-[38px] px-3 rounded-xl text-sm gap-1.5 font-semibold cursor-pointer text-primary border-primary/30 hover:bg-primary/10 shrink-0"
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

        <!-- Footer: [separador, subtotal, botón de cerrar] -->
        <DialogFooter class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-border/70 shrink-0">
            <div class="flex items-center justify-between sm:justify-start gap-2">
                <span class="text-xs text-muted-foreground font-medium">Subtotal productos:</span>
                <span class="text-sm font-bold text-foreground tabular-nums">
                    {formatCurrency(orderSubtotal)}
                </span>
            </div>

            <Button
                type="button"
                variant="outline"
                class="min-h-[44px] rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto"
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
