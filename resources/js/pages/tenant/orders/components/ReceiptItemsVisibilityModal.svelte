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

    // Estado local para cuenta corriente, saldo a favor y deuda previa
    let showAccount = $state(false);
    let showAccountStatus = $state(true);
    let showAccountBalance = $state(true);
    let showCurrentBalanceInSubtotal = $state(false);
    let showProjectedBalanceInSubtotal = $state(false);
    let showCreditApplied = $state(false);
    let showDebtCollected = $state(false);

    // Mapa reactivo de visibilidad de ajustes id -> boolean
    let visibilityMap = $state<Record<string, boolean>>({});

    const canShowCreditApplied = $derived(
        Boolean(order?.customer_id) && Number(order?.credit_balance_applied ?? 0) > 0
    );

    const canShowDebtCollected = $derived(
        Boolean(order?.customer_id) && Number(order?.previous_debt_collected ?? 0) > 0
    );

    // Sincronizar estado cuando se abre el modal o cambia la orden
    $effect(() => {
        if (open && order) {
            const isAccountActive = Boolean(order.show_account_in_receipt);
            showAccount = isAccountActive;
            showAccountStatus = isAccountActive ? order.show_account_status_in_receipt !== false : false;
            showAccountBalance = isAccountActive ? order.show_account_balance_in_receipt !== false : false;
            showCurrentBalanceInSubtotal = isAccountActive ? Boolean(order.show_current_balance_in_receipt_subtotal) : false;
            showProjectedBalanceInSubtotal = isAccountActive ? Boolean(order.show_projected_balance_in_receipt_subtotal) : false;
            showCreditApplied = canShowCreditApplied
                ? Boolean(order.show_credit_applied_in_receipt)
                : false;
            showDebtCollected = canShowDebtCollected
                ? Boolean(order.show_debt_collected_in_receipt)
                : false;
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

    // Estado de selección del grupo Pie de Boleta: 'all' | 'none' | 'some'
    const footerGroupState = $derived.by(() => {
        if (showAccountStatus && showAccountBalance) return 'all';
        if (!showAccountStatus && !showAccountBalance) return 'none';
        return 'some';
    });

    // Estado de selección del grupo Saldos en Subtotal: 'all' | 'none' | 'some'
    const subtotalGroupState = $derived.by(() => {
        if (showCurrentBalanceInSubtotal && showProjectedBalanceInSubtotal) return 'all';
        if (!showCurrentBalanceInSubtotal && !showProjectedBalanceInSubtotal) return 'none';
        return 'some';
    });

    // Estado de selección de la Cuenta Corriente raíz: 'all' | 'none' | 'some'
    const accountState = $derived.by(() => {
        const items = [
            showAccountStatus,
            showAccountBalance,
            showCurrentBalanceInSubtotal,
            showProjectedBalanceInSubtotal,
        ];
        const checkedCount = items.filter(Boolean).length;
        if (!showAccount || checkedCount === 0) return 'none';
        if (checkedCount === items.length) return 'all';
        return 'some';
    });

    function checkParentAccountSync() {
        const hasAnyActive =
            showAccountStatus ||
            showAccountBalance ||
            showCurrentBalanceInSubtotal ||
            showProjectedBalanceInSubtotal;
        if (hasAnyActive && !showAccount) {
            showAccount = true;
        } else if (!hasAnyActive && showAccount) {
            showAccount = false;
        }
    }

    function handleToggleAccount() {
        const target = accountState !== 'all';
        showAccount = target;
        showAccountStatus = target;
        showAccountBalance = target;
        showCurrentBalanceInSubtotal = target;
        showProjectedBalanceInSubtotal = target;
    }

    function toggleFooterGroup() {
        const target = footerGroupState !== 'all';
        showAccountStatus = target;
        showAccountBalance = target;
        checkParentAccountSync();
    }

    function toggleSubtotalGroup() {
        const target = subtotalGroupState !== 'all';
        showCurrentBalanceInSubtotal = target;
        showProjectedBalanceInSubtotal = target;
        checkParentAccountSync();
    }

    function toggleAccountStatus() {
        showAccountStatus = !showAccountStatus;
        checkParentAccountSync();
    }

    function toggleAccountBalance() {
        showAccountBalance = !showAccountBalance;
        checkParentAccountSync();
    }

    function toggleCurrentBalanceInSubtotal() {
        showCurrentBalanceInSubtotal = !showCurrentBalanceInSubtotal;
        checkParentAccountSync();
    }

    function toggleProjectedBalanceInSubtotal() {
        showProjectedBalanceInSubtotal = !showProjectedBalanceInSubtotal;
        checkParentAccountSync();
    }

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
                show_account_in_receipt: showAccount,
                show_account_status_in_receipt: showAccount ? showAccountStatus : false,
                show_account_balance_in_receipt: showAccount ? showAccountBalance : false,
                show_current_balance_in_receipt_subtotal: (showAccount && order?.customer_id) ? showCurrentBalanceInSubtotal : false,
                show_projected_balance_in_receipt_subtotal: (showAccount && order?.customer_id) ? showProjectedBalanceInSubtotal : false,
                show_credit_applied_in_receipt: canShowCreditApplied ? showCreditApplied : false,
                show_debt_collected_in_receipt: canShowDebtCollected ? showDebtCollected : false,
                adjustments_visibility: visibilityMap,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (pageProps: any) => {
                    isSaving = false;
                    toast.success('Configuración de remito guardada con éxito.');
                    if (order) {
                        order.show_account_in_receipt = showAccount;
                        order.show_account_status_in_receipt = showAccount ? showAccountStatus : false;
                        order.show_account_balance_in_receipt = showAccount ? showAccountBalance : false;
                        order.show_current_balance_in_receipt_subtotal = (showAccount && order?.customer_id) ? showCurrentBalanceInSubtotal : false;
                        order.show_projected_balance_in_receipt_subtotal = (showAccount && order?.customer_id) ? showProjectedBalanceInSubtotal : false;
                        order.show_credit_applied_in_receipt = canShowCreditApplied ? showCreditApplied : false;
                        order.show_debt_collected_in_receipt = canShowDebtCollected ? showDebtCollected : false;
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
            <div class="rounded-xl border border-border/70 divide-y divide-border/60 bg-card overflow-hidden">
                <!-- 1. Nodo Cuenta Corriente -->
                <div class="p-3.5 sm:p-4 space-y-3 {order?.customer_id ? '' : 'bg-muted/20 opacity-60'}">
                    <!-- Item Padre Raíz: Datos de Cuenta corriente -->
                    <div class="flex items-start justify-between">
                        <button
                            type="button"
                            disabled={!order?.customer_id}
                            onclick={handleToggleAccount}
                            class="flex items-start gap-2.5 text-left {order?.customer_id ? 'cursor-pointer group' : 'cursor-not-allowed'}"
                        >
                            <span class="text-primary shrink-0 mt-0.5">
                                {#if accountState === 'all'}
                                    <CheckSquare class="size-4" />
                                {:else if accountState === 'some'}
                                    <MinusSquare class="size-4" />
                                {:else}
                                    <Square class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                {/if}
                            </span>
                            <div class="flex-1 min-w-0">
                                <span class="text-sm sm:text-base font-semibold text-foreground block">
                                    Datos de Cuenta corriente
                                </span>
                                <p class="text-xs sm:text-sm text-muted-foreground mt-0.5">
                                    Imprime en la ficha del cliente el estado de cuenta y el saldo disponible.
                                </p>
                                {#if !order?.customer_id}
                                    <div class="mt-1.5 flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                                        <AlertCircle class="size-3.5 shrink-0" />
                                        <span>No disponible: El pedido no tiene un cliente registrado con cuenta corriente.</span>
                                    </div>
                                {/if}
                            </div>
                        </button>
                    </div>

                    {#if order?.customer_id}
                        <!-- Ramas del árbol indentadas -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-3.5 pt-1">
                            <!-- Rama 1: Sub-item Padre: Mostrar datos de Cta. Cte. debajo en el pie de la boleta -->
                            <div class="space-y-2">
                                <button
                                    type="button"
                                    onclick={toggleFooterGroup}
                                    class="flex items-start gap-2 text-left cursor-pointer group"
                                >
                                    <span class="text-primary shrink-0 mt-0.5">
                                        {#if footerGroupState === 'all'}
                                            <CheckSquare class="size-4" />
                                        {:else if footerGroupState === 'some'}
                                            <MinusSquare class="size-4" />
                                        {:else}
                                            <Square class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                        {/if}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-foreground block">
                                            Mostrar datos de Cta. Cte. debajo en el pie de la boleta
                                        </span>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Se incluirá en el pie de la boleta la información de cuenta corriente del cliente.
                                        </p>
                                    </div>
                                </button>

                                <!-- Hijos de Rama 1 (Pie de boleta) -->
                                <div class="ml-3 pl-3 border-l border-border/50 space-y-2 pt-0.5">
                                    <!-- Hijo 1.1: Leyenda de saldo -->
                                    <label class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-muted/30 transition-colors cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={showAccountStatus}
                                            onchange={toggleAccountStatus}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs sm:text-sm font-medium text-foreground block">
                                                Mostrar leyenda de tipo de Saldo de Cta. Cte.
                                            </span>
                                            <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                                Se mostrará en el pie de la boleta una leyenda que indique si el cliente tiene "saldo a favor" o "saldo a deber".
                                            </p>
                                        </div>
                                    </label>

                                    <!-- Hijo 1.2: Saldo actual -->
                                    <label class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-muted/30 transition-colors cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={showAccountBalance}
                                            onchange={toggleAccountBalance}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs sm:text-sm font-medium text-foreground block">
                                                Mostrar el Saldo de Cta. Cte.
                                            </span>
                                            <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                                Se incluirá en el pie de la boleta el saldo actual de la cuenta corriente del cliente.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Rama 2: Sub-item Padre: Agregar saldos de cta. cte como item de la boleta -->
                            <div class="space-y-2">
                                <button
                                    type="button"
                                    onclick={toggleSubtotalGroup}
                                    class="flex items-start gap-2 text-left cursor-pointer group"
                                >
                                    <span class="text-primary shrink-0 mt-0.5">
                                        {#if subtotalGroupState === 'all'}
                                            <CheckSquare class="size-4" />
                                        {:else if subtotalGroupState === 'some'}
                                            <MinusSquare class="size-4" />
                                        {:else}
                                            <Square class="size-4 text-muted-foreground group-hover:text-primary transition-colors" />
                                        {/if}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-foreground block">
                                            Agregar saldos de cta. cte como item de la boleta
                                        </span>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Se agregarán al desglose previo al subtotal los saldos actual y proyectado.
                                        </p>
                                    </div>
                                </button>

                                <!-- Hijos de Rama 2 (Saldos como items) -->
                                <div class="ml-3 pl-3 border-l border-border/50 space-y-2 pt-0.5">
                                    <!-- Hijo 2.1: Saldo actual como item -->
                                    <label class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-muted/30 transition-colors cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={showCurrentBalanceInSubtotal}
                                            onchange={toggleCurrentBalanceInSubtotal}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs sm:text-sm font-medium text-foreground block">
                                                Agregar saldo cta. cte. actual como item
                                            </span>
                                            <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                                Se agregará como ítem a la lista del subtotal, la información del saldo actual de la Cta. Cte. del cliente.
                                            </p>
                                        </div>
                                    </label>

                                    <!-- Hijo 2.2: Saldo resultante como item -->
                                    <label class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-muted/30 transition-colors cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={showProjectedBalanceInSubtotal}
                                            onchange={toggleProjectedBalanceInSubtotal}
                                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs sm:text-sm font-medium text-foreground block">
                                                Agregar saldo cta. cte. resultante como item
                                            </span>
                                            <p class="text-[11px] sm:text-xs text-muted-foreground mt-0.5">
                                                Se agregará como ítem a la lista del subtotal, la información del saldo resultante por el precio del pedido, es decir, el saldo o deuda que le quedaría pendiente posterior al pedido.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Rama 3: Imputación de Saldo a Favor en Totales (si aplica al pedido) -->
                            {#if canShowCreditApplied}
                                <label class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-muted/30 transition-colors cursor-pointer">
                                    <input
                                        type="checkbox"
                                        bind:checked={showCreditApplied}
                                        class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-foreground block">
                                            Mostrar uso de saldo a favor en el remito
                                        </span>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Imprime en el desglose de totales el monto de saldo a favor descontado y el total a pagar final.
                                        </p>
                                    </div>
                                </label>
                            {/if}

                            <!-- Rama 4: Cobro de Deuda Previa en Totales (si aplica al pedido) -->
                            {#if canShowDebtCollected}
                                <label class="flex items-start gap-2.5 p-1.5 rounded-lg hover:bg-muted/30 transition-colors cursor-pointer">
                                    <input
                                        type="checkbox"
                                        bind:checked={showDebtCollected}
                                        class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-foreground block">
                                            Mostrar cobro de deuda previa en el remito
                                        </span>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            Imprime en el desglose de totales la línea de cobro de deuda anterior. Si se desmarca, el remito reflejará el importe total cobrado sin desglosar el ítem.
                                        </p>
                                    </div>
                                </label>
                            {/if}
                        </div>
                    {/if}
                </div>

                <!-- 2. Nodo Descuentos (Padre con Hijos) -->
                <div class="p-3.5 sm:p-4 space-y-2.5 {discounts.length === 0 ? 'bg-muted/20 opacity-60' : ''}">
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            disabled={discounts.length === 0}
                            onclick={toggleAllDiscounts}
                            class="flex items-center gap-2 text-left {discounts.length === 0 ? 'cursor-not-allowed' : 'cursor-pointer group'}"
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
                    </div>

                    {#if discounts.length === 0}
                        <p class="text-xs sm:text-sm text-muted-foreground pl-6">
                            No se aplicaron descuentos en este pedido.
                        </p>
                    {:else}
                        <!-- Sub-items indentados con línea de conexión de árbol -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-2 pt-1">
                            {#each discounts as disc (disc.id)}
                                <label class="flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-muted/40 transition-colors cursor-pointer border border-transparent hover:border-border/60">
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
                                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
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
                <div class="p-3.5 sm:p-4 space-y-2.5 {surcharges.length === 0 ? 'bg-muted/20 opacity-60' : ''}">
                    <div class="flex items-center justify-between">
                        <button
                            type="button"
                            disabled={surcharges.length === 0}
                            onclick={toggleAllSurcharges}
                            class="flex items-center gap-2 text-left {surcharges.length === 0 ? 'cursor-not-allowed' : 'cursor-pointer group'}"
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
                    </div>

                    {#if surcharges.length === 0}
                        <p class="text-xs sm:text-sm text-muted-foreground pl-6">
                            No se aplicaron recargos en este pedido.
                        </p>
                    {:else}
                        <!-- Sub-items indentados con línea de conexión de árbol -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-2 pt-1">
                            {#each surcharges as sur (sur.id)}
                                <label class="flex items-center justify-between gap-3 p-2.5 rounded-xl hover:bg-muted/40 transition-colors cursor-pointer border border-transparent hover:border-border/60">
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
                                        <span class="text-sm font-bold text-amber-600 dark:text-amber-400">
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
                variant="outline"
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
