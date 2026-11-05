<script lang="ts">
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogBody } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { router } from '@inertiajs/svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import type { DeliveryOrder } from './DeliverOrderModal.svelte';

    interface Props {
        open?: boolean;
        order?: DeliveryOrder | null;
        onSuccess?: () => void;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onSuccess,
        onclose,
    }: Props = $props();

    let failureReason = $state<string>('local_cerrado');
    let deliveryNotes = $state<string>('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    const reasons = [
        { value: 'local_cerrado', label: 'Local o domicilio cerrado / Nadie atiende' },
        { value: 'rechazado', label: 'Rechazado por el cliente / No reconoce el pedido' },
        { value: 'daniado', label: 'Mercadería dañada o faltante en bultos' },
        { value: 'sin_dinero', label: 'Cliente sin fondos / No puede abonar contra entrega' },
        { value: 'otro', label: 'Otro motivo operacional' },
    ];

    $effect(() => {
        if (open) {
            failureReason = 'local_cerrado';
            deliveryNotes = '';
            errorMessage = null;
        }
    });

    function handleClose() {
        open = false;
        if (onclose) onclose();
    }

    function handleSubmit() {
        if (!order) return;
        errorMessage = null;

        if (!failureReason) {
            errorMessage = 'Selecciona un motivo para registrar el fallo.';
            return;
        }

        isSubmitting = true;

        router.post(
            `/tenant/repartos/${order.id}/fallar`,
            {
                failure_reason: failureReason,
                delivery_notes: deliveryNotes.trim() || null,
            },
            {
                onSuccess: () => {
                    isSubmitting = false;
                    handleClose();
                    if (onSuccess) onSuccess();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Error al registrar el fallo.';
                },
            }
        );
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-lg p-0 flex flex-col max-h-[85vh] sm:max-h-[88vh] bg-card text-card-foreground border border-border/80 overflow-hidden">
        <DialogHeader class="p-0 border-b border-border/60 shrink-0 bg-card">
            <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 p-4 sm:p-1.5 pb-3.5 sm:pb-1.5 pr-4 sm:pr-4 w-full">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div class="size-10 sm:size-12 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 flex items-center justify-center shrink-0">
                        <AlertTriangle class="size-5 sm:size-6 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate text-rose-600 dark:text-rose-400">
                            Registrar visita fallida
                        </DialogTitle>
                        <DialogDescription class="sr-only">Indica la causa por la que no se pudo completar la entrega en el domicilio</DialogDescription>
                    </div>
                </div>
                {#if order}
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold font-sans tabular-nums bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/20 shrink-0">
                        #{order.order_number}
                    </span>
                {/if}
            </div>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
            {#if order}
                <div class="rounded-xl border border-border/70 bg-background/50 p-4 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-foreground text-sm">{order.customer_name}</span>
                        <span class="font-sans font-bold text-muted-foreground tabular-nums">#{order.order_number}</span>
                    </div>
                    {#if order.delivery_address}
                        <p class="text-muted-foreground">Dirección: {order.delivery_address}</p>
                    {/if}
                </div>

                <!-- Motivo de Fallo -->
                <div class="space-y-1.5">
                    <Label for="failure-reason" class="text-sm font-medium text-foreground">Motivo de no entrega</Label>
                    <select
                        id="failure-reason"
                        bind:value={failureReason}
                        class="w-full h-11 px-3 text-sm rounded-xl border border-border/80 bg-select-background text-foreground focus:outline-none focus:border-rose-500 transition-all min-h-[44px]"
                    >
                        {#each reasons as r}
                            <option value={r.value}>{r.label}</option>
                        {/each}
                    </select>
                </div>

                <!-- Observaciones adicionales -->
                <div class="space-y-1.5">
                    <Label for="failure-notes" class="text-sm font-medium text-foreground">Detalles u observaciones</Label>
                    <Textarea
                        id="failure-notes"
                        rows={3}
                        maxlength="255"
                        placeholder="Describe lo ocurrido (ej: Timbre no funciona, se intentó llamar por celular sin respuesta...)"
                        bind:value={deliveryNotes}
                        class="resize-none bg-background border-border/80 text-sm"
                    />
                </div>

                <div class="p-3 rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs">
                    El pedido será desasignado de tu ruta y pasará al panel de depósito para que el administrador pueda reprogramarlo o cancelarlo.
                </div>

                {#if errorMessage}
                    <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/30 text-destructive text-xs">
                        {errorMessage}
                    </div>
                {/if}
            {/if}
        </DialogBody>

        <DialogFooter class="p-5 sm:p-6 pt-3 border-t border-border/50 flex flex-col-reverse sm:flex-row items-center justify-end gap-3 shrink-0">
            <Button
                variant="secondary"
                class="w-full sm:w-auto h-11 min-h-[44px] px-4 text-sm font-semibold"
                onclick={handleClose}
                disabled={isSubmitting}
            >
                Cancelar
            </Button>
            <Button
                variant="destructive"
                class="w-full sm:w-auto h-11 min-h-[44px] px-6 text-sm font-semibold gap-2"
                onclick={handleSubmit}
                disabled={isSubmitting}
            >
                {#if isSubmitting}
                    <Loader2 class="size-4 animate-spin" />
                    <span>Guardando...</span>
                {:else}
                    <AlertTriangle class="size-4" />
                    <span>Confirmar No Entregado</span>
                {/if}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
