<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { router } from '@inertiajs/svelte';
    import Calendar from 'lucide-svelte/icons/calendar';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Clock from 'lucide-svelte/icons/clock';
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
        order = null,
    }: {
        open: boolean;
        order: OrderData | null;
    } = $props();

    let selectedStatus = $state(order?.status ?? 'en_preparacion');
    let isUpdatingStatus = $state(false);

    $effect(() => {
        if (order) {
            selectedStatus = order.status;
        }
    });

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

    function handleStatusChange(newStatus: string) {
        if (!order || newStatus === order.status) return;

        if (newStatus === 'cancelado') {
            if (!confirm(`¿Estás seguro de cancelar el pedido ${order.order_number}? El stock de los productos se reincorporará automáticamente.`)) {
                selectedStatus = order.status;
                return;
            }
        }

        isUpdatingStatus = true;
        router.patch(
            `/tenant/orders/${order.id}/status`,
            { status: newStatus },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isUpdatingStatus = false;
                    selectedStatus = newStatus;
                },
                onError: () => {
                    isUpdatingStatus = false;
                    selectedStatus = order?.status ?? 'en_preparacion';
                },
            }
        );
    }

    function handleDelete() {
        if (!order) return;
        if (confirm(`¿Eliminar el pedido ${order.order_number}?`)) {
            router.delete(`/tenant/orders/${order.id}`, {
                onSuccess: () => {
                    open = false;
                },
            });
        }
    }
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
                            <h2 class="text-lg font-bold text-foreground font-mono">{order.order_number}</h2>
                            {#if order.status === 'en_preparacion'}
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/15 text-amber-600 border border-amber-500/30">
                                    En Preparación
                                </span>
                            {:else if order.status === 'preparado'}
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-500/15 text-blue-600 border border-blue-500/30">
                                    Preparado
                                </span>
                            {:else if order.status === 'en_camino'}
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/15 text-indigo-600 border border-indigo-500/30">
                                    En Camino
                                </span>
                            {:else if order.status === 'entregado'}
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/15 text-emerald-600 border border-emerald-500/30">
                                    Entregado
                                </span>
                            {:else if order.status === 'cancelado'}
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-500/15 text-rose-600 border border-rose-500/30">
                                    Cancelado
                                </span>
                            {/if}
                        </div>
                        <p class="text-xs text-muted-foreground">Fecha: {formatDate(order.created_at)}</p>
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
                    <p class="font-semibold text-foreground">{order.customer_phone || 'No especificado'}</p>
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
                <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Productos y Cantidades</h4>
                <div class="rounded-xl border overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-muted/40 text-muted-foreground border-b">
                            <tr>
                                <th class="p-2.5">Producto</th>
                                <th class="p-2.5">Presentación</th>
                                <th class="p-2.5 text-center">Cant.</th>
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
                                            <span class="text-[10px] text-muted-foreground block font-mono">SKU: {item.product.sku}</span>
                                        {/if}
                                    </td>
                                    <td class="p-2.5 text-muted-foreground">
                                        {#if item.packaging_type === 'bolson'}
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary">
                                                Bolsón (x{item.units_per_package}u)
                                            </span>
                                        {:else}
                                            <span>Paquete (Unidad)</span>
                                        {/if}
                                    </td>
                                    <td class="p-2.5 text-center font-bold font-mono">
                                        {item.quantity} {item.packaging_type === 'bolson' ? 'bols.' : 'paq.'}
                                        {#if item.packaging_type === 'bolson'}
                                            <span class="text-[10px] text-muted-foreground block font-normal">
                                                ({item.quantity * item.units_per_package} unid.)
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="p-2.5 text-right font-mono">{formatPrice(item.unit_price)}</td>
                                    <td class="p-2.5 text-right font-mono font-bold text-foreground">{formatPrice(item.subtotal)}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>

            {#if order.notes}
                <div class="p-2.5 rounded-lg bg-muted/20 border text-xs text-muted-foreground">
                    <strong class="text-foreground">Notas:</strong> {order.notes}
                </div>
            {/if}

            <!-- Cambiador de Estado Logístico -->
            <div class="space-y-2 pt-2 border-t">
                <span class="text-xs font-bold text-foreground block">Actualizar Estado Logístico del Pedido:</span>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <button
                        type="button"
                        disabled={isUpdatingStatus}
                        class="px-2 py-1.5 rounded-lg text-xs font-semibold border text-center transition-all {selectedStatus === 'en_preparacion' ? 'bg-amber-500 text-white border-amber-500 shadow-xs' : 'bg-muted/30 hover:bg-muted text-muted-foreground border-border'}"
                        onclick={() => handleStatusChange('en_preparacion')}
                    >
                        En Preparación
                    </button>
                    <button
                        type="button"
                        disabled={isUpdatingStatus}
                        class="px-2 py-1.5 rounded-lg text-xs font-semibold border text-center transition-all {selectedStatus === 'preparado' ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-muted/30 hover:bg-muted text-muted-foreground border-border'}"
                        onclick={() => handleStatusChange('preparado')}
                    >
                        Preparado
                    </button>
                    <button
                        type="button"
                        disabled={isUpdatingStatus}
                        class="px-2 py-1.5 rounded-lg text-xs font-semibold border text-center transition-all {selectedStatus === 'en_camino' ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-muted/30 hover:bg-muted text-muted-foreground border-border'}"
                        onclick={() => handleStatusChange('en_camino')}
                    >
                        En Camino
                    </button>
                    <button
                        type="button"
                        disabled={isUpdatingStatus}
                        class="px-2 py-1.5 rounded-lg text-xs font-semibold border text-center transition-all {selectedStatus === 'entregado' ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-muted/30 hover:bg-muted text-muted-foreground border-border'}"
                        onclick={() => handleStatusChange('entregado')}
                    >
                        Entregado
                    </button>
                    <button
                        type="button"
                        disabled={isUpdatingStatus}
                        class="px-2 py-1.5 rounded-lg text-xs font-semibold border text-center transition-all {selectedStatus === 'cancelado' ? 'bg-rose-600 text-white border-rose-600 shadow-xs' : 'bg-muted/30 hover:bg-muted text-muted-foreground border-border'}"
                        onclick={() => handleStatusChange('cancelado')}
                    >
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Footer con Total y Acciones -->
            <div class="flex items-center justify-between pt-3 border-t">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-xs text-destructive hover:bg-destructive/10 gap-1.5"
                    onclick={handleDelete}
                >
                    <Trash2 class="size-3.5" />
                    Eliminar Pedido
                </Button>

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-xs text-muted-foreground block">Total:</span>
                        <span class="text-lg font-bold font-mono text-primary">{formatPrice(order.total_amount)}</span>
                    </div>
                    <Button type="button" size="sm" variant="outline" onclick={() => (open = false)}>
                        Cerrar
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
