<script lang="ts">
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogBody } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Textarea } from '@/components/ui/textarea';
    import { router } from '@inertiajs/svelte';
    import type { DeliveryOrder } from './DeliverOrderModal.svelte';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import Loader2 from 'lucide-svelte/icons/loader-2';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import AlertCircle from 'lucide-svelte/icons/alert-circle';

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

    let destination = $state<'en_preparacion' | 'cancelado'>('en_preparacion');
    let reason = $state('');
    let isSubmitting = $state(false);
    let errorMessage = $state<string | null>(null);

    $effect(() => {
        if (open && order) {
            destination = 'en_preparacion';
            reason = '';
            errorMessage = null;
        }
    });

    function handleClose() {
        open = false;
        if (onclose) onclose();
    }

    function handleSubmit() {
        if (!order) return;
        isSubmitting = true;
        errorMessage = null;

        router.post(
            `/tenant/repartos/${order.id}/reprogramar`,
            {
                destination,
                reason: reason.trim() || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isSubmitting = false;
                    handleClose();
                    if (onSuccess) onSuccess();
                },
                onError: (errors) => {
                    isSubmitting = false;
                    errorMessage = Object.values(errors).flat().join(' ') || 'Error al procesar la reprogramación.';
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
                    <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                        <RefreshCw class="size-5 sm:size-6 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-normal py-0.5 sm:py-1 truncate">
                            Reprogramar o cancelar pedido
                        </DialogTitle>
                        <DialogDescription class="sr-only">Define el destino operativo para gestionar este pedido</DialogDescription>
                    </div>
                </div>
                {#if order}
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold font-sans tabular-nums bg-primary/15 text-primary border border-primary/20 shrink-0">
                        #{order.order_number}
                    </span>
                {/if}
            </div>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
            {#if order}
                <!-- Resumen de Pedido -->
                <div class="rounded-2xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-foreground">{order.customer_name}</span>
                        <span class="text-xs font-sans font-bold text-primary tabular-nums">#{order.order_number}</span>
                    </div>
                    {#if order.delivery_failure_reason}
                        <p class="text-xs text-rose-600 dark:text-rose-400 font-medium">
                            Motivo de no entrega: {order.delivery_failure_reason}
                        </p>
                    {/if}
                    {#if order.delivery_notes}
                        <p class="text-xs text-muted-foreground italic bg-muted/60 dark:bg-secondary/50 p-2 rounded-xl border border-border/80 dark:border-border/90">
                            Notas del chofer: "{order.delivery_notes}"
                        </p>
                    {/if}
                </div>

                <!-- Selección de Destino Bento -->
                <div class="space-y-2.5">
                    <Label class="text-sm font-medium text-foreground">Destino de la gestión</Label>
                    <div class="grid grid-cols-1 gap-2.5">
                        <!-- Opción 1: En Preparación -->
                        <button
                            type="button"
                            class="flex items-start gap-3 p-3.5 rounded-2xl border text-left transition-all cursor-pointer {destination === 'en_preparacion'
                                ? 'border-primary bg-primary/10 dark:bg-primary/15 text-foreground shadow-2xs'
                                : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-muted-foreground'}"
                            onclick={() => (destination = 'en_preparacion')}
                        >
                            <div class="size-9 rounded-xl flex items-center justify-center shrink-0 {destination === 'en_preparacion' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'}">
                                <RotateCcw class="size-4.5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-foreground">Volver a Depósito / En Preparación</span>
                                    {#if destination === 'en_preparacion'}
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-primary/20 text-primary uppercase">Seleccionado</span>
                                    {/if}
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                    Devuelve el pedido a depósito para rearmado, cambio de mercadería o reprogramar visita posterior. Se desasigna de la ruta del chofer.
                                </p>
                            </div>
                        </button>

                        <!-- Opción 2: Cancelación Definitiva -->
                        <button
                            type="button"
                            class="flex items-start gap-3 p-3.5 rounded-2xl border text-left transition-all cursor-pointer {destination === 'cancelado'
                                ? 'border-destructive bg-destructive/10 dark:bg-destructive/15 text-foreground shadow-2xs'
                                : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-muted-foreground'}"
                            onclick={() => (destination = 'cancelado')}
                        >
                            <div class="size-9 rounded-xl flex items-center justify-center shrink-0 {destination === 'cancelado' ? 'bg-destructive text-destructive-foreground' : 'bg-muted text-muted-foreground'}">
                                <Trash2 class="size-4.5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-foreground">Cancelar Pedido Definitivamente</span>
                                    {#if destination === 'cancelado'}
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-destructive/20 text-destructive uppercase">Seleccionado</span>
                                    {/if}
                                </div>
                                <p class="text-xs text-muted-foreground mt-0.5 leading-relaxed">
                                    Anula la operación, reincorpora automáticamente todo el stock físico a depósito y anula el saldo deudor en cuenta corriente del cliente.
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Textarea de Observaciones -->
                <div class="space-y-1.5">
                    <Label for="reschedule-reason" class="text-sm font-medium text-foreground">Motivo u observaciones (opcional)</Label>
                    <Textarea
                        id="reschedule-reason"
                        rows={2}
                        maxlength="255"
                        placeholder="Ej: Cliente acordó reprogramación para mañana / mercadería devuelta a depósito..."
                        bind:value={reason}
                        class="resize-none bg-field border-input text-sm rounded-xl"
                    />
                </div>

                {#if errorMessage}
                    <div class="p-3 rounded-xl bg-destructive/10 border border-destructive/30 text-destructive text-xs flex items-center gap-2">
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{errorMessage}</span>
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
                Cerrar
            </Button>

            {#if destination === 'cancelado'}
                <Button
                    variant="destructive"
                    class="w-full sm:w-auto h-11 min-h-[44px] px-6 text-sm font-semibold gap-2 shadow-xs"
                    onclick={handleSubmit}
                    disabled={isSubmitting}
                >
                    {#if isSubmitting}
                        <Loader2 class="size-4 animate-spin" />
                        <span>Procesando...</span>
                    {:else}
                        <Trash2 class="size-4" />
                        <span>Cancelar Pedido Definitivamente</span>
                    {/if}
                </Button>
            {:else}
                <Button
                    variant="default"
                    class="w-full sm:w-auto h-11 min-h-[44px] px-6 text-sm font-semibold gap-2 shadow-xs"
                    onclick={handleSubmit}
                    disabled={isSubmitting}
                >
                    {#if isSubmitting}
                        <Loader2 class="size-4 animate-spin" />
                        <span>Procesando...</span>
                    {:else}
                        <RotateCcw class="size-4" />
                        <span>Devolver a Preparación</span>
                    {/if}
                </Button>
            {/if}
        </DialogFooter>
    </DialogContent>
</Dialog>
