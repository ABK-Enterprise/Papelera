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
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Check from 'lucide-svelte/icons/check';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import X from 'lucide-svelte/icons/x';

    export interface CustomerAccountData {
        id: number;
        name: string;
        tax_id: string | null;
        credit_limit: string | number;
        credit_due_days?: number;
        is_defaulter: boolean;
    }

    let {
        open = $bindable(false),
        customer = null,
    }: {
        open?: boolean;
        customer: CustomerAccountData | null;
    } = $props();

    let creditLimit = $state('');
    let creditDueDays = $state(30);
    let isDefaulter = $state(false);
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    $effect(() => {
        if (customer) {
            creditLimit = customer.credit_limit ? customer.credit_limit.toString() : '0';
            creditDueDays = customer.credit_due_days ?? 30;
            isDefaulter = !!customer.is_defaulter;
        }
    });

    function handleClose() {
        open = false;
        errorMessage = null;
        isSubmitting = false;
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        if (!customer) return;

        isSubmitting = true;
        errorMessage = null;

        router.patch(
            `/tenant/customers/${customer.id}/account/credit-settings`,
            {
                credit_limit: Number(creditLimit) || 0,
                credit_due_days: Number(creditDueDays) || 30,
                is_defaulter: isDefaulter,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    handleClose();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors)[0] as string || 'Ocurrió un error al actualizar la configuración.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="sm:max-w-[480px] p-6 sm:p-7">
        {#if customer}
            <form onsubmit={handleSubmit}>
                <div class="flex items-start justify-between gap-4 pb-4 border-b border-border">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary shrink-0">
                            <ShieldCheck class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg font-bold tracking-tight text-foreground">
                                Límites y Plazos de Crédito
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

                <div class="grid gap-4 py-5">
                    <!-- Límite de Crédito -->
                    <div class="space-y-1.5">
                        <Label for="credit-limit-input" class="text-xs font-semibold text-foreground">
                            Límite Máximo de Crédito ($)
                        </Label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-muted-foreground">$</span>
                            <Input
                                id="credit-limit-input"
                                type="number"
                                step="0.01"
                                min="0"
                                bind:value={creditLimit}
                                class="pl-7 text-base tabular-nums font-bold"
                                placeholder="0.00"
                                required
                            />
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            Tope máximo de deuda permitida antes de emitir alertas en pedidos.
                        </p>
                    </div>

                    <!-- Días de Vencimiento -->
                    <div class="space-y-1.5">
                        <Label for="credit-due-days" class="text-xs font-semibold text-foreground">
                            Plazo de Vencimiento Predeterminado (Días)
                        </Label>
                        <Input
                            id="credit-due-days"
                            type="number"
                            min="1"
                            max="365"
                            bind:value={creditDueDays}
                            class="h-9 text-xs tabular-nums font-semibold"
                            required
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Cantidad de días desde la compra a crédito para la fecha límite de cobro.
                        </p>
                    </div>

                    <!-- Toggle Morosidad -->
                    <div class="pt-2">
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-border/80 bg-muted/20 hover:bg-muted/40 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                bind:checked={isDefaulter}
                                class="size-4 mt-0.5 rounded border-input text-amber-600 focus:ring-amber-500"
                            />
                            <div class="space-y-0.5 text-xs">
                                <span class="font-semibold text-foreground flex items-center gap-1.5">
                                    <AlertTriangle class="size-3.5 text-amber-600" />
                                    Marcar como cliente moroso
                                </span>
                                <p class="text-muted-foreground text-[11px]">
                                    Muestra alertas destacadas y advertencias de riesgo en la creación de pedidos.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                    <Button type="button" variant="outline" onclick={handleClose} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={isSubmitting} class="gap-1.5 font-medium">
                        <Check class="size-4" />
                        <span>Guardar cambios</span>
                    </Button>
                </div>
            </form>
        {/if}
    </DialogContent>
</Dialog>
