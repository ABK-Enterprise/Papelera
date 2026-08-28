<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { router } from '@inertiajs/svelte';
    import Check from 'lucide-svelte/icons/check';
    import Sliders from 'lucide-svelte/icons/sliders';
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

    let type = $state<'debit' | 'credit'>('credit');
    let amount = $state('');
    let description = $state('');
    let notes = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    let currentBalanceNum = $derived(Number(customer?.current_balance) || 0);
    let amountNum = $derived(Number(amount) || 0);
    let resultingBalance = $derived.by(() => {
        if (type === 'debit') {
            return currentBalanceNum + amountNum;
        }
        return Math.max(0, currentBalanceNum - amountNum);
    });

    function formatPrice(val: number) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(val);
    }

    function handleClose() {
        open = false;
        type = 'credit';
        amount = '';
        description = '';
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
        if (!description.trim()) {
            errorMessage = 'El motivo del ajuste es obligatorio.';
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        router.post(
            `/tenant/customers/${customer.id}/account/adjustment`,
            {
                type,
                amount: amountNum,
                description: description.trim(),
                notes: notes || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors)[0] as string || 'Ocurrió un error al registrar el ajuste.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-[500px] p-6 sm:p-7">
        {#if customer}
            <form onsubmit={handleSubmit}>
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-border">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                            <Sliders class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-bold tracking-tight text-foreground">
                                Ajuste Manual de Saldo
                            </DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                                Cliente: <span class="font-semibold text-foreground">{customer.name}</span>
                            </DialogDescription>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick={handleClose}
                        class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                        aria-label="Cerrar"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                {#if errorMessage}
                    <div class="mt-4 p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs font-medium">
                        {errorMessage}
                    </div>
                {/if}

                <!-- Calculador Dinámico de Saldo -->
                <div class="mt-4 p-4 rounded-2xl bg-muted/40 border border-border/70 grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-muted-foreground block">Saldo actual:</span>
                        <span class="text-sm font-bold text-foreground tabular-nums">
                            {formatPrice(currentBalanceNum)}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-muted-foreground block">Saldo resultante:</span>
                        <span class="text-sm font-bold tabular-nums {type === 'debit' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                            {formatPrice(resultingBalance)}
                        </span>
                    </div>
                </div>

                <div class="grid gap-4 py-4">
                    <!-- Selector Tipo de Ajuste -->
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold text-foreground">
                            Efecto sobre la deuda
                        </Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onclick={() => (type = 'credit')}
                                class="p-2.5 rounded-xl border text-xs font-semibold transition-all flex flex-col items-center gap-1 {type === 'credit' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500' : 'border-border bg-background text-muted-foreground hover:text-foreground'}"
                            >
                                <span>- Descuento / Crédito</span>
                                <span class="text-[10px] font-normal opacity-80">Reduce el saldo deudor</span>
                            </button>
                            <button
                                type="button"
                                onclick={() => (type = 'debit')}
                                class="p-2.5 rounded-xl border text-xs font-semibold transition-all flex flex-col items-center gap-1 {type === 'debit' ? 'border-rose-500 bg-rose-500/10 text-rose-700 dark:text-rose-300 ring-1 ring-rose-500' : 'border-border bg-background text-muted-foreground hover:text-foreground'}"
                            >
                                <span>+ Cargo / Débito</span>
                                <span class="text-[10px] font-normal opacity-80">Incrementa la deuda</span>
                            </button>
                        </div>
                    </div>

                    <!-- Monto -->
                    <div class="space-y-1.5">
                        <Label for="adjust-amount" class="text-xs font-semibold text-foreground">
                            Monto a ajustar ($) <span class="text-destructive">*</span>
                        </Label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">$</span>
                            <Input
                                id="adjust-amount"
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

                    <!-- Motivo -->
                    <div class="space-y-1.5">
                        <Label for="adjust-desc" class="text-xs font-semibold text-foreground">
                            Motivo del ajuste <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="adjust-desc"
                            bind:value={description}
                            placeholder="ej. Quita por pronto pago / Recargo por financiamiento"
                            class="h-9 text-xs"
                            required
                        />
                    </div>

                    <!-- Observaciones -->
                    <div class="space-y-1.5">
                        <Label for="adjust-notes" class="text-xs font-semibold text-foreground">
                            Observaciones (Opcional)
                        </Label>
                        <Input
                            id="adjust-notes"
                            bind:value={notes}
                            placeholder="Aprobado por administración..."
                            class="h-9 text-xs"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <Button type="button" variant="outline" onclick={handleClose} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={isSubmitting || amountNum <= 0 || !description.trim()} class="gap-1.5 font-medium">
                        <Check class="size-4" />
                        <span>Aplicar ajuste</span>
                    </Button>
                </div>
            </form>
        {/if}
    </DialogContent>
</Dialog>
