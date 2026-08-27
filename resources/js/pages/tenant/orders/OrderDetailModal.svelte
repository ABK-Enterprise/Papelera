<script lang="ts">
    import { Button } from '@/components/ui/button';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Calendar from 'lucide-svelte/icons/calendar';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Clock from 'lucide-svelte/icons/clock';
    import Info from 'lucide-svelte/icons/info';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Phone from 'lucide-svelte/icons/phone';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Truck from 'lucide-svelte/icons/truck';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import XCircle from 'lucide-svelte/icons/x-circle';

    interface OrderItem {
        id: number;
        product_id: number;
        packaging_type: string;
        units_per_package: number;
        quantity: number;
        unit_price: string | number;
        subtotal: string | number;
        product?: {
            id: number;
            name: string;
            sku: string | null;
        };
    }

    interface OrderData {
        id: number;
        order_number: string;
        customer_name: string;
        customer_phone: string | null;
        delivery_address: string | null;
        status: string;
        total_amount: string | number;
        notes: string | null;
        created_at: string;
        items?: OrderItem[];
    }

    let {
        open = $bindable(false),
        order = $bindable<OrderData | null>(null),
    }: {
        open: boolean;
        order: OrderData | null;
    } = $props();

    let isUpdatingStatus = $state(false);
    let isConfirmModalOpen = $state(false);
    let isDeleteModalOpen = $state(false);
    let pendingStatus = $state<string | null>(null);

    function formatPrice(amount: string | number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '-';
        try {
            return new Date(dateStr).toLocaleString('es-AR', {
                dateStyle: 'medium',
                timeStyle: 'short',
            });
        } catch {
            return dateStr;
        }
    }

    // Modal de confirmación para cualquier cambio de estado
    function requestStatusChange(newStatus: string) {
        if (!order || newStatus === order.status) return;

        // Si ya está cancelado o entregado, no permitir ninguna acción
        if (order.status === 'cancelado' || order.status === 'entregado') return;

        pendingStatus = newStatus;
        isConfirmModalOpen = true;
    }

    function executeStatusUpdate() {
        if (!order || !pendingStatus) return;

        const statusToSet = pendingStatus;
        isUpdatingStatus = true;

        router.patch(
            `/tenant/orders/${order.id}/status`,
            { status: statusToSet },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    isUpdatingStatus = false;
                    isConfirmModalOpen = false;
                    if (order) {
                        order = {
                            ...order,
                            status: statusToSet,
                        };
                    }
                    pendingStatus = null;
                },
                onError: () => {
                    isUpdatingStatus = false;
                    isConfirmModalOpen = false;
                    pendingStatus = null;
                },
            }
        );
    }

    function handleDelete() {
        if (!order || order.status === 'entregado' || order.status === 'cancelado') return;
        isDeleteModalOpen = true;
    }

    function executeDelete() {
        if (!order) return;
        router.delete(`/tenant/orders/${order.id}`, {
            onSuccess: () => {
                isDeleteModalOpen = false;
                open = false;
            },
        });
    }

    // Modal Copy Generator según el estado a aplicar
    let confirmModalConfig = $derived.by(() => {
        if (pendingStatus === 'cancelado') {
            return {
                title: '¿Cancelar pedido de venta?',
                description: `¿Estás seguro de cancelar el pedido ${order?.order_number}? El stock de los artículos se reincorporará automáticamente al inventario. Esta acción es definitiva y no podrá revertirse.`,
                confirmText: 'Cancelar pedido',
                variant: 'danger' as const,
            };
        }
        if (pendingStatus === 'entregado') {
            return {
                title: '¿Marcar pedido como entregado?',
                description: `¿Confirmas que el pedido ${order?.order_number} fue entregado con éxito al cliente? Al marcarlo como entregado se completará la operación de forma definitiva.`,
                confirmText: 'Confirmar entrega',
                variant: 'success' as const,
            };
        }
        if (pendingStatus === 'en_camino') {
            return {
                title: '¿Marcar pedido en camino?',
                description: `El pedido ${order?.order_number} pasará a estado de despacho / en tránsito hacia la dirección del cliente.`,
                confirmText: 'Despachar pedido',
                variant: 'default' as const,
            };
        }
        if (pendingStatus === 'preparado') {
            return {
                title: '¿Marcar pedido como preparado?',
                description: `El pedido ${order?.order_number} quedará empaquetado y listo para ser entregado o despachado.`,
                confirmText: 'Marcar preparado',
                variant: 'default' as const,
            };
        }
        return {
            title: '¿Actualizar estado del pedido?',
            description: `¿Deseas cambiar el estado del pedido ${order?.order_number}?`,
            confirmText: 'Actualizar',
            variant: 'default' as const,
        };
    });
</script>

{#if open && order}
    <div
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto"
        onclick={(e) => e.target === e.currentTarget && (open = false)}
        onkeydown={(e) => e.key === 'Escape' && (open = false)}
        role="button"
        tabindex="0"
        aria-label="Cerrar detalle"
    >
        <div class="bg-card text-card-foreground rounded-2xl border border-border shadow-2xl max-w-2xl w-full p-6 space-y-5 my-8">
            <!-- Header -->
            <div class="flex items-center justify-between border-b pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary font-mono font-bold">
                        <ShoppingBag class="size-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-foreground font-mono tabular-nums">{order.order_number}</h2>
                            {#if order.status === 'en_preparacion'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-amber-500/15 text-amber-600 border border-amber-500/30">
                                    En Preparación
                                </span>
                            {:else if order.status === 'preparado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-blue-500/15 text-blue-600 border border-blue-500/30">
                                    Preparado
                                </span>
                            {:else if order.status === 'en_camino'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-indigo-500/15 text-indigo-600 border border-indigo-500/30">
                                    En Camino
                                </span>
                            {:else if order.status === 'entregado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-emerald-500/15 text-emerald-600 border border-emerald-500/30">
                                    Entregado
                                </span>
                            {:else if order.status === 'cancelado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium tracking-wide bg-rose-500/15 text-rose-600 border border-rose-500/30">
                                    Cancelado
                                </span>
                            {/if}
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5 tabular-nums">Fecha: {formatDate(order.created_at)}</p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick={() => (open = false)}
                    class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Cerrar"
                >
                    <X class="size-5" />
                </button>
            </div>

            <!-- Cartel Informativo de Estado Terminal -->
            {#if order.status === 'cancelado'}
                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center gap-3 text-xs text-rose-700 dark:text-rose-300">
                    <XCircle class="size-5 text-rose-600 shrink-0" />
                    <div>
                        <strong class="font-bold block">Pedido Cancelado Definitivamente</strong>
                        <span>Este pedido fue cancelado, su stock ya fue reincorporado al catálogo y su registro se encuentra cerrado.</span>
                    </div>
                </div>
            {:else if order.status === 'entregado'}
                <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3 text-xs text-emerald-700 dark:text-emerald-300">
                    <CheckCircle2 class="size-5 text-emerald-600 shrink-0" />
                    <div>
                        <strong class="font-bold block">Pedido Completado y Entregado</strong>
                        <span>Este pedido fue entregado con éxito al cliente. La operación logística ha finalizado.</span>
                    </div>
                </div>
            {/if}

            <!-- Datos del Cliente -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-muted/20 border text-xs">
                <div class="space-y-1">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <User class="size-3.5" /> Cliente:
                    </span>
                    <p class="font-semibold text-foreground">{order.customer_name}</p>
                </div>
                <div class="space-y-1">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <Phone class="size-3.5" /> Teléfono:
                    </span>
                    <p class="font-medium text-foreground font-mono tabular-nums">{order.customer_phone || 'No especificado'}</p>
                </div>
                <div class="space-y-1">
                    <span class="text-muted-foreground flex items-center gap-1">
                        <MapPin class="size-3.5" /> Entrega:
                    </span>
                    <p class="font-semibold text-foreground">{order.delivery_address || 'Retiro en local'}</p>
                </div>
            </div>

            <!-- Tabla de Items -->
            <div class="space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Productos y Cantidades</h4>
                <div class="rounded-xl border overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-muted/40 text-muted-foreground border-b">
                            <tr>
                                <th class="p-2.5 text-left">Producto</th>
                                <th class="p-2.5 text-left">Presentación</th>
                                <th class="p-2.5 text-right">Cant.</th>
                                <th class="p-2.5 text-right">Precio Unit.</th>
                                <th class="p-2.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            {#each order.items ?? [] as item}
                                <tr>
                                    <td class="p-2.5 font-medium text-foreground">
                                        {item.product?.name || `Producto #${item.product_id}`}
                                        {#if item.product?.sku}
                                            <span class="text-xs text-muted-foreground block font-mono tabular-nums">SKU: {item.product.sku}</span>
                                        {/if}
                                    </td>
                                    <td class="p-2.5 text-muted-foreground">
                                        {#if item.packaging_type === 'gram'}
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-sky-600 dark:text-sky-400">
                                                Gramos (g)
                                            </span>
                                        {:else if item.packaging_type === 'kg'}
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-sky-600 dark:text-sky-400">
                                                Kilogramos (Kg)
                                            </span>
                                        {:else if item.packaging_type === 'bolson' || item.packaging_type === 'bulto'}
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-primary">
                                                Bulto (x{item.units_per_package || 1})
                                            </span>
                                        {:else}
                                            <span>Unidad</span>
                                        {/if}
                                    </td>
                                    <td class="p-2.5 text-right font-semibold font-mono tabular-nums">
                                        {#if item.packaging_type === 'gram'}
                                            {item.quantity} g
                                            <span class="text-xs text-muted-foreground block font-normal tabular-nums">
                                                ({item.quantity / 1000} Kg)
                                            </span>
                                        {:else if item.packaging_type === 'kg'}
                                            {item.quantity} Kg
                                        {:else if item.packaging_type === 'bolson' || item.packaging_type === 'bulto'}
                                            {item.quantity} {item.quantity === 1 ? 'bulto' : 'bultos'}
                                            <span class="text-xs text-muted-foreground block font-normal tabular-nums">
                                                ({item.quantity * (item.units_per_package || 1)})
                                            </span>
                                        {:else}
                                            {item.quantity} unid.
                                        {/if}
                                    </td>
                                    <td class="p-2.5 text-right font-mono tabular-nums">{formatPrice(item.unit_price)}</td>
                                    <td class="p-2.5 text-right font-mono font-bold tabular-nums text-foreground">{formatPrice(item.subtotal)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>

            {#if order.notes}
                <div class="p-2.5 rounded-lg bg-muted/20 border text-xs text-muted-foreground">
                    <strong class="text-foreground font-medium">Notas:</strong> {order.notes}
                </div>
            {/if}

            <!-- Cambiador de Estado Logístico (Solo si no está en estado terminal) -->
            {#if order.status !== 'cancelado' && order.status !== 'entregado'}
                <div class="space-y-2 pt-2 border-t">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-foreground block">Actualizar Estado del Pedido:</span>
                        <span class="text-xs text-muted-foreground">Selecciona el siguiente paso del flujo</span>
                    </div>

                    <div class="grid grid-cols-1 {order.status === 'preparado' ? 'sm:grid-cols-3' : 'sm:grid-cols-2'} gap-2">
                        {#if order.status === 'en_preparacion'}
                            <button
                                type="button"
                                disabled={isUpdatingStatus}
                                class="px-3 py-2.5 rounded-xl text-xs font-medium border text-center transition-all bg-blue-600 hover:bg-blue-700 text-white shadow-xs"
                                onclick={() => requestStatusChange('preparado')}
                            >
                                Preparar pedido
                            </button>
                        {:else if order.status === 'preparado'}
                            <button
                                type="button"
                                disabled={isUpdatingStatus}
                                class="px-3 py-2.5 rounded-xl text-xs font-medium border text-center transition-all bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs"
                                onclick={() => requestStatusChange('en_camino')}
                            >
                                Despachar pedido
                            </button>
                            <button
                                type="button"
                                disabled={isUpdatingStatus}
                                class="px-3 py-2.5 rounded-xl text-xs font-medium border text-center transition-all bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                                onclick={() => requestStatusChange('entregado')}
                            >
                                Entregar pedido
                            </button>
                        {:else if order.status === 'en_camino'}
                            <button
                                type="button"
                                disabled={isUpdatingStatus}
                                class="px-3 py-2.5 rounded-xl text-xs font-medium border text-center transition-all bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs"
                                onclick={() => requestStatusChange('entregado')}
                            >
                                Entregar pedido
                            </button>
                        {/if}

                        <!-- Botón Cancelar disponible para pedidos activos -->
                        <button
                            type="button"
                            disabled={isUpdatingStatus}
                            class="px-3 py-2.5 rounded-xl text-xs font-medium border text-center transition-all bg-rose-600/10 hover:bg-rose-600 hover:text-white text-rose-600 border-rose-500/30"
                            onclick={() => requestStatusChange('cancelado')}
                        >
                            Cancelar pedido
                        </button>
                    </div>
                </div>
            {/if}

            <!-- Footer con Total y Acciones -->
            <div class="flex items-center justify-between pt-3 border-t">
                {#if order.status !== 'cancelado' && order.status !== 'entregado'}
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-xs font-medium text-destructive hover:bg-destructive/10 gap-1.5"
                        onclick={handleDelete}
                    >
                        <Trash2 class="size-3.5" />
                        <span>Eliminar pedido</span>
                    </Button>
                {:else}
                    <div></div>
                {/if}

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-xs text-muted-foreground block">Total:</span>
                        <span class="text-lg font-bold font-mono tabular-nums text-primary">{formatPrice(order.total_amount)}</span>
                    </div>
                    <Button type="button" size="sm" variant="outline" onclick={() => (open = false)} class="text-xs font-medium">
                        Cerrar
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Unificado de Confirmación de Cambio de Estado -->
    <ConfirmActionModal
        bind:open={isConfirmModalOpen}
        title={confirmModalConfig.title}
        description={confirmModalConfig.description}
        confirmText={confirmModalConfig.confirmText}
        cancelText="Volver"
        variant={confirmModalConfig.variant}
        loading={isUpdatingStatus}
        onconfirm={executeStatusUpdate}
        oncancel={() => {
            pendingStatus = null;
        }}
    />

    <!-- Modal de Confirmación de Eliminación -->
    <ConfirmActionModal
        bind:open={isDeleteModalOpen}
        title="¿Eliminar pedido?"
        description={`Esta acción eliminará el registro del pedido ${order.order_number}. El stock reservado se reincorporará automáticamente.`}
        confirmText="Eliminar pedido"
        cancelText="Cancelar"
        variant="danger"
        onconfirm={executeDelete}
    />
{/if}
