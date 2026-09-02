<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import Banknote from 'lucide-svelte/icons/banknote';
    import Check from 'lucide-svelte/icons/check';
    import X from 'lucide-svelte/icons/x';

    export interface CustomerAccountData {
        id: number;
        name: string;
        tax_id: string | null;
        current_balance: string | number;
        credit_limit: string | number;
    }

    let {
        open = $bindable(false),
        customer = null,
    }: {
        open?: boolean;
        customer: CustomerAccountData | null;
    } = $props();

    let amount = $state('');
    let paymentMethod = $state('efectivo');
    let referenceNumber = $state('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let currentBalanceNum = $derived(Number(customer?.current_balance) || 0);
    let amountNum = $derived(Number(amount) || 0);
    let resultingBalance = $derived(Math.max(0, currentBalanceNum - amountNum));

    function formatPrice(val: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    function setFullAmount() {
        if (currentBalanceNum > 0) {
            amount = currentBalanceNum.toString();
        }
    }

    function handleClose() {
        open = false;
        amount = '';
        paymentMethod = 'efectivo';
        referenceNumber = '';
        notes = '';
        errorMessage = null;
        isSubmitting = false;
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        if (!customer) return;
        if (amountNum <= 0) {
            errorMessage = 'Por favor ingresa un monto válido mayor a cero.';
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        router.post(
            `/tenant/clientes/${customer.id}/cuenta-corriente/cobro`,
            {

                amount: amountNum,
                payment_method: paymentMethod,
                reference_number: referenceNumber || null,
                notes: notes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = (Object.values(errors)[0] as string) || 'Ocurrió un error al registrar el pago.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent size="default" class="max-w-lg">
        {#if customer}
            <DialogHeader>
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        <Banknote class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base sm:text-lg font-semibold text-foreground leading-snug truncate">
                            Registrar cobro / abono
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground truncate">
                            Cliente: <span class="font-semibold text-foreground">{customer.name}</span>
                        </DialogDescription>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={handleClose}
                    class="size-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer shrink-0"
                    aria-label="Cerrar"
                >
                    <X class="size-4" />
                </button>
            </DialogHeader>

            <DialogBody>
                {#if errorMessage}
                    <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs font-medium">
                        {errorMessage}
                    </div>
                {/if}

                <!-- Calculador Dinámico de Saldo -->
                <div class="p-4 rounded-2xl bg-muted/40 border border-border/70 grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-muted-foreground block mb-0.5">Saldo deudor actual:</span>
                        <span class="text-sm font-bold text-foreground tabular-nums">
                            {formatPrice(currentBalanceNum)}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-muted-foreground block mb-0.5">Saldo restante:</span>
                        <span class="text-sm font-bold tabular-nums {resultingBalance === 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-primary'}">
                            {formatPrice(resultingBalance)}
                        </span>
                    </div>
                </div>

                <form id="record-payment-form" onsubmit={handleSubmit} class="space-y-4 pt-1">
                    <!-- Monto -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="payment-amount" class="text-xs font-semibold text-foreground">
                                Monto a cobrar ($) <span class="text-destructive">*</span>
                            </Label>
                            {#if currentBalanceNum > 0}
                                <button
                                    type="button"
                                    onclick={setFullAmount}
                                    class="text-[11px] text-primary hover:underline font-medium tabular-nums"
                                >
                                    Cobro total ({formatPrice(currentBalanceNum)})
                                </button>
                            {/if}
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">$</span>
                            <Input
                                id="payment-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                bind:value={amount}
                                class="pl-7 text-base tabular-nums font-bold"
                                placeholder="0.00"
                                required
                            />
                        </div>
                    </div>

                    <!-- Método de pago -->
                    <div class="space-y-1.5">
                        <Label for="payment-method" class="text-xs font-semibold text-foreground">
                            Método de Pago <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="payment-method"
                            bind:value={paymentMethod}
                            class="h-10 w-full rounded-xl border border-border/80 bg-muted/30 dark:bg-black/20 dark:border-white/10 px-3 text-xs font-medium text-foreground focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary shadow-2xs"
                        >
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia Bancaria</option>
                            <option value="cheque">Cheque</option>
                            <option value="tarjeta">Tarjeta de Débito / Crédito</option>
                            <option value="otro">Otro medio de pago</option>
                        </select>
                    </div>


                    <!-- Nro Comprobante / Referencia -->
                    <div class="space-y-1.5">
                        <Label for="payment-ref" class="text-xs font-semibold text-foreground">
                            Nro. de Comprobante / Transacción (Opcional)
                        </Label>
                        <Input
                            id="payment-ref"
                            bind:value={referenceNumber}
                            placeholder="ej. TRF-994821 o Recibo 0001"
                            class="h-9 text-xs tabular-nums"
                        />
                    </div>

                    <!-- Notas -->
                    <div class="space-y-1.5">
                        <Label for="payment-notes" class="text-xs font-semibold text-foreground">
                            Observaciones (Opcional)
                        </Label>
                        <Input
                            id="payment-notes"
                            bind:value={notes}
                            placeholder="Detalle o nota interna..."
                            class="h-9 text-xs"
                        />
                    </div>
                </form>
            </DialogBody>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onclick={handleClose}
                    disabled={isSubmitting}
                    class="min-h-[44px] px-4 rounded-xl text-sm font-semibold border-border/70"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    form="record-payment-form"
                    disabled={isSubmitting || amountNum <= 0}
                    class="min-h-[44px] px-4 rounded-xl text-sm font-semibold gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white min-w-32 shadow-xs"
                >
                    <Check class="size-3.5" />
                    <span>Confirmar cobro</span>
                </Button>
            </DialogFooter>

        {/if}
    </DialogContent>
</Dialog>
