<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import IconContainer from '@/components/common/IconContainer.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import { router } from '@inertiajs/svelte';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';
    import Banknote from 'lucide-svelte/icons/banknote';
    import Check from 'lucide-svelte/icons/check';
    import CreditCard from 'lucide-svelte/icons/credit-card';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ArrowDownUp from 'lucide-svelte/icons/arrow-down-up';
    import Building from 'lucide-svelte/icons/building';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';

    export interface OrderToPay {
        id: number;
        order_number: string;
        total_amount: string | number;
        debt_amount: string | number;
        created_at?: string;
    }

    export interface CustomerData {
        id: number;
        name: string;
        tax_id?: string | null;
        current_balance?: string | number;
    }

    interface AllocationItem {
        order_id: number;
        order_number: string;
        total_amount: number;
        debt_amount: number;
        amount: number;
    }

    let {
        open = $bindable(false),
        customer = null,
        orders = [],
        suggestedAmount = 0,
        onSuccess = undefined,
    }: {
        open?: boolean;
        customer?: CustomerData | null;
        orders?: OrderToPay[];
        suggestedAmount?: number;
        onSuccess?: (receipt?: any) => void;
    } = $props();

    let amount = $state<string>('');
    let paymentMethod = $state<string>('efectivo');
    let referenceNumber = $state<string>('');
    let notes = $state<string>('');
    let isSubmitting = $state<boolean>(false);
    let errorMessage = $state<string | null>(null);
    let isConfirmAlertOpen = $state<boolean>(false);

    // Idempotency guard: tracks the set of order IDs currently being processed
    // to prevent duplicate submissions when the dialog stays open unexpectedly.
    let processingKey = $state<string | null>(null);

    let allocations = $state<AllocationItem[]>([]);

    const paymentMethods = [
        { value: 'efectivo', label: 'Efectivo' },
        { value: 'transferencia', label: 'Transferencia Bancaria' },
        { value: 'mercadopago', label: 'Mercado Pago' },
        { value: 'cheque', label: 'Cheque' },
        { value: 'debito', label: 'Tarjeta de Débito' },
        { value: 'credito', label: 'Tarjeta de Crédito' },
        { value: 'otro', label: 'Otro' },
    ];

    function formatPrice(val: number | string) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    // Calcula el total adeudado de los pedidos seleccionados
    let totalOrdersDebt = $derived(
        orders.reduce((sum, o) => sum + (Number(o.debt_amount) || 0), 0)
    );

    let parsedAmount = $derived(Number(amount) || 0);

    let allocatedTotal = $derived(
        allocations.reduce((sum, a) => sum + (Number(a.amount) || 0), 0)
    );

    let hasDiscrepancy = $derived(
        orders.length > 0 && Math.abs(allocatedTotal - parsedAmount) > 0.01
    );

    let isValid = $derived(
        parsedAmount > 0 &&
        Boolean(paymentMethod) &&
        (!orders.length || !hasDiscrepancy)
    );

    // Algoritmo FIFO para distribuir el importe ingresado
    function applyFifoDistribution(total: number) {
        let remaining = total;
        allocations = orders.map((o) => {
            const debt = Number(o.debt_amount) || 0;
            const applied = Math.min(remaining, debt);
            remaining = Math.max(0, remaining - applied);
            return {
                order_id: o.id,
                order_number: o.order_number,
                total_amount: Number(o.total_amount) || 0,
                debt_amount: debt,
                amount: Math.round(applied * 100) / 100,
            };
        });
    }

    // Inicialización al abrir el modal
    $effect(() => {
        if (open) {
            errorMessage = null;
            referenceNumber = '';
            notes = '';
            paymentMethod = 'efectivo';
            isSubmitting = false;
            processingKey = null;

            const initialAmount = suggestedAmount > 0 ? suggestedAmount : totalOrdersDebt;
            amount = initialAmount > 0 ? String(initialAmount) : '';
            applyFifoDistribution(initialAmount);
        }
    });

    function handleAmountChange(val: string) {
        amount = val;
        const num = Number(val) || 0;
        if (orders.length > 0) {
            applyFifoDistribution(num);
        }
    }

    function setFullTotalDebt() {
        if (totalOrdersDebt > 0) {
            handleAmountChange(String(totalOrdersDebt));
        } else if (customer && Number(customer.current_balance) > 0) {
            handleAmountChange(String(customer.current_balance));
        }
    }

    function handleManualAllocationChange(index: number, val: number) {
        if (allocations[index]) {
            allocations[index].amount = val;
        }
    }

    function handleFormSubmit(e: SubmitEvent) {
        e.preventDefault();
        if (!isValid) return;
        isConfirmAlertOpen = true;
    }

    function handleConfirmedPayment() {
        // Idempotency guard: build a stable key from current order IDs + amount.
        // If the same key is already processing, silently reject the second press.
        const currentKey = [
            ...allocations.map((a) => `${a.order_id}:${a.amount}`),
            parsedAmount,
            paymentMethod,
        ].join('|');

        if (processingKey !== null) return; // already in-flight
        processingKey = currentKey;

        isSubmitting = true;
        isConfirmAlertOpen = false; // close the confirm dialog immediately
        errorMessage = null;

        const payloadAllocations = allocations.map((a) => ({
            order_id: a.order_id,
            amount: Number(a.amount) || 0,
        }));

        router.post(
            '/tenant/cobranzas',
            {
                customer_id: customer?.id ?? null,
                amount: parsedAmount,
                payment_method: paymentMethod,
                reference_number: referenceNumber.trim() || null,
                notes: notes.trim() || null,
                allocations: payloadAllocations.length > 0 ? payloadAllocations : undefined,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    isSubmitting = false;
                    processingKey = null;
                    open = false;
                    onSuccess?.(page);
                },
                onError: (errors) => {
                    isSubmitting = false;
                    processingKey = null;
                    const first = Object.values(errors)[0];
                    errorMessage = typeof first === 'string' ? first : 'Error al registrar el cobro.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="w-[95vw] max-w-2xl p-5 sm:p-6 space-y-4 rounded-2xl sm:rounded-3xl bg-card border-border shadow-2xl max-h-[90vh] flex flex-col">
        <DialogHeader class="border-b pb-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <IconContainer variant="primary" size="md">
                    <ReceiptText class="size-5" />
                </IconContainer>
                <div>
                    <DialogTitle class="text-base sm:text-lg font-bold text-foreground">
                        Registrar Cobro / Recibo
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        {#if customer}
                            Comprobante oficial para <strong class="text-foreground">{customer.name}</strong>
                        {:else}
                            Emisión de recibo correlativo respaldatorio de pago.
                        {/if}
                    </DialogDescription>
                </div>
            </div>
        </DialogHeader>

        {#if errorMessage}
            <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center gap-2 shrink-0">
                <AlertCircle class="size-4 shrink-0" />
                <span class="font-medium">{errorMessage}</span>
            </div>
        {/if}

        <form onsubmit={handleFormSubmit} class="space-y-4 overflow-y-auto flex-1 min-h-0 pr-1">
            <!-- Bento Box: Resumen de Cliente y Deuda -->
            {#if customer}
                <div class="rounded-xl bg-muted/20 border border-border/80 p-3 sm:p-3.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <User class="size-4" />
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-foreground block">{customer.name}</span>
                            <span class="text-xs text-muted-foreground">
                                {customer.tax_id ? `CUIT: ${customer.tax_id}` : 'Cliente Registrado'}
                            </span>
                        </div>
                    </div>

                    <div class="text-left sm:text-right">
                        <span class="text-xs uppercase font-semibold text-muted-foreground block">Saldo en Cuenta</span>
                        <span class="text-sm sm:text-base font-bold font-sans tabular-nums {Number(customer.current_balance) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600'}">
                            {formatPrice(customer.current_balance || 0)}
                        </span>
                    </div>
                </div>
            {/if}

            <!-- Fila 1: Importe y Método de Pago -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-6 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="receipt-amount" class="text-sm font-medium text-foreground">
                            Monto a Cobrar <span class="text-destructive">*</span>
                        </Label>
                        {#if totalOrdersDebt > 0 || (customer && Number(customer.current_balance) > 0)}
                            <button
                                type="button"
                                onclick={setFullTotalDebt}
                                class="text-xs font-semibold text-primary hover:underline cursor-pointer"
                            >
                                Total ({formatPrice(totalOrdersDebt || customer?.current_balance || 0)})
                            </button>
                        {/if}
                    </div>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">$</span>
                        <Input
                            id="receipt-amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={amount}
                            oninput={(e) => handleAmountChange((e.target as HTMLInputElement).value)}
                            placeholder="0.00"
                            class="h-11 rounded-xl bg-background border-border/80 pl-8 text-base font-bold font-sans tabular-nums text-foreground"
                            required
                        />
                    </div>
                </div>

                <div class="sm:col-span-6 space-y-1.5">
                    <Label for="receipt-method" class="text-sm font-medium text-foreground">
                        Método de Pago <span class="text-destructive">*</span>
                    </Label>
                    <select
                        id="receipt-method"
                        bind:value={paymentMethod}
                        class="h-11 w-full rounded-xl border border-border/80 bg-background px-3 text-sm font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary"
                        required
                    >
                        {#each paymentMethods as m}
                            <option value={m.value}>{m.label}</option>
                        {/each}
                    </select>
                </div>
            </div>

            <!-- Fila 2: Referencia y Notas -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-6 space-y-1.5">
                    <Label for="receipt-ref" class="text-sm font-medium text-foreground">
                        Nº de Referencia / Transacción / Cheque
                    </Label>
                    <Input
                        id="receipt-ref"
                        type="text"
                        bind:value={referenceNumber}
                        placeholder="Ej. TRF-889911, CHQ-4455"
                        class="h-10 rounded-xl bg-background border-border/80 text-sm font-medium text-foreground"
                    />
                </div>

                <div class="sm:col-span-6 space-y-1.5">
                    <Label for="receipt-notes" class="text-sm font-medium text-foreground">Observaciones</Label>
                    <Input
                        id="receipt-notes"
                        type="text"
                        bind:value={notes}
                        placeholder="Comentarios adicionales del cobro..."
                        class="h-10 rounded-xl bg-background border-border/80 text-sm text-foreground"
                    />
                </div>
            </div>

            <!-- Tabla de Imputación de Pedidos (FIFO con edición manual) -->
            {#if orders.length > 0}
                <div class="space-y-2 border-t border-border/60 pt-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <ArrowDownUp class="size-3.5 text-primary" />
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                Imputación a Pedidos ({orders.length})
                            </span>
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2.5 text-xs text-primary gap-1"
                            onclick={() => applyFifoDistribution(parsedAmount)}
                        >
                            <ArrowDownUp class="size-3" />
                            <span>Redistribuir FIFO</span>
                        </Button>
                    </div>

                    <div class="rounded-xl border border-border/70 overflow-hidden divide-y divide-border/50 bg-background/50">
                        {#each allocations as item, idx}
                            <div class="p-2.5 sm:p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-foreground">{item.order_number}</span>
                                        <span class="text-xs bg-muted px-1.5 py-0.5 rounded text-muted-foreground tabular-nums">
                                            Total: {formatPrice(item.total_amount)}
                                        </span>
                                    </div>
                                    <span class="text-xs text-muted-foreground">
                                        Deuda pendiente: <strong class="text-rose-600 dark:text-rose-400 font-sans tabular-nums">{formatPrice(item.debt_amount)}</strong>
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
                                    <span class="text-xs font-semibold text-muted-foreground">Monto imputado:</span>
                                    <div class="relative w-32">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">$</span>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max={item.debt_amount}
                                            value={item.amount}
                                            oninput={(e) => handleManualAllocationChange(idx, Number((e.target as HTMLInputElement).value) || 0)}
                                            class="h-8 rounded-lg bg-background border-border/80 pl-6 text-sm font-bold font-sans tabular-nums text-right"
                                        />
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Discrepancia / Balance de Imputaciones -->
                    <div class="flex items-center justify-between p-2.5 rounded-xl {hasDiscrepancy ? 'bg-destructive/10 border border-destructive/20 text-destructive' : 'bg-muted/40 text-foreground'} text-xs">
                        <span class="font-semibold">
                            Total Imputado: <strong class="font-sans tabular-nums">{formatPrice(allocatedTotal)}</strong>
                        </span>
                        {#if hasDiscrepancy}
                            <span class="font-semibold text-xs flex items-center gap-1 tabular-nums">
                                <AlertCircle class="size-3.5" />
                                Discrepancia: {formatPrice(Math.abs(allocatedTotal - parsedAmount))}
                            </span>
                        {:else}
                            <span class="text-emerald-600 dark:text-emerald-400 font-semibold text-xs flex items-center gap-1">
                                <Check class="size-3.5" />
                                Montos balanceados al 100%
                            </span>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- Footer con Botones Simétricos min-h-[44px] -->
            <div class="border-t border-border/60 pt-4 flex flex-col-reverse sm:flex-row items-center justify-end gap-3 shrink-0">
                <Button
                    type="button"
                    variant="secondary"
                    class="h-11 px-5 rounded-xl font-semibold w-full sm:w-auto min-h-[44px]"
                    onclick={() => (open = false)}
                    disabled={isSubmitting}
                >
                    Cancelar
                </Button>

                <Button
                    type="submit"
                    variant="default"
                    class="h-11 px-6 rounded-xl font-bold w-full sm:w-auto min-h-[44px] shadow-sm gap-2"
                    disabled={!isValid || isSubmitting}
                >
                    {#if isSubmitting}
                        <span>Procesando cobro...</span>
                    {:else}
                        <Check class="size-4" />
                        <span>Confirmar Cobro ({formatPrice(parsedAmount)})</span>
                    {/if}
                </Button>
            </div>
        </form>
    </DialogContent>
</Dialog>

<!-- Confirmación Atómica -->
<ConfirmActionModal
    bind:open={isConfirmAlertOpen}
    title="¿Confirmar registro del cobro?"
    description="Se emitirá un recibo oficial por {formatPrice(parsedAmount)} mediante {paymentMethods.find(m => m.value === paymentMethod)?.label}. Esta operación actualizará el libro mayor de cuenta corriente y el saldo deudor de los pedidos imputados."
    confirmText="Registrar Cobro"
    confirmVariant="default"
    loading={isSubmitting}
    onconfirm={handleConfirmedPayment}
/>
