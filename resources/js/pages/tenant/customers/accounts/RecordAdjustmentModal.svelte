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
    import Check from 'lucide-svelte/icons/check';
    import Sliders from 'lucide-svelte/icons/sliders';
    import X from 'lucide-svelte/icons/x';
    import { preventInvalidNumericKeys, sanitizeNumericInput } from '@/lib/numericSanitizer';

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
        if (amountNum > 100_000_000) {
            errorMessage = 'El monto del ajuste no puede superar los $100.000.000.';
            return;
        }
        if (!description.trim()) {
            errorMessage = 'El motivo del ajuste es obligatorio.';
            return;
        }

        isSubmitting = true;
        errorMessage = null;

        router.post(
            `/tenant/clientes/${customer.id}/cuenta-corriente/ajuste`,
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
                    errorMessage = (Object.values(errors)[0] as string) || 'Ocurrió un error al registrar el ajuste.';
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
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary border border-primary/20">
                        <Sliders class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <DialogTitle class="text-base sm:text-lg font-semibold text-foreground leading-snug truncate">
                            Ajuste manual de saldo
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
                        <span class="text-muted-foreground block mb-0.5">Saldo actual:</span>
                        <span class="text-sm font-bold text-foreground tabular-nums">
                            {formatPrice(currentBalanceNum)}
                        </span>
                    </div>
                    <div class="text-right">
                        <span class="text-muted-foreground block mb-0.5">Saldo resultante:</span>
                        <span class="text-sm font-bold tabular-nums {type === 'debit' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'}">
                            {formatPrice(resultingBalance)}
                        </span>
                    </div>
                </div>

                <form id="record-adjustment-form" onsubmit={handleSubmit} class="space-y-4 pt-1">
                    <!-- Selector Tipo de Ajuste -->
                    <div class="space-y-1.5">
                        <Label class="text-sm font-medium text-foreground">
                            Efecto sobre la deuda
                        </Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onclick={() => (type = 'credit')}
                                class="p-2.5 rounded-xl border text-xs font-semibold transition-all flex flex-col items-center gap-1 {type === 'credit' ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-500' : 'border-border bg-background text-muted-foreground hover:text-foreground'}"
                            >
                                <span>- Descuento / Crédito</span>
                                <span class="text-xs font-normal opacity-80">Reduce el saldo deudor</span>
                            </button>
                            <button
                                type="button"
                                onclick={() => (type = 'debit')}
                                class="p-2.5 rounded-xl border text-xs font-semibold transition-all flex flex-col items-center gap-1 {type === 'debit' ? 'border-rose-500 bg-rose-500/10 text-rose-700 dark:text-rose-300 ring-1 ring-rose-500' : 'border-border bg-background text-muted-foreground hover:text-foreground'}"
                            >
                                <span>+ Cargo / Débito</span>
                                <span class="text-xs font-normal opacity-80">Incrementa la deuda</span>
                            </button>
                        </div>
                    </div>

                    <!-- Monto -->
                    <div class="space-y-1.5">
                        <Label for="adjust-amount" class="text-sm font-medium text-foreground">
                            Monto a ajustar <span class="text-destructive">*</span>
                        </Label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">$</span>
                            <Input
                                id="adjust-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max="100000000"
                                onkeydown={(e) => preventInvalidNumericKeys(e)}
                                oninput={(e) => {
                                    sanitizeNumericInput(e, 0.01, 100_000_000, (val) => {
                                        amount = typeof val === 'number' ? String(val) : val;
                                    });
                                }}
                                bind:value={amount}
                                class="pl-7 text-base tabular-nums font-bold"
                                placeholder="0.00"
                                required
                            />
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="space-y-1.5">
                        <Label for="adjust-desc" class="text-sm font-medium text-foreground">
                            Motivo del ajuste <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="adjust-desc"
                            bind:value={description}
                            placeholder="ej. Quita por pronto pago / Recargo por financiamiento"
                            class="h-9 text-sm"
                            required
                        />
                    </div>

                    <!-- Observaciones -->
                    <div class="space-y-1.5">
                        <Label for="adjust-notes" class="text-sm font-medium text-foreground">
                            Observaciones (Opcional)
                        </Label>
                        <Input
                            id="adjust-notes"
                            bind:value={notes}
                            placeholder="Aprobado por administración..."
                            class="h-9 text-sm"
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
                    class="min-h-[44px] px-4 rounded-xl text-sm font-medium border-border/70"
                >
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    form="record-adjustment-form"
                    disabled={isSubmitting || amountNum <= 0 || !description.trim()}
                    class="min-h-[44px] px-4 rounded-xl text-sm font-medium gap-1.5 min-w-32 shadow-xs"
                >
                    <Check class="size-3.5" />
                    <span>Aplicar ajuste</span>
                </Button>
            </DialogFooter>

        {/if}
    </DialogContent>
</Dialog>
