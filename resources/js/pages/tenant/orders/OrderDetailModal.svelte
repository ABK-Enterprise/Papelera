<script lang="ts">
    import { Button } from '@/components/ui/button';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import PdfPreviewModal from '@/components/common/PdfPreviewModal.svelte';
    import MercadoPagoQrModal from '@/components/common/MercadoPagoQrModal.svelte';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import CustomerOrderReturnModal from './CustomerOrderReturnModal.svelte';
    import EmitFiscalInvoiceModal from './EmitFiscalInvoiceModal.svelte';
    import ReceiptItemsVisibilityModal from './components/ReceiptItemsVisibilityModal.svelte';
    import CreateCustomAdjustmentModal from './components/CreateCustomAdjustmentModal.svelte';
    import ChooseAdjustmentActionModal from './components/ChooseAdjustmentActionModal.svelte';
    import SelectExistingAdjustmentModal from './components/SelectExistingAdjustmentModal.svelte';
    import { router, page } from '@inertiajs/svelte';
    import { hasPermission, hasModule } from '@/lib/permissions';
    import { fade, scale } from 'svelte/transition';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Calendar from 'lucide-svelte/icons/calendar';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Clock from 'lucide-svelte/icons/clock';
    import FileText from 'lucide-svelte/icons/file-text';
    import Info from 'lucide-svelte/icons/info';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Phone from 'lucide-svelte/icons/phone';
    import Plus from 'lucide-svelte/icons/plus';
    import Printer from 'lucide-svelte/icons/printer';
    import QrCode from 'lucide-svelte/icons/qr-code';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import Tag from 'lucide-svelte/icons/tag';
    import Truck from 'lucide-svelte/icons/truck';
    import User from 'lucide-svelte/icons/user';
    import Lock from 'lucide-svelte/icons/lock';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import Wallet from 'lucide-svelte/icons/wallet';
    import XCircle from 'lucide-svelte/icons/x-circle';
    import { toast } from 'svelte-sonner';

    interface OrderItem {
        id: number;
        product_id: number;
        packaging_type: string;
        units_per_package: number;
        quantity: number;
        returned_quantity?: number;
        unit_price: string | number;
        subtotal: string | number;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            price?: string | number;
        };
    }

    interface CustomerInfo {
        id: number;
        name: string;
        tax_id?: string | null;
        tax_condition?: string | null;
        phone?: string | null;
        address?: string | null;
        is_defaulter?: boolean;
    }

    interface OrderData {
        id: number;
        order_number: string;
        customer_id?: number | null;
        customer_name: string;
        customer_phone: string | null;
        delivery_address: string | null;
        status: string;
        fiscal_status?: string;
        fiscal_error_message?: string | null;
        active_fiscal_invoice?: any;
        return_status?: string;
        payment_status?: string;
        price_review_pending?: boolean;
        payment_method?: string | null;
        payment_condition?: string | null;
        derived_payment_condition?: string | null;
        has_pending_credit_note?: boolean;
        show_account_in_receipt?: boolean;
        discount_total?: string | number;
        surcharge_total?: string | number;
        price_adjustments?: any[] | null;
        debt_amount?: string | number;
        paid_amount?: string | number;
        total_amount: string | number;
        notes: string | null;
        created_at: string;
        customer?: CustomerInfo | null;
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
    let isConfirmReturnOpen = $state(false);
    let isReturnModalOpen = $state(false);
    let isPdfModalOpen = $state(false);
    let isFiscalModalOpen = $state(false);
    let isFiscalPdfModalOpen = $state(false);
    let isQrModalOpen = $state(false);
    let isReceiptVisibilityModalOpen = $state(false);
    let isChooseAdjustmentModalOpen = $state(false);
    let isCustomAdjustmentModalOpen = $state(false);
    let isSelectExistingAdjustmentModalOpen = $state(false);
    let pendingStatus = $state<string | null>(null);
    let isEmittingCreditNote = $state(false);
    let isResolvingPrices = $state(false);

    let netProductsSubtotal = $derived(
        order?.items?.reduce((acc, it) => acc + Number(it.subtotal || 0), 0) ?? 0
    );

    function resolveKeepPrices() {
        if (!order || isResolvingPrices) return;
        isResolvingPrices = true;
        router.post(
            `/tenant/pedidos/${order.id}/conservar-precios`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Precios originales conservados para este pedido.');
                    if (order) order.price_review_pending = false;
                    isResolvingPrices = false;
                },
                onError: (errors) => {
                    const msg = Object.values(errors)[0] || 'Error al procesar la solicitud.';
                    toast.error(msg);
                    isResolvingPrices = false;
                },
                onFinish: () => {
                    isResolvingPrices = false;
                },
            }
        );
    }

    function resolveUpdatePrices() {
        if (!order || isResolvingPrices) return;
        isResolvingPrices = true;
        router.post(
            `/tenant/pedidos/${order.id}/actualizar-precios`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Precios del pedido actualizados con éxito según el catálogo.');
                    if (order) order.price_review_pending = false;
                    isResolvingPrices = false;
                },
                onError: (errors) => {
                    const msg = Object.values(errors)[0] || 'Error al actualizar precios del pedido.';
                    toast.error(msg);
                    isResolvingPrices = false;
                },
                onFinish: () => {
                    isResolvingPrices = false;
                },
            }
        );
    }

    function getMobilePaymentLabel(ord: OrderData): string {
        const raw = (ord.derived_payment_condition || ord.payment_condition || ord.payment_method || '').toLowerCase();
        if (raw.includes('transferencia')) return 'Transferencia';
        if (raw.includes('mercado') || raw.includes('mdo') || raw.includes('mp')) return 'Mdo. Pago';
        if (raw.includes('efectivo')) return 'Efectivo';
        if (raw.includes('cheque')) return 'Cheque';
        if (raw.includes('corriente') || raw.includes('cta') || raw.includes('parcial')) return 'Cta. Cte.';
        if (raw.includes('contado')) return 'Contado';
        return ord.derived_payment_condition || ord.payment_condition || ord.payment_method || 'Efectivo';
    }

    function emitCreditNote() {
        if (!order || isEmittingCreditNote) return;
        isEmittingCreditNote = true;
        router.post(
            `/tenant/pedidos/${order.id}/nota-credito`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Nota de Crédito ARCA emitida exitosamente.');
                    isEmittingCreditNote = false;
                },
                onError: (errors) => {
                    const msg = errors.fiscal_error || Object.values(errors)[0] || 'Error al emitir Nota de Crédito ARCA.';
                    toast.error(msg);
                    isEmittingCreditNote = false;
                },
                onFinish: () => {
                    isEmittingCreditNote = false;
                },
            }
        );
    }

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

    function formatDateOnly(dateStr: string | null | undefined): string {
        if (!dateStr) return '-';
        try {
            const parts = dateStr.split('T')[0].split('-');
            if (parts.length === 3) {
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            return new Date(dateStr).toLocaleDateString('es-AR');
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
            `/tenant/pedidos/${order.id}/estado`,
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




    // Modal Copy Generator según el estado a aplicar
    let confirmModalConfig = $derived.by(() => {
        if (pendingStatus === 'cancelado') {
            const description = order?.fiscal_status === 'facturado'
                ? '¡Atención! Este pedido se encuentra facturado ante ARCA. Al cancelarlo, el sistema emitirá automáticamente una Nota de Crédito oficial para anular la factura. El stock será reincorporado al inventario. ¿Deseas continuar?'
                : `¿Estás seguro de cancelar el pedido ${order?.order_number}? El stock de los artículos se reincorporará automáticamente al inventario. Esta acción es definitiva y no podrá revertirse.`;

            return {
                title: '¿Cancelar pedido de venta?',
                description,
                confirmText: 'Sí, Cancelar Pedido',
                variant: 'danger' as const,
            };
        }
        if (pendingStatus === 'entregado') {
            return {
                title: '¿Marcar pedido como Entregado?',
                description: `¿Confirmas que el pedido ${order?.order_number} fue entregado con éxito al cliente? Al marcarlo como entregado se completará la operación de forma definitiva.`,
                confirmText: 'Confirmar Entrega',
                variant: 'success' as const,
            };
        }
        if (pendingStatus === 'en_camino') {
            return {
                title: '¿Marcar pedido En Camino?',
                description: `El pedido ${order?.order_number} pasará a estado de despacho / en tránsito hacia la dirección del cliente.`,
                confirmText: 'Poner En Camino',
                variant: 'default' as const,
            };
        }
        if (pendingStatus === 'preparado') {
            return {
                title: '¿Marcar pedido como Preparado?',
                description: `El pedido ${order?.order_number} quedará empaquetado y listo para ser entregado o despachado.`,
                confirmText: 'Marcar Preparado',
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

    const canReturn = $derived(
        order?.status === 'entregado' && order?.return_status !== 'total' && hasPermission('pedidos.editar_estado')
    );
    const canEmitFiscal = $derived(
        hasModule('facturacion_arca') &&
        hasPermission('facturas.emitir') &&
        order?.status !== 'cancelado' &&
        (order?.fiscal_status === 'sin_facturar' ||
            order?.fiscal_status === 'error_fiscal' ||
            !order?.fiscal_status)
    );
    const hasFiscalPdf = $derived(
        (order?.fiscal_status === 'facturado' || Boolean(order?.active_fiscal_invoice)) &&
        (hasPermission('facturas.consultar') || hasPermission('pedidos.ver'))
    );
    const hasFiscalAction = $derived(canEmitFiscal || hasFiscalPdf);
    const hasQrPayment = $derived(
        order?.status !== 'cancelado' &&
        order?.status !== 'entregado' &&
        Number(order?.debt_amount ?? order?.total_amount) > 0
    );
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
                    <IconContainer size="md" variant="primary" icon="solar:bill-list-bold-duotone" />
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base sm:text-lg font-bold text-foreground font-sans tabular-nums">{order.order_number}</h2>
                            {#if order.status === 'en_preparacion'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    En Preparación
                                </span>
                            {:else if order.status === 'preparado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-500/15 text-blue-700 dark:text-blue-400 border border-blue-500/30">
                                    Preparado
                                </span>
                            {:else if order.status === 'en_camino'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 border border-indigo-500/30">
                                    En Camino
                                </span>
                            {:else if order.status === 'entregado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    Entregado
                                </span>
                            {:else if order.status === 'cancelado'}
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                    Cancelado
                                </span>
                            {/if}

                            <!-- Pill Pendiente NC -->
                            {#if order.has_pending_credit_note}
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                    Pendiente NC
                                </span>
                            {/if}

                            <!-- Badge de Estado Fiscal ARCA -->
                            {#if order.fiscal_status === 'facturado'}
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                                    <ReceiptText class="size-3.5" />
                                    <span>Facturado ARCA</span>
                                </span>
                            {:else if order.fiscal_status === 'anulado_fiscal'}
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 line-through">
                                    <span>Anulado Fiscal (NC)</span>
                                </span>
                            {:else if order.fiscal_status === 'error_fiscal'}
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                    <AlertTriangle class="size-3.5" />
                                    <span>Error Fiscal</span>
                                </span>
                            {:else}
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border/70">
                                    <span>Sin Facturar</span>
                                </span>
                            {/if}
                        </div>
                        <p class="text-xs text-muted-foreground flex flex-wrap items-center gap-1 break-words leading-relaxed">
                            <span>Fecha:</span>
                            <span class="break-words font-sans tabular-nums">{formatDate(order.created_at)}</span>
                        </p>
                    </div>
                </div>
            </div>


            <!-- Scrollable Content Body -->
            <div class="flex-1 min-h-0 overflow-y-auto space-y-4 sm:space-y-5 py-2 pr-1">
                <!-- Banner de Discrepancia de Precios de Catálogo -->
                {#if order.price_review_pending}
                    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-3">
                        <div class="flex items-start gap-3">
                            <AlertTriangle class="size-5 text-amber-600 shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <strong class="font-bold text-sm text-foreground block">
                                    Discrepancia en Precios de Catálogo
                                </strong>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Uno o más productos incluidos en este pedido tuvieron modificaciones de precio en el catálogo con posterioridad a la creación de la orden. Puedes conservar los valores fijados originalmente o recalcular los ítems al valor vigente del catálogo.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2.5 pt-2 border-t border-amber-500/20">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="min-h-[36px] rounded-lg text-xs font-semibold border-amber-500/40 hover:bg-amber-500/15 text-foreground cursor-pointer"
                                disabled={isResolvingPrices}
                                onclick={resolveKeepPrices}
                            >
                                Conservar precios originales
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                class="min-h-[36px] rounded-lg text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white cursor-pointer gap-1.5"
                                disabled={isResolvingPrices}
                                onclick={resolveUpdatePrices}
                            >
                                {#if isResolvingPrices}
                                    <Loader2 class="size-3.5 animate-spin" />
                                    <span>Actualizando...</span>
                                {:else}
                                    <span>Actualizar al precio vigente</span>
                                {/if}
                            </Button>
                        </div>
                    </div>
                {/if}

                <!-- Banner de Inconveniente Fiscal en caso de error previo -->
                {#if order.fiscal_status === 'error_fiscal' && order.fiscal_error_message}
                    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3 text-xs text-amber-800 dark:text-amber-300">
                        <AlertTriangle class="size-5 text-amber-600 shrink-0 mt-0.5" />
                        <div class="space-y-0.5">
                            <strong class="font-bold block">Inconveniente de facturación ARCA:</strong>
                            <p class="leading-relaxed">{order.fiscal_error_message}</p>
                            <p class="text-[11px] text-muted-foreground pt-1">
                                Puedes reintentar la emisión con el botón «Facturar ARCA» al pie de la ventana.
                            </p>
                        </div>
                    </div>
                {/if}

                <!-- Bloque Informativo de Factura Oficial Aprobada en ARCA -->
                {#if order.fiscal_status === 'facturado' && order.active_fiscal_invoice}
                    <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="size-8 rounded-lg bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 flex items-center justify-center shrink-0">
                                <ReceiptText class="size-4.5" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <strong class="font-bold text-foreground text-sm font-sans tabular-nums">
                                        {order.active_fiscal_invoice.formatted_number || `Comprobante Nº ${order.active_fiscal_invoice.numero_comprobante}`}
                                    </strong>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/20 text-emerald-700 dark:text-emerald-400">
                                        CAE Oficial
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground mt-0.5 font-sans tabular-nums text-[11px]">
                                    <span>CAE: <strong class="text-foreground">{order.active_fiscal_invoice.cae}</strong></span>
                                    <span>Vencimiento CAE: <strong class="text-foreground">{formatDateOnly(order.active_fiscal_invoice.cae_due_date)}</strong></span>
                                </div>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="min-h-[36px] px-3 rounded-lg text-xs font-semibold text-emerald-700 dark:text-emerald-300 border-emerald-500/30 hover:bg-emerald-500/15 gap-1.5 cursor-pointer shrink-0"
                            onclick={() => (isFiscalPdfModalOpen = true)}
                        >
                            <FileText class="size-3.5" />
                            <span>Ver Factura</span>
                        </Button>
                    </div>
                {/if}

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
                        <p class="font-semibold text-foreground">{order.customer_phone || 'No especificado'}</p>
                    </div>
                    <div class="space-y-1">
                        <span class="text-muted-foreground flex items-center gap-1">
                            <MapPin class="size-3.5" /> Entrega:
                        </span>
                        <p class="font-semibold text-foreground">{order.delivery_address || 'Retiro en local'}</p>
                    </div>
                </div>

                <!-- Detalle de Productos -->
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Productos y Cantidades</h4>
                    
                    <!-- Stacked Cards en Mobile (block sm:hidden) -->
                    <div class="block sm:hidden space-y-2.5">
                        {#each order.items ?? [] as item}
                            <div class="p-3.5 rounded-xl border bg-muted/20 space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <h5 class="text-sm font-semibold text-foreground leading-tight">
                                            {item.product?.name || `Producto #${item.product_id}`}
                                        </h5>
                                        {#if item.product?.sku}
                                            <span class="text-xs text-muted-foreground font-sans tabular-nums block mt-0.5">
                                                SKU: {item.product.sku}
                                            </span>
                                        {/if}
                                    </div>
                                    <div class="shrink-0">
                                        {#if item.packaging_type === 'gram'}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                                Gramos
                                            </span>
                                        {:else if item.packaging_type === 'kg'}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                                                Kg
                                            </span>
                                        {:else if item.packaging_type === 'bolson' || item.packaging_type === 'bulto'}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                                Bulto x{item.units_per_package || 1}
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-muted text-muted-foreground border border-border">
                                                Unidad
                                            </span>
                                        {/if}
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-border/50">
                                    <div class="text-xs text-muted-foreground font-sans tabular-nums">
                                        <div>
                                            {formatPrice(item.unit_price)} × 
                                            {#if item.packaging_type === 'gram'}
                                                {item.quantity} g ({item.quantity / 1000} Kg)
                                            {:else if item.packaging_type === 'kg'}
                                                {item.quantity} Kg
                                            {:else if item.packaging_type === 'bolson' || item.packaging_type === 'bulto'}
                                                {item.quantity} {item.quantity === 1 ? 'bulto' : 'bultos'} ({item.quantity * (item.units_per_package || 1)} u)
                                            {:else}
                                                {item.quantity} unid.
                                            {/if}
                                        </div>
                                        {#if item.product?.price && Math.abs(Number(item.product.price) - Number(item.unit_price)) >= 0.01}
                                            <span class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold block mt-0.5">
                                                Catálogo actual: {formatPrice(item.product.price)}
                                            </span>
                                        {/if}
                                    </div>
                                    <div class="text-sm font-bold font-sans tabular-nums text-foreground">
                                        {formatPrice(item.subtotal)}
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
                                                <span class="text-[10px] text-muted-foreground block font-sans tabular-nums">SKU: {item.product.sku}</span>
                                            {/if}
                                        </td>
                                        <td class="p-2.5 text-muted-foreground">
                                            {#if item.packaging_type === 'gram'}
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400">
                                                    Gramos (g)
                                                </span>
                                            {:else if item.packaging_type === 'kg'}
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400">
                                                    Kilogramos (Kg)
                                                </span>
                                            {:else if item.packaging_type === 'bolson' || item.packaging_type === 'bulto'}
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary">
                                                    Bulto (x{item.units_per_package || 1})
                                                </span>
                                            {:else}
                                                <span>Unidad</span>
                                            {/if}
                                        </td>
                                        <td class="p-2.5 text-center font-bold font-sans tabular-nums">
                                            {#if item.packaging_type === 'gram'}
                                                {item.quantity} g
                                                <span class="text-[10px] text-muted-foreground block font-normal">
                                                    ({item.quantity / 1000} Kg)
                                                </span>
                                            {:else if item.packaging_type === 'kg'}
                                                {item.quantity} Kg
                                            {:else if item.packaging_type === 'bolson' || item.packaging_type === 'bulto'}
                                                {item.quantity} {item.quantity === 1 ? 'bulto' : 'bultos'}
                                                <span class="text-[10px] text-muted-foreground block font-normal">
                                                    ({item.quantity * (item.units_per_package || 1)})
                                                </span>
                                            {:else}
                                                {item.quantity} unid.
                                            {/if}
                                        </td>
                                        <td class="p-2.5 text-right font-sans tabular-nums">
                                            <div>{formatPrice(item.unit_price)}</div>
                                            {#if item.product?.price && Math.abs(Number(item.product.price) - Number(item.unit_price)) >= 0.01}
                                                <span class="text-[10px] text-amber-600 dark:text-amber-400 block font-normal tabular-nums">
                                                    Catálogo: {formatPrice(item.product.price)}
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="p-2.5 text-right font-sans tabular-nums font-bold text-foreground">{formatPrice(item.subtotal)}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Sección de Descuentos y Recargos Aplicados -->
                <div class="space-y-2 pt-2 border-t">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5">
                            <Tag class="size-3.5 text-primary" />
                            <span class="text-xs font-bold text-foreground">Descuentos y Recargos</span>
                        </div>
                        {#if order.status !== 'cancelado' && order.status !== 'entregado' && order.fiscal_status !== 'facturado' && hasPermission('pedidos.editar_estado')}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 px-3 rounded-lg text-[13px] font-semibold gap-1.5 text-primary border-primary/40 hover:bg-primary/10 cursor-pointer shadow-2xs"
                                onclick={() => (isChooseAdjustmentModalOpen = true)}
                            >
                                <Plus class="size-3.5" />
                                <span>Agregar</span>
                            </Button>
                        {/if}
                    </div>

                    {#if Array.isArray(order.price_adjustments) && order.price_adjustments.length > 0}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            {#each order.price_adjustments as adj}
                                <div class="p-2.5 rounded-xl border flex items-center justify-between text-xs {adj.type === 'discount' ? 'bg-emerald-500/5 border-emerald-500/20' : 'bg-amber-500/5 border-amber-500/20'}">
                                    <div class="min-w-0 pr-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-foreground truncate">{adj.name || (adj.type === 'discount' ? 'Descuento' : 'Recargo')}</span>
                                            {#if adj.calculation_type === 'percentage'}
                                                <span class="text-[10px] px-1.5 py-0.5 rounded-md bg-muted text-muted-foreground font-sans tabular-nums font-medium">%{adj.value}</span>
                                            {/if}
                                        </div>
                                        {#if adj.reason}
                                            <p class="text-[11px] text-muted-foreground truncate mt-0.5">{adj.reason}</p>
                                        {/if}
                                    </div>
                                    <div class="text-right shrink-0 font-sans tabular-nums">
                                        <span class="font-bold {adj.type === 'discount' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'}">
                                            {adj.type === 'discount' ? '-' : '+'}{formatPrice(adj.applied_amount)}
                                        </span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <p class="text-xs text-muted-foreground italic">Sin recargos ni descuentos adicionales aplicados.</p>
                    {/if}
                </div>

                {#if order.notes}
                    <div class="p-2.5 rounded-lg bg-muted/20 border text-xs text-muted-foreground">
                        <strong class="text-foreground">Notas:</strong> {order.notes}
                    </div>
                {/if}

                <!-- Cambiador de Estado Logístico (Solo si no está en estado terminal y posee permisos) -->
                {#if order.status !== 'cancelado' && order.status !== 'entregado' && (hasPermission('pedidos.editar_estado') || hasPermission('pedidos.cancelar'))}
                    <div class="space-y-2 pt-2 border-t">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-foreground block">Actualizar estado</span>
                        </div>

                        <div class="grid grid-cols-1 {order.status === 'preparado' && hasPermission('pedidos.editar_estado') ? 'sm:grid-cols-3' : 'sm:grid-cols-2'} gap-2">
                            {#if hasPermission('pedidos.editar_estado')}
                                {#if order.status === 'en_preparacion'}
                                    <button
                                        type="button"
                                        disabled={isUpdatingStatus}
                                        class="px-3 py-2.5 rounded-xl text-xs font-bold border text-center transition-all bg-blue-600 hover:bg-blue-700 text-white shadow-xs cursor-pointer"
                                        onclick={() => requestStatusChange('preparado')}
                                    >
                                        Pasar a Preparado
                                    </button>
                                {:else if order.status === 'preparado'}
                                    <button
                                        type="button"
                                        disabled={isUpdatingStatus}
                                        class="px-3 py-2.5 rounded-xl text-xs font-bold border text-center transition-all bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs cursor-pointer"
                                        onclick={() => requestStatusChange('en_camino')}
                                    >
                                        Despachar (En camino)
                                    </button>
                                    <button
                                        type="button"
                                        disabled={isUpdatingStatus}
                                        class="px-3 py-2.5 rounded-xl text-xs font-bold border text-center transition-all bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs cursor-pointer"
                                        onclick={() => requestStatusChange('entregado')}
                                    >
                                        Entregar (Retiro en local)
                                    </button>
                                {:else if order.status === 'en_camino'}
                                    <button
                                        type="button"
                                        disabled={isUpdatingStatus}
                                        class="px-3 py-2.5 rounded-xl text-xs font-bold border text-center transition-all bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs cursor-pointer"
                                        onclick={() => requestStatusChange('entregado')}
                                    >
                                        Marcar Entregado
                                    </button>
                                {/if}
                            {/if}

                            <!-- Botón Cancelar disponible para pedidos activos -->
                            {#if hasPermission('pedidos.cancelar')}
                                <button
                                    type="button"
                                    disabled={isUpdatingStatus}
                                    class="px-3 py-2.5 rounded-xl text-xs font-bold border text-center transition-all bg-rose-600/10 hover:bg-rose-600 hover:text-white text-rose-600 border-rose-500/30 cursor-pointer"
                                    onclick={() => requestStatusChange('cancelado')}
                                >
                                    Cancelar Pedido
                                </button>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Snippets de Botones de Acción (Reutilizados para Desktop y Mobile) -->
            {#snippet btnDevolucion(extraClass = '')}
                {#if canReturn}
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[42px] sm:min-h-[40px] px-3.5 rounded-xl text-xs font-semibold text-amber-600 border-amber-500/30 hover:bg-amber-500/10 hover:border-amber-500/50 gap-1.5 shrink-0 cursor-pointer {extraClass}"
                        onclick={() => (isConfirmReturnOpen = true)}
                    >
                        <RotateCcw class="size-4 shrink-0" />
                        <span class="truncate">Devolución</span>
                    </Button>
                {/if}
            {/snippet}

            {#snippet btnFiscal(extraClass = '')}
                {#if canEmitFiscal}
                    {#if Number(order?.total_amount) <= 0}
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-[42px] sm:min-h-[40px] px-3 rounded-xl text-xs sm:text-sm font-semibold gap-1.5 shadow-xs shrink-0 bg-muted text-muted-foreground border border-border cursor-not-allowed opacity-60 {extraClass}"
                            onclick={() => toast.error('WSFEv1 (ARCA) requiere comprobantes con importe total mayor a cero ($0.00). Para entregas sin cargo utilice remito interno.')}
                        >
                            <Lock class="size-4 shrink-0" />
                            <span class="truncate">Facturar ARCA</span>
                        </Button>
                    {:else}
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-[42px] sm:min-h-[40px] px-3 rounded-xl text-xs sm:text-sm font-semibold gap-1.5 shadow-xs shrink-0 cursor-pointer text-foreground hover:text-primary hover:border-primary/50 {extraClass}"
                            onclick={() => (isFiscalModalOpen = true)}
                        >
                            <ReceiptText class="size-4 text-primary shrink-0" />
                            <span class="truncate">Facturar ARCA</span>
                        </Button>
                    {/if}
                {:else if hasFiscalPdf}
                    <Button
                        type="button"
                        variant="secondary"
                        class="min-h-[42px] sm:min-h-[40px] px-3 rounded-xl text-xs sm:text-sm font-semibold gap-1.5 shadow-xs shrink-0 cursor-pointer {extraClass}"
                        onclick={() => (isFiscalPdfModalOpen = true)}
                    >
                        <FileText class="size-4 text-primary shrink-0" />
                        <span class="truncate">Factura PDF</span>
                    </Button>
                {/if}
            {/snippet}

            {#snippet btnCreditNote(extraClass = '')}
                {#if order?.has_pending_credit_note && hasPermission('facturas.anular')}
                    <Button
                        type="button"
                        variant="outline"
                        disabled={isEmittingCreditNote}
                        class="min-h-[42px] sm:min-h-[40px] px-3 rounded-xl text-xs sm:text-sm font-semibold gap-1.5 shadow-xs shrink-0 cursor-pointer bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/30 hover:bg-rose-500/20 {extraClass}"
                        onclick={emitCreditNote}
                    >
                        {#if isEmittingCreditNote}
                            <Loader2 class="size-4 animate-spin shrink-0" />
                            <span class="truncate">Emitiendo NC...</span>
                        {:else}
                            <ReceiptText class="size-4 text-rose-600 dark:text-rose-400 shrink-0" />
                            <span class="truncate">Emitir Nota de Crédito ARCA</span>
                        {/if}
                    </Button>
                {/if}
            {/snippet}

            {#snippet btnQr(extraClass = '')}
                {#if hasQrPayment}
                    <Button
                        type="button"
                        variant="default"
                        class="min-h-[42px] sm:min-h-[40px] px-3 rounded-xl text-xs sm:text-sm font-semibold gap-1.5 shadow-xs bg-sky-600 hover:bg-sky-700 text-white dark:bg-sky-600 dark:hover:bg-sky-700 shrink-0 cursor-pointer {extraClass}"
                        onclick={() => (isQrModalOpen = true)}
                    >
                        <QrCode class="size-4 shrink-0" />
                        <span class="hidden sm:inline">Cobro QR MP</span>
                        <span class="inline sm:hidden">QR (MP)</span>
                    </Button>
                {/if}
            {/snippet}

            {#snippet btnRemito(extraClass = '')}
                <div class="inline-flex items-center {extraClass}">
                    <Button
                        type="button"
                        variant="secondary"
                        class="min-h-[42px] sm:min-h-[40px] px-3 rounded-l-xl rounded-r-none text-xs sm:text-sm font-semibold gap-1.5 shadow-xs shrink-0 cursor-pointer flex-1 justify-center border-r-0"
                        onclick={() => (isPdfModalOpen = true)}
                    >
                        <Printer class="size-4 text-primary shrink-0" />
                        <span>Remito</span>
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        class="min-h-[42px] sm:min-h-[40px] px-2.5 rounded-r-xl rounded-l-none border-l border-border/60 text-xs sm:text-sm font-semibold shadow-xs shrink-0 cursor-pointer hover:bg-muted"
                        title="Configurar visibilidad en remito"
                        onclick={() => (isReceiptVisibilityModalOpen = true)}
                    >
                        <SlidersHorizontal class="size-3.5 text-muted-foreground hover:text-foreground" />
                    </Button>
                </div>
            {/snippet}

            {#snippet btnCerrar(extraClass = '')}
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-[42px] sm:min-h-[40px] px-3.5 rounded-xl text-xs sm:text-sm font-semibold shrink-0 cursor-pointer {extraClass}"
                    onclick={() => (open = false)}
                >
                    Cerrar
                </Button>
            {/snippet}

            <!-- Footer con Total (Fila 1) y Acciones (Fila 2) (shrink-0) -->
            <div class="flex flex-col gap-2.5 pt-3 border-t shrink-0">
                <!-- Fila 1: Método de pago a la izquierda y Total a la derecha (simétricos) -->
                <div class="flex items-center justify-between gap-2 pb-1.5 border-b border-border/40 min-w-0">
                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground font-medium min-w-0">
                        <Wallet class="size-4 shrink-0 text-muted-foreground" />
                        <span class="hidden sm:inline truncate" title={order.derived_payment_condition || order.payment_condition || 'Contado'}>
                            {order.derived_payment_condition || order.payment_condition || 'Contado'}
                        </span>
                        <span class="inline sm:hidden truncate">
                            {getMobilePaymentLabel(order)}
                        </span>
                    </div>

                    <div class="flex items-baseline gap-1.5 shrink-0">
                        <span class="text-xs text-muted-foreground font-medium">Total:</span>
                        <span class="text-base sm:text-lg font-bold font-sans tabular-nums text-primary" title={formatPrice(order.total_amount)}>
                            {formatPrice(order.total_amount)}
                        </span>
                    </div>
                </div>

                <!-- VISTA DESKTOP (sm:) -->
                <div class="hidden sm:flex sm:items-center sm:justify-between sm:gap-2 sm:w-full">
                    <div class="flex flex-wrap items-center gap-2">
                        {#if order.return_status === 'total'}
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30 shrink-0">
                                Devolución Total
                            </span>
                        {:else if order.return_status === 'parcial'}
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30 shrink-0">
                                Devolución Parcial
                            </span>
                        {/if}

                        {@render btnDevolucion()}
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        {@render btnCreditNote()}
                        {@render btnQr()}
                        {@render btnFiscal()}
                        {@render btnRemito()}
                        {@render btnCerrar()}
                    </div>
                </div>

                <!-- VISTA MOBILE (< sm) -->
                <div class="sm:hidden flex flex-col gap-2 w-full">
                    <!-- Botón Emitir NC si está pendiente -->
                    {#if order.has_pending_credit_note}
                        {@render btnCreditNote('w-full justify-center')}
                    {/if}

                    <!-- Badge de devolución si aplica -->
                    {#if order.return_status === 'total'}
                        <div class="flex items-center justify-start pb-0.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-400 border border-rose-500/30">
                                Devolución Total
                            </span>
                        </div>
                    {:else if order.return_status === 'parcial'}
                        <div class="flex items-center justify-start pb-0.5">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                Devolución Parcial
                            </span>
                        </div>
                    {/if}

                    {#if order.status === 'entregado'}
                        {#if canReturn && hasFiscalAction}
                            <!-- Grid 2x2: Fila 1 (Facturar ARCA + Remito), Fila 2 (Devolución + Cerrar) -->
                            <div class="grid grid-cols-[1.35fr_1fr] gap-2 w-full">
                                {@render btnFiscal('w-full justify-center')}
                                {@render btnRemito('w-full justify-center')}
                            </div>
                            <div class="grid grid-cols-[1.35fr_1fr] gap-2 w-full">
                                {@render btnDevolucion('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {:else if canReturn}
                            <!-- Devolución + Remito + Cerrar -->
                            {@render btnDevolucion('w-full justify-center')}
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {:else if hasFiscalAction}
                            <!-- Facturar ARCA + Remito + Cerrar -->
                            {@render btnFiscal('w-full justify-center')}
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {:else}
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {/if}
                    {:else if order.status !== 'cancelado'}
                        {#if hasQrPayment && hasFiscalAction}
                            <!-- Grid 2x2: Fila 1 (Facturar ARCA + QR MP), Fila 2 (Remito + Cerrar) -->
                            <div class="grid grid-cols-[1.35fr_1fr] gap-2 w-full">
                                {@render btnFiscal('w-full justify-center')}
                                {@render btnQr('w-full justify-center')}
                            </div>
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {:else if hasQrPayment}
                            {@render btnQr('w-full justify-center')}
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {:else if hasFiscalAction}
                            {@render btnFiscal('w-full justify-center')}
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {:else}
                            <div class="grid grid-cols-2 gap-2 w-full">
                                {@render btnRemito('w-full justify-center')}
                                {@render btnCerrar('w-full justify-center')}
                            </div>
                        {/if}
                    {:else}
                        <!-- Cancelado: Remito y Cerrar -->
                        <div class="grid grid-cols-2 gap-2 w-full">
                            {@render btnRemito('w-full justify-center')}
                            {@render btnCerrar('w-full justify-center')}
                        </div>
                    {/if}
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



    <!-- Modal de Previsualización y Descarga de PDF -->
    {#if order}
        <PdfPreviewModal
            bind:open={isPdfModalOpen}
            title="Comprobante de Pedido"
            orderNumber={order.order_number}
            previewUrl={`/tenant/pedidos/${order.id}/previsualizar-pdf`}
            viewerUrl={`/tenant/pedidos/${order.id}/visor`}
            downloadUrl={`/tenant/pedidos/${order.id}/pdf`}
        />

        <!-- Modal de Facturación Electrónica ARCA -->
        <EmitFiscalInvoiceModal
            bind:open={isFiscalModalOpen}
            {order}
            onSuccess={() => {
                router.reload({ only: ['orders'] });
            }}
        />

        <!-- Modal de Previsualización y Descarga de Factura Fiscal Oficial ARCA -->
        <PdfPreviewModal
            bind:open={isFiscalPdfModalOpen}
            title="Factura Electrónica ARCA"
            orderNumber={order.order_number}
            previewUrl={`/tenant/pedidos/${order.id}/factura-previsualizar`}
            viewerUrl={`/tenant/pedidos/${order.id}/factura-visor`}
            downloadUrl={`/tenant/pedidos/${order.id}/factura-pdf`}
        />

        <!-- Modal de Cobro QR Presencial Mercado Pago -->
        <MercadoPagoQrModal
            bind:open={isQrModalOpen}
            {order}
            onSuccess={(updatedOrder) => {
                if (order && updatedOrder) {
                    order.debt_amount = updatedOrder.debt_amount;
                    order.paid_amount = updatedOrder.paid_amount;
                    order.payment_status = updatedOrder.payment_status;
                    if (updatedOrder.status) {
                        order.status = updatedOrder.status;
                    }
                }
                router.reload({ only: ['orders'] });
            }}
        />

        <!-- Modal de Confirmación para Devolución de Pedido -->
        <ConfirmActionModal
            bind:open={isConfirmReturnOpen}
            title="¿Registrar Devolución de Pedido?"
            description="Se abrirá el asistente para seleccionar los ítems a devolver, indicando reingreso a stock o merma/dañado, y acreditando el saldo correspondiente en cuenta corriente."
            confirmText="Continuar a Devolución"
            cancelText="Volver"
            variant="warning"
            onconfirm={() => {
                isConfirmReturnOpen = false;
                isReturnModalOpen = true;
            }}
        />

        <!-- Modal de Asistente de Devolución de Productos -->
        <CustomerOrderReturnModal
            bind:open={isReturnModalOpen}
            order={order as any}
            onSuccess={() => {
                router.reload({ only: ['orders'] });
            }}
        />

        <!-- Modal de Visibilidad en Remito -->
        <ReceiptItemsVisibilityModal
            bind:open={isReceiptVisibilityModalOpen}
            bind:order={order as any}
            onSuccess={() => {
                router.reload({ only: ['orders'] });
            }}
        />

        <!-- Modal de Elección de Ajuste -->
        <ChooseAdjustmentActionModal
            bind:open={isChooseAdjustmentModalOpen}
            onCreate={() => (isCustomAdjustmentModalOpen = true)}
            onSelectExisting={() => (isSelectExistingAdjustmentModalOpen = true)}
        />

        <!-- Modal de Ajuste Existente del Catálogo -->
        <SelectExistingAdjustmentModal
            bind:open={isSelectExistingAdjustmentModalOpen}
            orderId={order.id}
            orderDate={order.created_at}
            netProductsAmount={netProductsSubtotal}
            currentAdjustments={order.price_adjustments || []}
            onBack={() => {
                isChooseAdjustmentModalOpen = true;
            }}
            onSuccess={(updatedOrder) => {
                if (order && updatedOrder) {
                    order = {
                        ...order,
                        ...updatedOrder,
                    };
                }
                router.reload({ only: ['orders'] });
            }}
        />

        <!-- Modal de Ajuste Personalizado (Recargo / Descuento) -->
        <CreateCustomAdjustmentModal
            bind:open={isCustomAdjustmentModalOpen}
            orderId={order.id}
            orderNumber={order.order_number}
            netProductsAmount={netProductsSubtotal}
            onBack={() => {
                isChooseAdjustmentModalOpen = true;
            }}
            onSuccess={(updatedOrder) => {
                if (order && updatedOrder) {
                    order = {
                        ...order,
                        ...updatedOrder,
                    };
                }
                router.reload({ only: ['orders'] });
            }}
        />
    {/if}
{/if}
