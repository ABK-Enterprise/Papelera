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
    import Wallet from 'lucide-svelte/icons/wallet';
    import Percent from 'lucide-svelte/icons/percent';
    import DollarSign from 'lucide-svelte/icons/dollar-sign';
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
        show_account_in_receipt?: boolean;
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

    // Estado local para cuenta corriente
    let showAccount = $state(false);

    // Mapa reactivo de visibilidad de ajustes id -> boolean
    let visibilityMap = $state<Record<string, boolean>>({});

    // Sincronizar estado cuando se abre el modal o cambia la orden
    $effect(() => {
        if (open && order) {
            showAccount = Boolean(order.show_account_in_receipt);
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
                show_account_in_receipt: showAccount,
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
    <DialogContent class="sm:max-w-xl max-h-[90vh] flex flex-col p-5 sm:p-6 space-y-4">
        <DialogHeader class="shrink-0 text-left">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <FileText class="size-4" />
                </div>
                <DialogTitle class="text-base sm:text-lg font-bold text-foreground">
                    Visibilidad en Remito #{order?.order_number ?? ''}
                </DialogTitle>
            </div>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto pr-1 space-y-4">
            <!-- Árbol Jerárquico de Elementos -->
            <div class="rounded-xl border border-border/70 divide-y divide-border/60 bg-card overflow-hidden">
                <!-- 1. Nodo Cuenta Corriente -->
                <div class="p-3.5 sm:p-4 {order?.customer_id ? 'hover:bg-muted/30 transition-colors' : 'bg-muted/20 opacity-60'}">
                    <label class="flex items-start gap-3 {order?.customer_id ? 'cursor-pointer' : 'cursor-not-allowed'}">
                        <input
                            type="checkbox"
                            disabled={!order?.customer_id}
                            bind:checked={showAccount}
                            class="size-4 mt-0.5 rounded border-border text-primary focus:ring-primary shrink-0"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <Wallet class="size-3.5 text-primary shrink-0" />
                                <span class="text-xs sm:text-sm font-semibold text-foreground">
                                    Datos de Cuenta Corriente
                                </span>
                            </div>
                            <p class="text-[11px] text-muted-foreground mt-0.5">
                                Imprime en el pie del remito el límite de crédito, saldo deudor actual y saldo disponible.
                            </p>
                            {#if !order?.customer_id}
                                <div class="mt-1.5 flex items-center gap-1 text-[10px] text-amber-600 dark:text-amber-400">
                                    <AlertCircle class="size-3 shrink-0" />
                                    <span>No disponible: El pedido no tiene un cliente registrado con cuenta corriente.</span>
                                </div>
                            {:else if order?.customer}
                                <div class="mt-2 grid grid-cols-2 gap-2 p-2 rounded-lg bg-muted/40 text-[11px] font-sans tabular-nums">
                                    <div>
                                        <span class="text-muted-foreground block text-[10px]">Saldo Deudor:</span>
                                        <span class="font-semibold text-foreground">
                                            {formatCurrency(order.customer.current_balance ?? 0)}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-muted-foreground block text-[10px]">Límite de Crédito:</span>
                                        <span class="font-semibold text-foreground">
                                            {formatCurrency(order.customer.credit_limit ?? 0)}
                                        </span>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </label>
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
                            <div class="flex items-center gap-1.5">
                                <Percent class="size-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span class="text-xs sm:text-sm font-semibold text-foreground">
                                    Descuentos Aplicados ({discounts.length})
                                </span>
                            </div>
                        </button>
                        {#if discounts.length > 0}
                            <span class="text-[11px] text-muted-foreground font-sans tabular-nums">
                                {discounts.filter((d) => visibilityMap[d.id]).length} visibles
                            </span>
                        {/if}
                    </div>

                    {#if discounts.length === 0}
                        <p class="text-[11px] text-muted-foreground pl-6">
                            No se aplicaron descuentos en este pedido.
                        </p>
                    {:else}
                        <!-- Sub-items indentados con línea de conexión de árbol -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-1.5 pt-1">
                            {#each discounts as disc (disc.id)}
                                <label class="flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-muted/40 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(visibilityMap[disc.id])}
                                            onchange={() => toggleItem(disc.id)}
                                            class="size-3.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-xs font-medium text-foreground block truncate">
                                                {disc.name || 'Descuento'}
                                            </span>
                                            {#if disc.reason}
                                                <span class="text-[10px] text-muted-foreground block truncate">
                                                    {disc.reason}
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 font-sans tabular-nums">
                                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            -{formatCurrency(disc.applied_amount)}
                                        </span>
                                        {#if disc.calculation_type === 'percentage'}
                                            <span class="text-[10px] text-muted-foreground block">
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
                            <div class="flex items-center gap-1.5">
                                <DollarSign class="size-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
                                <span class="text-xs sm:text-sm font-semibold text-foreground">
                                    Recargos Aplicados ({surcharges.length})
                                </span>
                            </div>
                        </button>
                        {#if surcharges.length > 0}
                            <span class="text-[11px] text-muted-foreground font-sans tabular-nums">
                                {surcharges.filter((s) => visibilityMap[s.id]).length} visibles
                            </span>
                        {/if}
                    </div>

                    {#if surcharges.length === 0}
                        <p class="text-[11px] text-muted-foreground pl-6">
                            No se aplicaron recargos en este pedido.
                        </p>
                    {:else}
                        <!-- Sub-items indentados con línea de conexión de árbol -->
                        <div class="ml-2 pl-4 border-l-2 border-border/60 space-y-1.5 pt-1">
                            {#each surcharges as sur (sur.id)}
                                <label class="flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-muted/40 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(visibilityMap[sur.id])}
                                            onchange={() => toggleItem(sur.id)}
                                            class="size-3.5 rounded border-border text-primary focus:ring-primary shrink-0"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-xs font-medium text-foreground block truncate">
                                                {sur.name || 'Recargo'}
                                            </span>
                                            {#if sur.reason}
                                                <span class="text-[10px] text-muted-foreground block truncate">
                                                    {sur.reason}
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0 font-sans tabular-nums">
                                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                                            +{formatCurrency(sur.applied_amount)}
                                        </span>
                                        {#if sur.calculation_type === 'percentage'}
                                            <span class="text-[10px] text-muted-foreground block">
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

        <DialogFooter class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-border/60 shrink-0">
            <Button
                type="button"
                variant="outline"
                disabled={isSaving}
                class="min-h-[44px] px-4 rounded-xl text-xs sm:text-sm font-semibold cursor-pointer w-full sm:w-auto"
                onclick={() => (open = false)}
            >
                Cancelar
            </Button>
            <Button
                type="button"
                variant="default"
                disabled={isSaving}
                class="min-h-[44px] px-5 rounded-xl text-xs sm:text-sm font-semibold cursor-pointer w-full sm:w-auto gap-2"
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
