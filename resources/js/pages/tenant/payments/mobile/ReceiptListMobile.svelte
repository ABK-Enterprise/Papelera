<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
    import Eye from 'lucide-svelte/icons/eye';
    import Printer from 'lucide-svelte/icons/printer';
    import Ban from 'lucide-svelte/icons/ban';
    import type { ReceiptItem } from '../desktop/ReceiptListDesktop.svelte';

    let {
        receipts = [],
        onViewDetail = undefined,
        onRequestVoid = undefined,
        onPreviewPdf = undefined,
    }: {
        receipts: ReceiptItem[];
        onViewDetail?: (receipt: ReceiptItem) => void;
        onRequestVoid?: (receipt: ReceiptItem) => void;
        onPreviewPdf?: (receipt: ReceiptItem) => void;
    } = $props();

    function formatPrice(val: number | string) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    function truncateRef(ref: string | null | undefined, maxLen = 14): string {
        if (!ref) return '';
        return ref.length > maxLen ? ref.slice(0, maxLen) + '...' : ref;
    }
</script>

<div class="space-y-3">
    {#if receipts.length === 0}
        <Card class="p-8 text-center text-sm text-muted-foreground rounded-2xl border border-border/70 shadow-xs">
            No se encontraron recibos coincidentes.
        </Card>
    {:else}
        {#each receipts as rc (rc.id)}
            <div
                class="p-4 rounded-2xl border border-border/70 bg-card shadow-xs space-y-3 {rc.is_cancelled ? 'opacity-70 bg-muted/10' : ''}"
            >
                <!-- Fila Superior: Nº Recibo + Estado + Menú '...' -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0 flex-wrap">
                        <span class="font-bold text-xs text-foreground font-mono tabular-nums">
                            {rc.receipt_number}
                        </span>
                        {#if rc.is_cancelled}
                            <span
                                title={rc.cancellation_reason ? `Motivo: ${rc.cancellation_reason}` : undefined}
                                class="bg-destructive/10 text-destructive border border-destructive/20 text-xs font-bold px-2 py-0.5 rounded-full line-through shrink-0"
                            >
                                Anulado
                            </span>
                        {:else}
                            <span class="bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 text-xs font-semibold px-2 py-0.5 rounded-full shrink-0">
                                Válido
                            </span>
                        {/if}
                    </div>

                    <!-- Botón '...' con DropdownMenu (Touch Target >= 44px) -->
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    class="min-h-[44px] min-w-[44px] size-11 rounded-xl border border-border/70 text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors shadow-2xs flex items-center justify-center cursor-pointer shrink-0 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-hidden"
                                    aria-label={`Opciones para recibo ${rc.receipt_number}`}
                                >
                                    <MoreVertical class="size-4" />
                                </button>
                            {/snippet}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 shadow-lg">
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-sm font-medium cursor-pointer py-2.5"
                                onclick={() => onViewDetail?.(rc)}
                            >
                                <Eye class="size-4 text-muted-foreground" />
                                <span>Ver detalle</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-sm font-medium cursor-pointer py-2.5"
                                onclick={() => onPreviewPdf?.(rc)}
                            >
                                <Printer class="size-4 text-muted-foreground" />
                                <span>Ver e Imprimir PDF</span>
                            </DropdownMenuItem>
                            {#if !rc.is_cancelled}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="flex items-center gap-2 text-sm font-medium text-destructive focus:text-destructive focus:bg-destructive/10 hover:text-destructive hover:bg-destructive/10 cursor-pointer py-2.5"
                                    onclick={() => onRequestVoid?.(rc)}
                                >
                                    <Ban class="size-4 text-destructive" />
                                    <span>Anular recibo</span>
                                </DropdownMenuItem>
                            {/if}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- Fila Media: Cliente + Método + Referencia -->
                <div class="space-y-1.5 pt-0.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            {#if rc.customer}
                                <span class="text-sm font-semibold text-foreground truncate block" title={rc.customer.name}>
                                    {rc.customer.name}
                                </span>
                                {#if rc.customer.tax_id}
                                    <span class="text-xs text-muted-foreground block tabular-nums">
                                        {rc.customer.tax_id}
                                    </span>
                                {/if}
                            {:else}
                                <span class="text-xs text-muted-foreground italic block">
                                    Cliente Ocasional
                                </span>
                            {/if}
                        </div>

                        <div class="shrink-0">
                            <span class="text-xs font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full border border-primary/20 bg-primary/10 text-primary inline-block">
                                {rc.payment_method}
                            </span>
                        </div>
                    </div>

                    <!-- Pedidos Imputados (Máx 1 en Mobile + indicador '+ X') / Referencia -->
                    {#if rc.orders && rc.orders.length > 0}
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5">
                            <span class="text-xs font-mono font-semibold tabular-nums px-2 py-0.5 rounded-md bg-muted/70 text-foreground border border-border/70 inline-block shadow-2xs">
                                {rc.orders[0].order_number}
                            </span>
                            {#if rc.orders.length > 1}
                                <span
                                    class="size-5 inline-flex items-center justify-center rounded-full bg-primary/10 text-primary border border-primary/25 text-xs font-bold font-sans tabular-nums shrink-0"
                                    title={rc.orders.slice(1).map((o) => o.order_number).join(', ')}
                                >
                                    +{rc.orders.length - 1}
                                </span>
                            {/if}
                        </div>
                    {:else if rc.reference_number}
                        <div class="flex items-center gap-1.5 pt-0.5">
                            <span class="text-xs font-medium text-muted-foreground">Ref:</span>
                            <span
                                class="text-xs font-mono tabular-nums text-muted-foreground bg-muted/50 px-2 py-0.5 rounded-md border border-border/50 truncate max-w-[200px] inline-block"
                                title={rc.reference_number}
                            >
                                {truncateRef(rc.reference_number, 14)}
                            </span>
                        </div>
                    {/if}
                </div>

                <!-- Fila Inferior: Monto Destacado + Botón Ver detalle (Touch Target >= 44px) -->
                <div class="pt-2.5 border-t border-border/60 flex items-center justify-between gap-3">
                    <div>
                        <span class="text-xs text-muted-foreground block">Monto total</span>
                        <span class="text-sm font-bold tabular-nums text-foreground block">
                            {formatPrice(rc.amount)}
                        </span>
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        class="h-11 min-h-[44px] px-4 text-sm font-medium rounded-xl gap-1.5 cursor-pointer shrink-0"
                        onclick={() => onViewDetail?.(rc)}
                    >
                        <Eye class="size-4 text-muted-foreground" />
                        <span>Ver detalle</span>
                    </Button>
                </div>
            </div>
        {/each}
    {/if}
</div>
