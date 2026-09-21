<script lang="ts">
    import { Button } from '@/components/ui/button';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import { router } from '@inertiajs/svelte';
    import { fade, scale } from 'svelte/transition';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Minus from 'lucide-svelte/icons/minus';
    import Plus from 'lucide-svelte/icons/plus';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import X from 'lucide-svelte/icons/x';

    export interface ReturnableOrderItem {
        id: number;
        product_id: number | null;
        packaging_type: string;
        units_per_package?: number;
        quantity: number;
        returned_quantity?: number;
        unit_price: string | number;
        subtotal: string | number;
        product?: {
            id: number;
            name: string;
            sku: string | null;
        } | null;
    }

    export interface OrderForReturn {
        id: number;
        order_number: string;
        customer_name: string;
        customer_id?: number | null;
        status: string;
        payment_status?: string;
        total_amount: string | number;
        debt_amount?: string | number;
        items?: ReturnableOrderItem[];
    }

    let {
        open = $bindable(false),
        order = $bindable<OrderForReturn | null>(null),
        onSuccess,
    }: {
        open: boolean;
        order: OrderForReturn | null;
        onSuccess?: () => void;
    } = $props();

    interface ItemReturnState {
        order_item_id: number;
        selected: boolean;
        quantity: number;
        max_quantity: number;
        unit_price: number;
        destination: 'restock' | 'scrap';
        product_name: string;
        sku: string | null;
        packaging_type: string;
    }

    let itemStates = $state<ItemReturnState[]>([]);
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    $effect(() => {
        if (open && order?.items) {
            itemStates = order.items.map((it) => {
                const returned = Number(it.returned_quantity) || 0;
                const available = Math.max(0, Number(it.quantity) - returned);
                return {
                    order_item_id: it.id,
                    selected: false,
                    quantity: available > 0 ? (it.packaging_type === 'kg' ? Math.min(1, available) : 1) : 0,
                    max_quantity: available,
                    unit_price: Number(it.unit_price) || 0,
                    destination: 'restock',
                    product_name: it.product?.name ?? 'Producto',
                    sku: it.product?.sku ?? null,
                    packaging_type: it.packaging_type,
                };
            });
            notes = '';
            errorMessage = null;
        }
    });

    let selectedItems = $derived(itemStates.filter((it) => it.selected && it.quantity > 0));

    let totalRefundAmount = $derived(
        selectedItems.reduce((acc, it) => acc + (it.quantity * it.unit_price), 0)
    );

    let allSelected = $derived(
        itemStates.length > 0 && itemStates.every((it) => it.max_quantity === 0 || it.selected)
    );

    function toggleSelectAll() {
        const target = !allSelected;
        for (const it of itemStates) {
            if (it.max_quantity > 0) {
                it.selected = target;
            }
        }
    }

    function incrementItemQty(index: number) {
        const it = itemStates[index];
        if (!it) return;
        const step = it.packaging_type === 'kg' ? 0.5 : (it.packaging_type === 'gram' ? 50 : 1);
        if (it.quantity + step <= it.max_quantity) {
            it.quantity = Math.round((it.quantity + step) * 1000) / 1000;
        } else {
            it.quantity = it.max_quantity;
        }
    }

    function decrementItemQty(index: number) {
        const it = itemStates[index];
        if (!it) return;
        const step = it.packaging_type === 'kg' ? 0.5 : (it.packaging_type === 'gram' ? 50 : 1);
        const minVal = it.packaging_type === 'kg' ? 0.1 : (it.packaging_type === 'gram' ? 50 : 1);
        if (it.quantity - step >= minVal) {
            it.quantity = Math.round((it.quantity - step) * 1000) / 1000;
        } else {
            it.quantity = minVal;
        }
    }

    function formatPrice(amount: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    }

    function closeModal() {
        open = false;
        errorMessage = null;
    }

    function handleSubmit() {
        if (!order) return;
        if (selectedItems.length === 0) {
            errorMessage = 'Debes seleccionar al menos un producto con cantidad mayor a cero para devolver.';
            return;
        }

        for (const it of selectedItems) {
            if (it.quantity <= 0 || it.quantity > it.max_quantity) {
                errorMessage = `La cantidad a devolver de "${it.product_name}" no puede superar ${it.max_quantity}.`;
                return;
            }
        }

        isSubmitting = true;
        errorMessage = null;

        const payload = {
            items: selectedItems.map((it) => ({
                order_item_id: it.order_item_id,
                quantity: it.quantity,
                destination: it.destination,
            })),
            notes: notes.trim() || null,
        };

        router.post(
            `/tenant/pedidos/${order.id}/devolucion`,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                    onSuccess?.();
                },
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    errorMessage = typeof first === 'string' ? first : 'Error al registrar la devolución.';
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            }
        );
    }
</script>

{#if open && order}
    <!-- Backdrop accesible -->
    <div
        transition:fade={{ duration: 150 }}
        class="fixed inset-0 bg-background/80 backdrop-blur-xs z-50"
        onclick={closeModal}
        aria-hidden="true"
    ></div>

    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto pointer-events-none">
        <div
            transition:scale={{ start: 0.97, duration: 150 }}
            class="relative w-full max-w-2xl rounded-2xl sm:rounded-3xl bg-card border border-border shadow-2xl p-4 sm:p-6 max-h-[85vh] sm:max-h-[88vh] flex flex-col my-auto pointer-events-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="return-modal-title"
        >
            <!-- Header Bento -->
            <div class="flex items-start justify-between gap-3 border-b pb-3.5 sm:pb-4 shrink-0">
                <div class="min-w-0 flex-1 flex items-start gap-2.5">
                    <div class="shrink-0 mt-0.5">
                        <IconContainer size="md" variant="warning" icon="solar:restart-bold-duotone" />
                    </div>
                    <div>
                        <h2 id="return-modal-title" class="text-base sm:text-lg font-bold text-foreground leading-snug line-clamp-2 break-words">
                            Registrar Devolución — {order.order_number}
                        </h2>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Cliente: <strong class="text-foreground">{order.customer_name}</strong>
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="size-8 rounded-lg flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted/80 transition-colors shrink-0 cursor-pointer"
                    onclick={closeModal}
                    aria-label="Cerrar modal"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Error Banner -->
            {#if errorMessage}
                <div class="mt-3 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-600 dark:text-rose-400 flex items-center gap-2">
                    <ShieldAlert class="size-4 shrink-0" />
                    <span>{errorMessage}</span>
                </div>
            {/if}

            <!-- Body Scrollable -->
            <div class="flex-1 overflow-y-auto space-y-4 py-4 pr-1">
                <!-- Selector global y aviso -->
                <div class="flex items-center justify-between gap-2 p-3 rounded-xl bg-muted/20 border border-border/70">
                    <div class="flex items-center gap-2">
                        <input
                            id="select-all-return"
                            type="checkbox"
                            checked={allSelected}
                            onchange={toggleSelectAll}
                            class="size-4 rounded border-border text-primary focus:ring-primary cursor-pointer"
                        />
                        <label for="select-all-return" class="text-xs font-semibold text-foreground cursor-pointer">
                            Seleccionar todos los disponibles
                        </label>
                    </div>
                    <span class="text-[11px] text-muted-foreground">
                        {selectedItems.length} seleccionados
                    </span>
                </div>

                <!-- Lista de Productos a Devolver -->
                <div class="space-y-2.5">
                    {#each itemStates as item, index (item.order_item_id)}
                        {@const isExhausted = item.max_quantity <= 0}
                        <div class="p-3.5 rounded-xl border transition-all {item.selected ? 'bg-primary/5 border-primary/40 shadow-xs' : 'bg-card border-border/70 opacity-90'} {isExhausted ? 'opacity-50 bg-muted/20' : ''}">
                            <div class="flex items-start gap-3">
                                <input
                                    type="checkbox"
                                    bind:checked={item.selected}
                                    disabled={isExhausted}
                                    class="mt-1 size-4 rounded border-border text-primary focus:ring-primary cursor-pointer disabled:cursor-not-allowed"
                                />

                                <div class="flex-1 min-w-0 space-y-2">
                                    <!-- Cabecera del ítem -->
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-bold text-foreground line-clamp-1 break-words">
                                                {item.product_name}
                                            </h4>
                                            {#if item.sku}
                                                <span class="text-[11px] font-sans tabular-nums text-muted-foreground">
                                                    SKU: {item.sku}
                                                </span>
                                            {/if}
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="text-xs font-bold font-sans tabular-nums text-foreground block">
                                                {formatPrice(item.unit_price)}
                                            </span>
                                            <span class="text-[10px] text-muted-foreground tabular-nums">
                                                {#if isExhausted}
                                                    <strong class="text-muted-foreground">Totalmente devuelto</strong>
                                                {:else}
                                                    Remanente: <strong>{item.max_quantity}</strong>
                                                {/if}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Controles cuando está seleccionado -->
                                    {#if item.selected && !isExhausted}
                                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border/50">
                                            <!-- Destino físico -->
                                            <div class="flex items-center gap-2">
                                                <label for="dest_{index}" class="text-[11px] font-semibold text-muted-foreground">Destino:</label>
                                                <select
                                                    id="dest_{index}"
                                                    bind:value={item.destination}
                                                    class="h-8 rounded-lg border border-border/80 bg-background px-2 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 cursor-pointer shadow-2xs"
                                                >
                                                    <option value="restock">Reingreso a Stock (+)</option>
                                                    <option value="scrap">Merma / Dañado (Scrap)</option>
                                                </select>
                                            </div>

                                            <!-- Stepper de Cantidad -->
                                            <div class="flex items-center border rounded-lg bg-background shadow-2xs h-8 overflow-hidden">
                                                <button
                                                    type="button"
                                                    class="size-8 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 cursor-pointer"
                                                    onclick={() => decrementItemQty(index)}
                                                    disabled={item.quantity <= (item.packaging_type === 'kg' ? 0.1 : (item.packaging_type === 'gram' ? 50 : 1))}
                                                >
                                                    <Minus class="size-3" />
                                                </button>

                                                <input
                                                    type="number"
                                                    step={item.packaging_type === 'kg' ? '0.1' : (item.packaging_type === 'gram' ? '50' : '1')}
                                                    min={item.packaging_type === 'kg' ? 0.1 : (item.packaging_type === 'gram' ? 50 : 1)}
                                                    max={item.max_quantity}
                                                    bind:value={item.quantity}
                                                    class="w-14 h-full text-xs font-bold font-sans tabular-nums text-center bg-transparent border-0 outline-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                />

                                                <button
                                                    type="button"
                                                    class="size-8 flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-40 cursor-pointer"
                                                    onclick={() => incrementItemQty(index)}
                                                    disabled={item.quantity >= item.max_quantity}
                                                >
                                                    <Plus class="size-3" />
                                                </button>
                                            </div>

                                            <!-- Subtotal de devolución -->
                                            <div class="text-right font-sans tabular-nums text-xs font-bold text-primary shrink-0 ml-auto sm:ml-0">
                                                {formatPrice(item.quantity * item.unit_price)}
                                            </div>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>

                <!-- Campo de Observaciones -->
                <div class="space-y-1.5 pt-2">
                    <label for="return-notes" class="text-xs font-semibold text-foreground">
                        Motivo / Notas de la Devolución (Opcional)
                    </label>
                    <textarea
                        id="return-notes"
                        bind:value={notes}
                        placeholder="Ej. Producto devuelto por falla de empaque o error de pedido..."
                        rows="2"
                        class="w-full rounded-xl border border-border/80 bg-background p-2.5 text-xs text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs"
                    ></textarea>
                </div>
            </div>

            <!-- Footer con Resumen y Botones -->
            <div class="pt-3.5 border-t space-y-3 shrink-0">
                <div class="flex items-center justify-between p-3 rounded-xl bg-muted/30 border border-border/60">
                    <div>
                        <span class="text-xs text-muted-foreground font-medium block">Total de Devolución:</span>
                        <span class="text-[11px] text-muted-foreground">
                            {order.customer_id ? 'Se acreditará a la cuenta corriente del cliente.' : 'Comprobante de devolución para cliente ocasional.'}
                        </span>
                    </div>
                    <span class="text-lg font-bold font-sans tabular-nums text-primary">
                        {formatPrice(totalRefundAmount)}
                    </span>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] px-4 rounded-xl text-xs font-semibold shrink-0 cursor-pointer"
                        onclick={closeModal}
                        disabled={isSubmitting}
                    >
                        Cancelar
                    </Button>

                    <Button
                        type="button"
                        variant="default"
                        class="min-h-[44px] px-5 rounded-xl text-xs font-semibold gap-2 shadow-xs bg-amber-600 hover:bg-amber-700 text-white shrink-0 cursor-pointer disabled:opacity-50"
                        onclick={handleSubmit}
                        disabled={isSubmitting || selectedItems.length === 0}
                    >
                        <RotateCcw class="size-4 shrink-0" />
                        <span>{isSubmitting ? 'Procesando...' : 'Confirmar Devolución'}</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
{/if}
