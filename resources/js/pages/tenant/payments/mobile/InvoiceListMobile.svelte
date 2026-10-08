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
    import Download from 'lucide-svelte/icons/download';
    import type { FiscalInvoiceItem } from '../desktop/InvoiceListDesktop.svelte';

    let {
        invoices = [],
        onViewDetail = undefined,
        onPreviewPdf = undefined,
    }: {
        invoices: FiscalInvoiceItem[];
        onViewDetail?: (invoice: FiscalInvoiceItem) => void;
        onPreviewPdf?: (invoice: FiscalInvoiceItem) => void;
    } = $props();

    function formatPrice(val: number | string) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2,
        }).format(Number(val) || 0);
    }

    function formatDate(val: string | null | undefined): string {
        if (!val) return '—';
        const parts = val.split('T')[0].split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return val;
    }

    function getCbteBadgeStyle(cbteTipo: number) {
        switch (cbteTipo) {
            case 1: // Factura A
                return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20';
            case 6: // Factura B
                return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
            case 11: // Factura C
                return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20';
            case 3: // NC A
            case 8: // NC B
            case 13: // NC C
                return 'bg-destructive/10 text-destructive border-destructive/20';
            default:
                return 'bg-muted text-muted-foreground border-border/80';
        }
    }

    function getCbteName(cbteTipo: number, letter: string) {
        if ([3, 8, 13].includes(cbteTipo)) {
            return `NC ${letter}`;
        }
        return `Factura ${letter}`;
    }
</script>

<div class="space-y-3 font-sans">
    {#if invoices.length === 0}
        <Card class="p-8 text-center text-sm text-muted-foreground rounded-2xl border border-border/70 shadow-xs">
            No se encontraron facturas coincidentes.
        </Card>
    {:else}
        {#each invoices as inv (inv.id)}
            {@const isCreditNote = [3, 8, 13].includes(inv.cbte_tipo)}
            <div
                class="p-4 rounded-2xl border border-border/70 bg-card shadow-xs space-y-3"
            >
                <!-- Fila Superior: Nº Comprobante + Badge + Menú '...' -->
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0 flex-wrap">
                        <span class="font-bold text-base text-foreground font-sans tabular-nums">
                            {inv.formatted_number}
                        </span>
                        <span class="text-xs font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full border shrink-0 {getCbteBadgeStyle(inv.cbte_tipo)}">
                            {getCbteName(inv.cbte_tipo, inv.cbte_letter)}
                        </span>
                    </div>

                    <!-- Botón '...' con DropdownMenu (Touch Target >= 44px) -->
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    class="min-h-[44px] min-w-[44px] size-11 rounded-xl border border-border/70 text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors shadow-2xs flex items-center justify-center cursor-pointer shrink-0 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-hidden"
                                    aria-label={`Opciones para comprobante ${inv.formatted_number}`}
                                >
                                    <MoreVertical class="size-4" />
                                </button>
                            {/snippet}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 shadow-lg">
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                onclick={() => onViewDetail?.(inv)}
                            >
                                <Eye class="size-4 text-muted-foreground" />
                                <span>Ver detalle</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                onclick={() => onPreviewPdf?.(inv)}
                            >
                                <Printer class="size-4 text-muted-foreground" />
                                <span>Ver e Imprimir PDF</span>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-xs font-medium cursor-pointer py-2.5"
                                onclick={() => window.open(`/tenant/cobranzas/facturas/${inv.id}/pdf`, '_blank')}
                            >
                                <Download class="size-4 text-muted-foreground" />
                                <span>Descargar PDF</span>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- Fila Media: Cliente + Importe Total -->
                <div class="space-y-1 pt-0.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <span class="text-sm font-semibold text-foreground truncate block leading-snug" title={inv.receptor_name}>
                                {inv.receptor_name}
                            </span>
                            <span class="text-xs text-muted-foreground block tabular-nums">
                                {inv.doc_tipo === 80 ? 'CUIT' : (inv.doc_tipo === 96 ? 'DNI' : 'Doc')}: {inv.doc_nro}
                            </span>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="font-sans font-bold text-base tabular-nums block {isCreditNote ? 'text-destructive' : 'text-foreground'}">
                                {isCreditNote ? '-' : ''}{formatPrice(inv.importe_total)}
                            </span>
                            <span class="text-xs text-muted-foreground block tabular-nums font-medium">
                                Neto: {formatPrice(inv.importe_neto)}
                            </span>
                        </div>
                    </div>

                    <!-- Pedido Imputado + Fecha + CAE -->
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-border/50 text-xs">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            {#if inv.customer_order}
                                <span class="font-semibold tabular-nums px-1.5 py-0.5 rounded bg-muted text-foreground border border-border/60 text-xs">
                                    {inv.customer_order.order_number}
                                </span>
                            {/if}
                            <span class="text-muted-foreground tabular-nums text-xs">
                                {formatDate(inv.issue_date)}
                            </span>
                        </div>

                        <span class="text-xs text-muted-foreground/80 tabular-nums truncate max-w-[140px]" title={`CAE: ${inv.cae}`}>
                            CAE: {inv.cae}
                        </span>
                    </div>
                </div>

                <!-- Botón de Acción Táctil Mobile (Touch Target >= 44px) -->
                <div class="pt-1">
                    <Button
                        variant="secondary"
                        class="w-full min-h-[44px] h-11 rounded-xl text-sm font-medium gap-2 shadow-2xs justify-center"
                        onclick={() => onViewDetail?.(inv)}
                    >
                        <Eye class="size-4" />
                        <span>Ver Detalle de Factura</span>
                    </Button>
                </div>
            </div>
        {/each}
    {/if}
</div>
