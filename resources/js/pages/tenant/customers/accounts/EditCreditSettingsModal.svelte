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
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
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
    let isConfirmModalOpen = $state(false);
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
        isConfirmModalOpen = false;
        errorMessage = null;
        isSubmitting = false;
    }

    function handleOpenConfirm(e: Event) {
        e.preventDefault();
        if (!customer) return;
        isConfirmModalOpen = true;
    }

    function executeSave() {
        if (!customer) return;

        isSubmitting = true;
        errorMessage = null;

        router.patch(
            `/tenant/clientes/${customer.id}/cuenta-corriente/limite-credito`,
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
                    isConfirmModalOpen = false;
                    errorMessage = (Object.values(errors)[0] as string) || 'Ocurrió un error al actualizar la configuración.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent size="default" class="max-w-md">
        {#if customer}
            <DialogHeader class="pb-3 border-b border-border/60 shrink-0">
                <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 w-full">
                    <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                        <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                            <ShieldCheck class="size-5 sm:size-6 text-primary" />
                        </div>
                        <div class="min-w-0 flex-1 flex flex-col justify-center">
                            <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-none truncate">
                                Límites y plazos de crédito
                            </DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground truncate mt-1">
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
                </div>
            </DialogHeader>


            <DialogBody>
                {#if errorMessage}
                    <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs font-medium">
                        {errorMessage}
                    </div>
                {/if}

                <form id="edit-credit-settings-form" onsubmit={handleOpenConfirm} class="space-y-4">
                    <!-- Límite de Crédito -->
                    <div class="space-y-1.5">
                        <Label for="credit-limit-input" class="text-sm font-medium text-foreground">
                            Límite de crédito máximo
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
                        <p class="text-xs text-muted-foreground">
                            Tope máximo de deuda permitida antes de emitir alertas en pedidos.
                        </p>
                    </div>

                    <!-- Días de Vencimiento -->
                    <div class="space-y-1.5">
                        <Label for="credit-due-days" class="text-sm font-medium text-foreground">
                            Plazo de vencimiento predeterminado
                        </Label>
                        <Input
                            id="credit-due-days"
                            type="number"
                            min="1"
                            max="365"
                            bind:value={creditDueDays}
                            class="h-9 text-sm tabular-nums font-medium"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            Cantidad de días desde la compra a crédito para la fecha límite de cobro.
                        </p>
                    </div>

                    <!-- Toggle Morosidad -->
                    <div class="pt-1">
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
                                <p class="text-muted-foreground text-xs">
                                    Muestra alertas destacadas y advertencias de riesgo en la creación de pedidos.
                                </p>
                            </div>
                        </label>
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
                    form="edit-credit-settings-form"
                    disabled={isSubmitting}
                    class="min-h-[44px] px-4 rounded-xl text-sm font-medium gap-1.5 min-w-32 shadow-xs"
                >
                    <Check class="size-3.5" />
                    <span>Guardar cambios</span>
                </Button>
            </DialogFooter>

        {/if}
    </DialogContent>
</Dialog>

<!-- Confirmación antes de actualizar límites de crédito -->
<ConfirmActionModal
    bind:open={isConfirmModalOpen}
    title="¿Actualizar límites y plazos de crédito?"
    description={`¿Deseas guardar los nuevos parámetros de cuenta corriente para "${customer?.name}" (Límite: $${Number(creditLimit) || 0}, Plazo: ${creditDueDays} días)?`}
    confirmText="Confirmar y guardar"
    cancelText="Volver"
    variant="warning"
    loading={isSubmitting}
    onconfirm={executeSave}
/>
