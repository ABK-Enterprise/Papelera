<script lang="ts">
    import { Button } from '@/components/ui/button';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import PdfPreviewModal from '@/components/common/PdfPreviewModal.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { router } from '@inertiajs/svelte';
    import { hasPermission } from '@/lib/permissions';
    import { fade, scale } from 'svelte/transition';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import PackageCheck from 'lucide-svelte/icons/package-check';
    import Phone from 'lucide-svelte/icons/phone';
    import Printer from 'lucide-svelte/icons/printer';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Truck from 'lucide-svelte/icons/truck';
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
    let isPdfModalOpen = $state(false);
    let orderVersion = $derived(order?.updated_at ? new Date(order.updated_at).getTime() : Date.now());

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
            <div class="flex items-start sm:items-center justify-between border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="flex items-start sm:items-center gap-2.5 min-w-0 flex-1">
                    <IconContainer size="md" variant="primary" icon="solar:clipboard-list-bold-duotone" class="shrink-0 mt-0.5 sm:mt-0" />
                    <div class="min-w-0 flex-1">
                        <!-- Mobile (< sm): Stack vertical [ID de orden, Pill de estado, Fecha] -->
                        <div class="flex sm:hidden flex-col items-start gap-1">
                            <h2 class="text-base font-bold text-foreground font-sans tabular-nums break-words leading-tight">
                                {order.order_number}
                            </h2>
                            {#if order.status === 'solicitado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    Solicitado
                                </span>
                            {:else if order.status === 'recibido'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    Recibido
                                </span>
                            {:else if order.status === 'cancelado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                    Cancelado
                                </span>
                            {/if}
                            <p class="text-xs text-muted-foreground mt-0.5">Fecha: {formatDate(order.created_at)}</p>
                        </div>

                        <!-- Desktop (sm:): Row horizontal con ID y Pill, luego Fecha -->
                        <div class="hidden sm:block">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-foreground font-sans tabular-nums">{order.order_number}</h2>
                                {#if order.status === 'solicitado'}
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                        Solicitado
                                    </span>
                                {:else if order.status === 'recibido'}
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                        Recibido
                                    </span>
                                {:else if order.status === 'cancelado'}
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                        Cancelado
                                    </span>
                                {/if}
                            </div>
                            <p class="text-xs text-muted-foreground mt-0.5">Fecha: {formatDate(order.created_at)}</p>
                        </div>
                    </div>
                </div>
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
                                        <h5 class="text-sm font-medium text-foreground leading-tight">
                                            {item.product_name}
                                        </h5>
                                        {#if item.product?.sku}
                                            <span class="text-xs text-muted-foreground tabular-nums block mt-0.5">
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
                                    <div class="text-sm font-bold tabular-nums text-foreground">
                                        {Number(item.subtotal) > 0 ? formatPrice(item.subtotal) : '-'}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Tabla en Desktop (hidden sm:block) -->
                    <div class="hidden sm:block rounded-xl border overflow-hidden">
                        <table class="w-full text-left text-sm font-medium">
                            <thead class="bg-muted/40 text-muted-foreground border-b text-xs font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="p-2.5">Producto</th>
                                    <th class="p-2.5 text-center">Cant. Solicitada</th>
                                    <th class="p-2.5 text-right">Costo Unit.</th>
                                    <th class="p-2.5 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                {#each order.items ?? [] as item}
                                    <tr class="text-sm font-medium">
                                        <td class="p-2.5 text-foreground">
                                            {item.product_name}
                                            {#if item.product?.sku}
                                                <span class="text-xs text-muted-foreground block tabular-nums">SKU: {item.product.sku}</span>
                                            {/if}
                                        </td>
                                        <td class="p-2.5 text-center font-sans tabular-nums">
                                            {item.quantity} {item.product?.sale_type === 'weight' ? 'Kg' : 'unid.'}
                                        </td>
                                        <td class="p-2.5 text-right font-sans tabular-nums">
                                            {Number(item.unit_cost) > 0 ? formatPrice(item.unit_cost) : '-'}
                                        </td>
                                        <td class="p-2.5 text-right text-sm font-bold tabular-nums text-foreground">
                                            {Number(item.subtotal) > 0 ? formatPrice(item.subtotal) : '-'}
                                        </td>
                                    </tr>
                                {/each}

                            </tbody>
                        </table>
                    </div>
                </div>

                {#if order.notes}
                    <div class="p-2.5 rounded-lg bg-muted/20 border text-sm text-muted-foreground">
                        <strong class="text-foreground">Notas:</strong> {order.notes}
                    </div>
                {/if}

                <!-- Acciones de Estado -->
                {#if order.status === 'solicitado' && hasPermission('proveedores.pedidos.recibir')}
                    <div class="p-3 rounded-xl bg-primary/5 border border-primary/20 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold text-foreground block">¿Llegó la mercadería al depósito?</span>
                            <p class="text-xs text-muted-foreground">Al marcar como recibido, se sumará el stock de cada artículo en Kardex.</p>
                        </div>
                        <Button size="sm" class="min-h-[38px] text-sm font-medium gap-1.5 shrink-0 bg-emerald-600 hover:bg-emerald-700 text-white" onclick={promptReceive} disabled={isProcessing}>
                            <PackageCheck class="size-4" />
                            Marcar como Recibido
                        </Button>
                    </div>
                {/if}
            </div>

            {#snippet btnCancel(extraClass = '')}
                {#if order.status === 'solicitado' && hasPermission('proveedores.pedidos.cancelar')}
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] px-3 rounded-xl text-sm font-medium text-destructive border-destructive/30 hover:bg-destructive/10 hover:border-destructive/50 gap-1.5 cursor-pointer {extraClass}"
                        onclick={promptCancel}
                        disabled={isProcessing}
                    >
                        <XCircle class="size-4 shrink-0" />
                        <span class="break-words text-center leading-tight">Cancelar orden</span>
                    </Button>
                {/if}
            {/snippet}

            {#snippet btnPdf(extraClass = '')}
                <Button
                    type="button"
                    variant="secondary"
                    class="min-h-[44px] px-3 rounded-xl text-sm font-medium gap-1.5 shadow-xs cursor-pointer {extraClass}"
                    onclick={() => (isPdfModalOpen = true)}
                >
                    <Printer class="size-4 text-primary shrink-0" />
                    <span class="break-words text-center leading-tight">Ver PDF</span>
                </Button>
            {/snippet}

            {#snippet btnClose(extraClass = '')}
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[44px] px-3.5 rounded-xl text-sm font-medium shrink-0 cursor-pointer {extraClass}"
                    onclick={() => (open = false)}
                >
                    <span class="break-words text-center leading-tight">Cerrar</span>
                </Button>
            {/snippet}

            <!-- Footer con Total y Acciones (shrink-0) -->
            <div class="flex flex-col gap-2.5 pt-3 border-t shrink-0">
                <!-- Fila de Costo Total (separado de los botones) -->
                <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-border/40 min-w-0">
                    <span class="text-xs text-muted-foreground font-medium">Costo Total:</span>
                    <span class="text-sm font-bold tabular-nums text-primary">
                        {formatPrice(order.total_cost)}
                    </span>
                </div>

                <!-- VISTA DESKTOP (sm:) -->
                <div class="hidden sm:flex sm:items-center sm:justify-between sm:gap-2 sm:w-full">
                    <div>
                        {#if order.status === 'solicitado' && hasPermission('proveedores.pedidos.cancelar')}
                            {@render btnCancel()}
                        {/if}
                    </div>

                    <div class="flex items-center gap-2.5">
                        {@render btnPdf()}
                        {@render btnClose()}
                    </div>
                </div>

                <!-- VISTA MOBILE (< sm) -->
                <div class="sm:hidden flex flex-col gap-2 w-full">
                    {#if order.status === 'solicitado' && hasPermission('proveedores.pedidos.cancelar')}
                        <!-- Si hay 3 botones: Fila 1 [Cancelar orden, Ver PDF] y Fila 2 [Cerrar] -->
                        <div class="grid grid-cols-2 gap-2 w-full">
                            {@render btnCancel('w-full justify-center')}
                            {@render btnPdf('w-full justify-center')}
                        </div>
                        <div class="w-full">
                            {@render btnClose('w-full justify-center')}
                        </div>
                    {:else}
                        <!-- Si no hay cancelar: Fila única con Ver PDF y Cerrar en 2 columnas -->
                        <div class="grid grid-cols-2 gap-2 w-full">
                            {@render btnPdf('w-full justify-center')}
                            {@render btnClose('w-full justify-center')}
                        </div>
                    {/if}
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

    <!-- Modal de Previsualización y Descarga de PDF -->
    {#if order}
        <PdfPreviewModal
            bind:open={isPdfModalOpen}
            title="Orden de Compra"
            orderNumber={order.order_number}
            previewUrl={`/tenant/proveedores/ordenes/${order.id}/previsualizar-pdf?v=${orderVersion}`}
            viewerUrl={`/tenant/proveedores/ordenes/${order.id}/visor?v=${orderVersion}`}
            downloadUrl={`/tenant/proveedores/ordenes/${order.id}/pdf?v=${orderVersion}`}
        />
    {/if}
{/if}
