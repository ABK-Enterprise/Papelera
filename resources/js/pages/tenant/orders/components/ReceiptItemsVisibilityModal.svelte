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
    import { router } from '@inertiajs/svelte';
    import { toast } from 'svelte-sonner';
    import FileText from 'lucide-svelte/icons/file-text';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import CheckSquare from 'lucide-svelte/icons/check-square';
    import Square from 'lucide-svelte/icons/square';
    import MinusSquare from 'lucide-svelte/icons/minus-square';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Loader2 from 'lucide-svelte/icons/loader-2';

    export interface PriceAdjustmentItem {
        id: string;
        source?: string;
        source_id?: number | null;
        type: 'discount' | 'surcharge';
        name: string;
        calculation_type: 'percentage' | 'fixed';
        value: number;
        applied_amount: number;
        reason?: string | null;
        show_in_receipt?: boolean;
    }

    export interface OrderData {
        id: number;
        order_number: string;
        customer_id?: number | null;
        customer_name: string;
        credit_balance_applied?: number | string | null;
        previous_debt_collected?: number | string | null;
        show_account_in_receipt?: boolean;
        show_account_status_in_receipt?: boolean;
        show_account_balance_in_receipt?: boolean;
        show_current_balance_in_receipt_subtotal?: boolean;
        show_projected_balance_in_receipt_subtotal?: boolean;
        show_credit_applied_in_receipt?: boolean;
        show_debt_collected_in_receipt?: boolean;
        price_adjustments?: PriceAdjustmentItem[] | null;
        customer?: {
            id: number;
            name: string;
            credit_limit?: number | string | null;
            current_balance?: number | string | null;
        } | null;
    }

    let {
        open = $bindable(false),
        order = $bindable<OrderData | null>(null),
        onSuccess = undefined,
    }: {
        open: boolean;
        order: OrderData | null;
        onSuccess?: ((updatedOrder: any) => void) | undefined;
    } = $props();

    let isSaving = $state(false);

    // Estado local para visibilidad de saldo en cuenta corriente
    let showCurrentBalance = $state(false);

    // Estado reactivo para expandir/contraer nodos del árbol
    let isDiscountsExpanded = $state(false);
    let isSurchargesExpanded = $state(false);

    // Mapa reactivo de visibilidad de ajustes id -> boolean
    let visibilityMap = $state<Record<string, boolean>>({});

    // Sincronizar estado cuando se abre el modal o cambia la orden
    $effect(() => {
        if (open && order) {
            showCurrentBalance = Boolean(
                order.show_current_balance_in_receipt_subtotal ??
                order.show_account_in_receipt
            );
            const map: Record<string, boolean> = {};
            if (Array.isArray(order.price_adjustments)) {
                order.price_adjustments.forEach((adj) => {
                    if (adj.id) {
                        map[adj.id] = adj.show_in_receipt !== false;
                    }
                });
            }
            visibilityMap = map;
        }
    });

    const discounts = $derived(
        Array.isArray(order?.price_adjustments)
            ? order!.price_adjustments.filter((a) => a.type === 'discount')
            : []
    );

    const surcharges = $derived(
        Array.isArray(order?.price_adjustments)
            ? order!.price_adjustments.filter((a) => a.type === 'surcharge')
            : []
    );

    // Estado de selección del grupo Descuentos: 'all' | 'none' | 'some'
    const discountsState = $derived.by(() => {
        if (discounts.length === 0) return 'none';
        const checkedCount = discounts.filter((d) => visibilityMap[d.id]).length;
        if (checkedCount === 0) return 'none';
        if (checkedCount === discounts.length) return 'all';
        return 'some';
    });

    // Estado de selección del grupo Recargos: 'all' | 'none' | 'some'
    const surchargesState = $derived.by(() => {
        if (surcharges.length === 0) return 'none';
        const checkedCount = surcharges.filter((s) => visibilityMap[s.id]).length;
        if (checkedCount === 0) return 'none';
        if (checkedCount === surcharges.length) return 'all';
        return 'some';
    });

    function toggleAllDiscounts() {
        if (discounts.length === 0) return;
        const target = discountsState !== 'all';
        discounts.forEach((d) => {
            visibilityMap[d.id] = target;
        });
    }

    function toggleAllSurcharges() {
        if (surcharges.length === 0) return;
        const target = surchargesState !== 'all';
        surcharges.forEach((s) => {
            visibilityMap[s.id] = target;
        });
    }

    function toggleItem(id: string) {
        visibilityMap[id] = !visibilityMap[id];
    }

    function formatCurrency(amount: number | string): string {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(amount));
    }

    function saveSettings() {
        if (!order || isSaving) return;

        isSaving = true;
        router.patch(
            `/tenant/pedidos/${order.id}/visibilidad-remito`,
            {
                show_current_balance_in_receipt: showCurrentBalance,
                show_current_balance_in_receipt_subtotal: showCurrentBalance,
                show_account_in_receipt: showCurrentBalance,
                show_account_status_in_receipt: showCurrentBalance,
                show_account_balance_in_receipt: showCurrentBalance,
                show_projected_balance_in_receipt_subtotal: false,
                show_credit_applied_in_receipt: false,
                show_debt_collected_in_receipt: false,
                adjustments_visibility: visibilityMap,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (pageProps: any) => {
                    isSaving = false;
                    toast.success('Configuración de remito guardada con éxito.');
                    if (order) {
                        order.show_current_balance_in_receipt_subtotal = showCurrentBalance;
                        order.show_account_in_receipt = showCurrentBalance;
                        order.show_account_status_in_receipt = showCurrentBalance;
                        order.show_account_balance_in_receipt = showCurrentBalance;
                        order.show_projected_balance_in_receipt_subtotal = false;
                        order.show_credit_applied_in_receipt = false;
                        order.show_debt_collected_in_receipt = false;
                        if (Array.isArray(order.price_adjustments)) {
                            order.price_adjustments.forEach((adj) => {
                                if (adj.id && visibilityMap[adj.id] !== undefined) {
                                    adj.show_in_receipt = visibilityMap[adj.id];
                                }
                            });
                        }
                    }
                    if (onSuccess) {
                        onSuccess(pageProps?.props?.order || order);
                    }
                    open = false;
                },
                onError: (errors) => {
                    isSaving = false;
                    const msg = Object.values(errors)[0] || 'Error al actualizar visibilidad.';
                    toast.error(msg);
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-xl max-h-[90vh] flex flex-col p-0 gap-0 overflow-hidden">
        <DialogHeader class="p-0 border-b border-border/60 shrink-0 bg-card">
            <div class="flex items-center gap-2.5 sm:gap-3.5 p-4 sm:p-1.5 pb-3.5 sm:pb-1.5 pr-4 sm:pr-4 w-full">
                <!-- Contenedor del Icono Desacoplado: Mobile size-10 (40px) y margenes de OrderDetailModal, Desktop 48px (sm:size-12) con 3px de margen -->
                <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                    <FileText class="size-5 sm:size-6 text-primary" />
                </div>

                <!-- Contenedor Propio del Título Centrado en el Eje Horizontal -->
                <div class="min-w-0 flex-1 flex items-center">
                    <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                        Visibilidad en Remito #{order?.order_number ?? ''}
                    </DialogTitle>
                </div>
            </div>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
            <!-- Árbol Jerárquico de Elementos -->
            <div class="rounded-xl border border-border/80 dark:border-border/90 divide-y divide-border/60 bg-muted/40 dark:bg-secondary/35 overflow-hidden">
                <!-- 1. Nodo Cuenta Corriente -->
                <div class="p-3.5 sm:p-4 space-y-3 {order?.customer_id ? '' : 'bg-muted/40 dark:bg-secondary/35 opacity-60'}">
                    <label class="flex items-start gap-3 cursor-pointer {order?.customer_id ? '' : 'cursor-not-allowed'}">
                        <input
                            type="checkbox"
                            disabled={!order?.customer_id}
                            bind:checked={showCurrentBalance}
                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                        />
                        <div class="flex-1 min-w-0">
                            <span class="text-sm sm:text-base font-semibold text-foreground block">
                                Mostrar saldo de Cuenta Corriente actual
                            </span>
                            <p class="text-xs sm:text-sm text-muted-foreground mt-0.5">
                                Muestra el saldo deudor actual de la cuenta corriente como un ítem en la boleta y lo suma al total del pedido.
                            </p>
                            {#if !order?.customer_id}
                                <div class="mt-1.5 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                                    <AlertCircle class="size-3.5 shrink-0" />
                                    <span>No disponible: El pedido no tiene un cliente con cuenta corriente asignada.</span>
                                </div>
                            {/if}
                        </div>
                    </label>
                </div>

                <!-- 2. Nodo Descuentos (Padre con Hijos) -->
                <div class="p-3.5 sm:p-4 space-y-2.5 {discounts.length === 0 ? 'bg-muted/40 dark:bg-secondary/35 opacity-60' : ''}">
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            disabled={discounts.length === 0}
                            onclick={toggleAllDiscounts}
                            class="flex items-center gap-2 text-left {discounts.length === 0 ? 'cursor-not-allowed' : 'cursor-pointer group'} flex-1 min-w-0"
                        >
                            <span class="text-primary shrink-0">
                                {#if discountsState === 'all'}
                                    <CheckSquare class="size-4" />
                                {:else if discountsState === 'some'}
                                    <MinusSquare class="size-4" />
                                {:else}
                                    <Square class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                {/if}
                            </span>
                            <span class="text-sm sm:text-base font-semibold text-foreground">
                                Descuentos Aplicados ({discounts.length})
                            </span>
                        </button>
                        {#if discounts.length > 0}
                            <button
                                type="button"
                                onclick={() => (isDiscountsExpanded = !isDiscountsExpanded)}
                                class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/70 dark:hover:bg-secondary/60 transition-colors shrink-0 ml-2 cursor-pointer"
                                aria-label={isDiscountsExpanded ? 'Contraer descuentos' : 'Expandir descuentos'}
                            >
                                <ChevronRight class="size-4 transition-transform duration-200 {isDiscountsExpanded ? 'rotate-90' : ''}" />
                            </button>
                        {/if}
                    </div>

                    {#if discounts.length === 0}
                        <p class="text-xs sm:text-sm text-muted-foreground pl-6">
                            No se aplicaron descuentos en este pedido.
                        </p>
                    {:else if isDiscountsExpanded}
                        <!-- Sub-items indentados con línea de conexión de árbol -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-2 pt-1">
                            {#each discounts as disc (disc.id)}
                                <label class="flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-muted/70 dark:hover:bg-secondary/55 transition-colors cursor-pointer border border-transparent hover:border-border/80 dark:hover:border-border/90">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(visibilityMap[disc.id])}
                                            onchange={() => toggleItem(disc.id)}
                                            class="size-4 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-sm font-semibold text-foreground block truncate">
                                                {disc.name || 'Descuento'}
                                            </span>
                                            {#if disc.reason}
                                                <span class="text-xs text-muted-foreground block truncate mt-0.5">
                                                    {disc.reason}
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 font-sans tabular-nums">
                                        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                            -{formatCurrency(disc.applied_amount)}
                                        </span>
                                        {#if disc.calculation_type === 'percentage'}
                                            <span class="text-xs text-muted-foreground block">
                                                ({disc.value}%)
                                            </span>
                                        {/if}
                                    </div>
                                </label>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- 3. Nodo Recargos (Padre con Hijos) -->
                <div class="p-3.5 sm:p-4 space-y-2.5 {surcharges.length === 0 ? 'bg-muted/40 dark:bg-secondary/35 opacity-60' : ''}">
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            disabled={surcharges.length === 0}
                            onclick={toggleAllSurcharges}
                            class="flex items-center gap-2 text-left {surcharges.length === 0 ? 'cursor-not-allowed' : 'cursor-pointer group'} flex-1 min-w-0"
                        >
                            <span class="text-primary shrink-0">
                                {#if surchargesState === 'all'}
                                    <CheckSquare class="size-4" />
                                {:else if surchargesState === 'some'}
                                    <MinusSquare class="size-4" />
                                {:else}
                                    <Square class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                {/if}
                            </span>
                            <span class="text-sm sm:text-base font-semibold text-foreground">
                                Recargos Aplicados ({surcharges.length})
                            </span>
                        </button>
                        {#if surcharges.length > 0}
                            <button
                                type="button"
                                onclick={() => (isSurchargesExpanded = !isSurchargesExpanded)}
                                class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/70 dark:hover:bg-secondary/60 transition-colors shrink-0 ml-2 cursor-pointer"
                                aria-label={isSurchargesExpanded ? 'Contraer recargos' : 'Expandir recargos'}
                            >
                                <ChevronRight class="size-4 transition-transform duration-200 {isSurchargesExpanded ? 'rotate-90' : ''}" />
                            </button>
                        {/if}
                    </div>

                    {#if surcharges.length === 0}
                        <p class="text-xs sm:text-sm text-muted-foreground pl-6">
                            No se aplicaron recargos en este pedido.
                        </p>
                    {:else if isSurchargesExpanded}
                        <!-- Sub-items indentados con línea de conexión de árbol -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-2 pt-1">
                            {#each surcharges as sur (sur.id)}
                                <label class="flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-muted/70 dark:hover:bg-secondary/55 transition-colors cursor-pointer border border-transparent hover:border-border/80 dark:hover:border-border/90">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(visibilityMap[sur.id])}
                                            onchange={() => toggleItem(sur.id)}
                                            class="size-4 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-sm font-semibold text-foreground block truncate">
                                                {sur.name || 'Recargo'}
                                            </span>
                                            {#if sur.reason}
                                                <span class="text-xs text-muted-foreground block truncate mt-0.5">
                                                    {sur.reason}
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 font-sans tabular-nums">
                                        <span class="text-sm font-bold text-amber-700 dark:text-amber-400">
                                            +{formatCurrency(sur.applied_amount)}
                                        </span>
                                        {#if sur.calculation_type === 'percentage'}
                                            <span class="text-xs text-muted-foreground block">
                                                ({sur.value}%)
                                            </span>
                                        {/if}
                                    </div>
                                </label>
                            {/each}
                        </div>
                    {/if}
                </div>
            </div>
        </DialogBody>

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 p-4 sm:p-5 border-t border-border/60 bg-card shrink-0">
            <Button
                type="button"
                variant="secondary"
                disabled={isSaving}
                class="min-h-[44px] px-4 rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto"
                onclick={() => (open = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                disabled={isSaving}
                class="min-h-[44px] px-5 rounded-xl text-sm font-semibold cursor-pointer w-full sm:w-auto gap-2"
                onclick={saveSettings}
            >
                {#if isSaving}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Guardando...</span>
                {:else}
                    <FileText class="size-4" />
                    <span>Guardar Visibilidad</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
