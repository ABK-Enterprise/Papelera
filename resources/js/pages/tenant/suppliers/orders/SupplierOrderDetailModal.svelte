<script lang="ts">
    import { Button } from '@/components/ui/button';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { router } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import PackageCheck from 'lucide-svelte/icons/package-check';
    import Phone from 'lucide-svelte/icons/phone';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Truck from 'lucide-svelte/icons/truck';
    import X from 'lucide-svelte/icons/x';
    import XCircle from 'lucide-svelte/icons/x-circle';

    interface SupplierOrderItem {
        id: number;
        product_id: number;
        product_name: string;
        quantity: number;
        unit_cost: string | number;
        subtotal: string | number;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            stock: number;
        };
    }

    interface SupplierOrderData {
        id: number;
        order_number: string;
        supplier_id: number;
        status: string;
        total_cost: string | number;
        notes: string | null;
        created_at: string;
        supplier?: {
            id: number;
            name: string;
            contact_number: string | null;
            email: string | null;
        };
        items?: SupplierOrderItem[];
    }

    let {
        open = $bindable(false),
        order = $bindable<SupplierOrderData | null>(null),
    }: {
        open: boolean;
        order: SupplierOrderData | null;
    } = $props();

    let isProcessing = $state(false);
    let isReceiveModalOpen = $state(false);
    let isCancelModalOpen = $state(false);

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

    function promptReceive() {
        isReceiveModalOpen = true;
    }

    function executeReceive() {
        if (!order) return;
        isProcessing = true;
        router.patch(`/tenant/proveedores/ordenes/${order.id}/recibir`, {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isProcessing = false;
                isReceiveModalOpen = false;
                if (order) {
                    order = {
                        ...order,
                        status: 'recibido',
                    };
                }
            },
            onError: () => {
                isProcessing = false;
                isReceiveModalOpen = false;
            },
        });
    }

    function promptCancel() {
        isCancelModalOpen = true;
    }

    function executeCancel() {
        if (!order) return;
        isProcessing = true;
        router.patch(`/tenant/proveedores/ordenes/${order.id}/cancelar`, {}, {

            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                isProcessing = false;
                isCancelModalOpen = false;
                if (order) {
                    order = {
                        ...order,
                        status: 'cancelado',
                    };
                }
            },
            onError: () => {
                isProcessing = false;
                isCancelModalOpen = false;
            },
        });
    }
</script>

{#if open && order}
    <!-- Backdrop oscuro accesible -->
    <div
        transition:fade={{ duration: 150 }}
        class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50"
        onclick={() => (open = false)}
        aria-hidden="true"
    ></div>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto pointer-events-none">
        <div
            transition:scale={{ start: 0.97, duration: 150 }}
            class="bg-card text-card-foreground rounded-2xl sm:rounded-3xl border border-border shadow-2xl max-w-2xl w-full p-4 sm:p-6 max-h-[85vh] sm:max-h-[88vh] flex flex-col my-auto pointer-events-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
        >
            <!-- Header (shrink-0) -->
            <div class="flex items-center justify-between border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <IconContainer size="md" variant="primary" icon="solar:clipboard-list-bold-duotone" />
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base sm:text-lg font-bold text-foreground font-sans tabular-nums">{order.order_number}</h2>
                            {#if order.status === 'solicitado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    Solicitado (Pendiente)
                                </span>
                            {:else if order.status === 'recibido'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    Recibido en Stock
                                </span>
                            {:else if order.status === 'cancelado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
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
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </div>


            <!-- Scrollable Content Body -->
            <div class="flex-1 min-h-0 overflow-y-auto space-y-4 sm:space-y-5 py-2 pr-1">
                <!-- Cartel de Estado Terminal -->
                {#if order.status === 'recibido'}
                    <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center gap-3 text-xs text-emerald-700 dark:text-emerald-300">
                        <CheckCircle2 class="size-5 text-emerald-600 shrink-0" />
                        <div>
                            <strong class="font-bold block">Orden Recibida en Stock</strong>
                            <span>Los artículos de esta orden ya fueron incorporados al inventario y registrados en Kardex.</span>
                        </div>
                    </div>
                {:else if order.status === 'cancelado'}
                    <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 flex items-center gap-3 text-xs text-rose-700 dark:text-rose-300">
                        <XCircle class="size-5 text-rose-600 shrink-0" />
                        <div>
                            <strong class="font-bold block">Orden Cancelada</strong>
                            <span>Esta orden de compra fue cancelada y no generó movimientos de stock.</span>
                        </div>
                    </div>
                {/if}

                <!-- Datos del Proveedor -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-xl bg-muted/20 border text-xs">
                    <div class="space-y-1">
                        <span class="text-muted-foreground">Proveedor:</span>
                        <p class="font-bold text-foreground">{order.supplier?.name || 'Proveedor'}</p>
                    </div>
                    <div class="space-y-1">
                        <span class="text-muted-foreground">Contacto:</span>
                        <p class="font-semibold text-foreground">{order.supplier?.contact_number || order.supplier?.email || 'Sin contacto directo'}</p>
                    </div>
                </div>

                <!-- Detalle de Artículos -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Artículos Solicitados</h4>
                    
                    <!-- Stacked Cards en Mobile (block sm:hidden) -->
                    <div class="block sm:hidden space-y-2.5">
                        {#each order.items ?? [] as item}
                            <div class="p-3.5 rounded-xl border bg-muted/20 space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <h5 class="text-sm font-semibold text-foreground leading-tight">
                                            {item.product_name}
                                        </h5>
                                        {#if item.product?.sku}
                                            <span class="text-xs text-muted-foreground font-sans tabular-nums block mt-0.5">
                                                SKU: {item.product.sku}
                                            </span>
                                        {/if}
                                    </div>
                                    <div class="shrink-0">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border font-sans tabular-nums">
                                            {item.quantity} {item.product?.sale_type === 'weight' ? 'Kg' : 'unid.'}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-border/50">
                                    <div class="text-xs text-muted-foreground font-sans tabular-nums">
                                        {Number(item.unit_cost) > 0 ? formatPrice(item.unit_cost) : '-'} × {item.quantity} {item.product?.sale_type === 'weight' ? 'Kg' : 'unid.'}
                                    </div>
                                    <div class="text-sm font-bold font-sans tabular-nums text-foreground">
                                        {Number(item.subtotal) > 0 ? formatPrice(item.subtotal) : '-'}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Tabla en Desktop (hidden sm:block) -->
                    <div class="hidden sm:block rounded-xl border overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-muted/40 text-muted-foreground border-b">
                                <tr>
                                    <th class="p-2.5">Producto</th>
                                    <th class="p-2.5 text-center">Cant. Solicitada</th>
                                    <th class="p-2.5 text-right">Costo Unit.</th>
                                    <th class="p-2.5 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                {#each order.items ?? [] as item}
                                    <tr>
                                        <td class="p-2.5 font-medium text-foreground">
                                            {item.product_name}
                                            {#if item.product?.sku}
                                                <span class="text-[10px] text-muted-foreground block font-sans tabular-nums">SKU: {item.product.sku}</span>
                                            {/if}
                                        </td>
                                        <td class="p-2.5 text-center font-bold font-sans tabular-nums">
                                            {item.quantity} {item.product?.sale_type === 'weight' ? 'Kg' : 'unid.'}
                                        </td>
                                        <td class="p-2.5 text-right font-sans tabular-nums">
                                            {Number(item.unit_cost) > 0 ? formatPrice(item.unit_cost) : '-'}
                                        </td>
                                        <td class="p-2.5 text-right font-sans tabular-nums font-bold text-foreground">
                                            {Number(item.subtotal) > 0 ? formatPrice(item.subtotal) : '-'}
                                        </td>
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

                <!-- Acciones de Estado -->
                {#if order.status === 'solicitado'}
                    <div class="p-3 rounded-xl bg-primary/5 border border-primary/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold text-foreground block">¿Llegó la mercadería al depósito?</span>
                            <p class="text-[11px] text-muted-foreground">Al marcar como recibido, se sumará el stock de cada artículo en Kardex.</p>
                        </div>
                        <Button size="sm" class="font-bold gap-1.5 shrink-0 bg-emerald-600 hover:bg-emerald-700 text-white" onclick={promptReceive} disabled={isProcessing}>
                            <PackageCheck class="size-4" />
                            Marcar como Recibido
                        </Button>
                    </div>
                {/if}
            </div>

            <!-- Footer (shrink-0) -->
            <div class="flex items-center justify-between pt-3.5 border-t shrink-0">
                {#if order.status === 'solicitado'}
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] px-4 rounded-xl text-xs font-semibold text-destructive border-destructive/30 hover:bg-destructive/10 hover:border-destructive/50 gap-2"
                        onclick={promptCancel}
                        disabled={isProcessing}
                    >
                        <XCircle class="size-4" />
                        <span>Cancelar orden</span>
                    </Button>
                {:else}
                    <div></div>
                {/if}

                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-xs text-muted-foreground block">Costo Total:</span>
                        <span class="text-base sm:text-lg font-bold font-sans tabular-nums text-primary">{formatPrice(order.total_cost)}</span>
                    </div>
                    <Button type="button" variant="outline" class="min-h-[44px] px-4 rounded-xl text-sm font-semibold" onclick={() => (open = false)}>
                        Cerrar
                    </Button>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal de Confirmación de Recepción en Stock -->
    <ConfirmActionModal
        bind:open={isReceiveModalOpen}
        title="¿Confirmar recepción de mercadería?"
        description={`¿Deseas registrar el ingreso de los artículos de la orden ${order.order_number}? El inventario de cada producto se incrementará automáticamente y se asentará en Kardex.`}
        confirmText="Ingresar a Stock"
        cancelText="Volver"
        variant="success"
        loading={isProcessing}
        onconfirm={executeReceive}
    />

    <!-- Modal de Confirmación de Cancelación de Orden -->
    <ConfirmActionModal
        bind:open={isCancelModalOpen}
        title="¿Cancelar orden de compra?"
        description={`¿Estás seguro de cancelar la orden ${order.order_number}? La orden quedará archivada como cancelada y no modificará el inventario.`}
        confirmText="Cancelar Orden"
        cancelText="Conservar"
        variant="warning"
        loading={isProcessing}
        onconfirm={executeCancel}
    />
{/if}
