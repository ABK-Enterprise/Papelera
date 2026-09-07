<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import Ban from 'lucide-svelte/icons/ban';
    import Printer from 'lucide-svelte/icons/printer';
    import type { ReceiptItem } from '../desktop/ReceiptListDesktop.svelte';

    let {
        receipts = [],
        onRequestVoid = undefined,
        onPreviewPdf = undefined,
    }: {
        receipts: ReceiptItem[];
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

    function formatDate(val: string) {
        if (!val) return '';
        return new Intl.DateTimeFormat('es-AR', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(val));
    }
</script>

<div class="space-y-2.5">
    {#if receipts.length === 0}
        <Card class="p-8 text-center text-xs text-muted-foreground">
            No se encontraron recibos coincidentes.
        </Card>
    {:else}
        {#each receipts as rc}
            <div class="p-3.5 rounded-xl border bg-card border-border/80 shadow-2xs space-y-2.5 {rc.is_cancelled ? 'opacity-60 bg-muted/20' : ''}">
                <div class="flex items-start justify-between gap-2">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-xs sm:text-sm text-foreground font-sans">
                                {rc.receipt_number}
                            </span>
                            {#if rc.is_cancelled}
                                <span class="text-[9px] font-bold text-destructive bg-destructive/10 px-1.5 py-0.2 rounded line-through">
                                    ANULADO
                                </span>
                            {:else}
                                <span class="text-[9px] font-semibold text-emerald-600 bg-emerald-500/10 px-1.5 py-0.2 rounded border border-emerald-500/20">
                                    VÁLIDO
                                </span>
                            {/if}
                        </div>
                        <span class="text-[11px] text-muted-foreground block">
                            {formatDate(rc.created_at)}
                        </span>
                    </div>

                    <div class="text-right shrink-0">
                        <span class="text-xs sm:text-sm font-bold font-sans tabular-nums text-foreground block">
                            {formatPrice(rc.amount)}
                        </span>
                        <span class="text-[10px] uppercase font-semibold text-primary">
                            {rc.payment_method}
                        </span>
                    </div>
                </div>

                <div class="pt-2 border-t border-border/50 flex items-center justify-between gap-2 text-xs">
                    <div class="min-w-0">
                        {#if rc.customer}
                            <span class="font-medium text-foreground truncate block">{rc.customer.name}</span>
                        {:else}
                            <span class="text-muted-foreground italic">Cliente Ocasional</span>
                        {/if}
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 min-h-[32px] px-2.5 text-xs text-foreground hover:bg-muted/80 gap-1 rounded-lg cursor-pointer"
                            onclick={() => onPreviewPdf?.(rc)}
                            title="Ver e imprimir comprobante"
                        >
                            <Printer class="size-3 text-muted-foreground" />
                            <span>PDF</span>
                        </Button>
                        {#if !rc.is_cancelled}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-8 min-h-[32px] px-2.5 text-xs text-destructive border-destructive/30 hover:bg-destructive/10 gap-1 rounded-lg shrink-0 cursor-pointer"
                                onclick={() => onRequestVoid?.(rc)}
                            >
                                <Ban class="size-3" />
                                <span>Anular</span>
                            </Button>
                        {/if}
                    </div>
                </div>
            </div>
        {/each}
    {/if}
</div>
