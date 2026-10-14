<script lang="ts">
    import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogBody } from '@/components/ui/dialog';
    import { Button } from '@/components/ui/button';
    import type { DeliveryOrder } from './DeliverOrderModal.svelte';
    import FileText from 'lucide-svelte/icons/file-text';
    import Truck from 'lucide-svelte/icons/truck';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';

    interface Props {
        open?: boolean;
        order?: DeliveryOrder | null;
        onclose?: () => void;
    }

    let {
        open = $bindable(false),
        order = null,
        onclose,
    }: Props = $props();

    function handleClose() {
        open = false;
        if (onclose) onclose();
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-md p-0 flex flex-col max-h-[85vh] sm:max-h-[88vh] bg-card text-card-foreground border border-border/80 overflow-hidden">
        <DialogHeader class="p-0 border-b border-border/60 shrink-0 bg-card">
            <div class="flex items-center justify-between gap-2.5 sm:gap-3.5 p-4 sm:p-[3px] pb-3.5 sm:pb-[3px] pr-4 sm:pr-4 w-full">
                <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
                    <div class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center shrink-0">
                        <FileText class="size-5 sm:size-6 text-primary" />
                    </div>
                    <div class="min-w-0 flex-1 flex flex-col justify-center">
                        <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground leading-snug sm:leading-none truncate">
                            Notas y observaciones
                        </DialogTitle>
                        <DialogDescription class="sr-only">Historial de notas y observaciones del pedido</DialogDescription>
                    </div>
                </div>
                {#if order}
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold font-sans tabular-nums bg-primary/15 text-primary border border-primary/20 shrink-0">
                        #{order.order_number}
                    </span>
                {/if}
            </div>
        </DialogHeader>

        <DialogBody class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-3.5">
            {#if order}
                <!-- Resumen de Cliente -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-background/50 border border-border/70 text-xs">
                    <span class="font-semibold text-foreground">{order.customer_name}</span>
                    <span class="text-muted-foreground font-sans font-bold tabular-nums">#{order.order_number}</span>
                </div>

                <!-- 1. Notas de Venta / Instrucciones de Entrega -->
                {#if order.notes}
                    <div class="rounded-2xl border border-border/80 bg-background/40 p-3.5 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs font-semibold text-foreground">
                            <FileText class="size-4 text-primary shrink-0" />
                            <span>Instrucciones de venta / entrega</span>
                        </div>
                        <p class="text-xs text-foreground/90 leading-relaxed whitespace-pre-wrap pl-6">
                            {order.notes}
                        </p>
                    </div>
                {/if}

                <!-- 2. Motivo de No Entrega (si existió) -->
                {#if order.delivery_failure_reason}
                    <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-3.5 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs font-semibold text-rose-700 dark:text-rose-300">
                            <AlertTriangle class="size-4 text-rose-600 dark:text-rose-400 shrink-0" />
                            <span>Motivo de reporte de no entrega</span>
                        </div>
                        <p class="text-xs text-rose-800 dark:text-rose-200 font-medium pl-6">
                            {order.delivery_failure_reason}
                        </p>
                    </div>
                {/if}

                <!-- 3. Observaciones del Chofer / Repartidor -->
                {#if order.delivery_notes}
                    <div class="rounded-2xl border border-border/80 bg-background/40 p-3.5 space-y-1.5">
                        <div class="flex items-center gap-2 text-xs font-semibold text-foreground">
                            <Truck class="size-4 text-sky-500 shrink-0" />
                            <span>Observaciones del chofer / repartidor</span>
                        </div>
                        <p class="text-xs text-foreground/90 leading-relaxed whitespace-pre-wrap pl-6">
                            {order.delivery_notes}
                        </p>
                    </div>
                {/if}

                {#if !order.notes && !order.delivery_failure_reason && !order.delivery_notes}
                    <div class="p-8 text-center text-xs text-muted-foreground border border-dashed border-border/80 rounded-2xl">
                        Este pedido no cuenta con notas ni observaciones registradas.
                    </div>
                {/if}
            {/if}
        </DialogBody>

        <DialogFooter class="p-5 sm:p-6 pt-3 border-t border-border/50 flex items-center justify-end shrink-0">
            <Button
                variant="secondary"
                class="w-full sm:w-auto h-11 min-h-[44px] px-6 text-sm font-semibold"
                onclick={handleClose}
            >
                Cerrar
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
