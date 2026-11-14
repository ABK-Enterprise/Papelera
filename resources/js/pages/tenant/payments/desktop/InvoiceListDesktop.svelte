<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import FileText from 'lucide-svelte/icons/file-text';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
    import Eye from 'lucide-svelte/icons/eye';
    import Printer from 'lucide-svelte/icons/printer';
    import Download from 'lucide-svelte/icons/download';

    export interface FiscalInvoiceItem {
        id: number;
        tenant_id: number;
        customer_order_id: number;
        created_by_user_id: number;
        cbte_tipo: number;
        cbte_letter: string;
        punto_venta: number;
        numero_comprobante: number;
        formatted_number: string;
        cae: string;
        cae_due_date: string;
        issue_date: string;
        doc_tipo: number;
        doc_nro: string;
        receptor_condicion_iva_id: number;
        receptor_name: string;
        receptor_address?: string | null;
        concepto: number;
        importe_total: number | string;
        importe_neto: number | string;
        importe_iva: number | string;
        importe_exento: number | string;
        importe_tributos: number | string;
        status: string;
        notes?: string | null;
        created_at: string;
        customer_order?: {
            id: number;
            order_number: string;
            total_amount: number | string;
            customer?: {
                id: number;
                name: string;
                tax_id?: string | null;
            } | null;
        } | null;
        created_by_user?: {
            id: number;
            name: string;
        } | null;
        associated_invoice?: {
            id: number;
            formatted_number: string;
            cbte_letter: string;
        } | null;
    }

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
                return 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border-blue-500/20';
            case 6: // Factura B
                return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20';
            case 11: // Factura C
                return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20';
            case 3: // NC A
            case 8: // NC B
            case 13: // NC C
                return 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20';
            default:
                return 'bg-muted text-muted-foreground border-border/80';
        }
    }

    function getCbteName(cbteTipo: number, letter: string) {
        if ([3, 8, 13].includes(cbteTipo)) {
            return `Nota de Crédito ${letter}`;
        }
        return `Factura ${letter}`;
    }
</script>

<div class="flex flex-col gap-3 font-sans">
    {#if invoices.length === 0}
        <div class="p-8 rounded-2xl border border-border/70 bg-card text-center text-sm text-muted-foreground shadow-xs">
            No se encontraron facturas fiscales que coincidan con los criterios seleccionados.
        </div>
    {:else}
        {#each invoices as inv (inv.id)}
            {@const isCreditNote = [3, 8, 13].includes(inv.cbte_tipo)}
            <div
                class="flex items-center justify-between p-4.5 rounded-2xl border border-border/80 bg-card shadow-xs hover:border-border hover:bg-muted/40 dark:hover:bg-secondary/30 transition-all duration-200 gap-4 select-none"
            >
                <!-- Lado Izquierdo: Icono + Comprobante y Letra -->
                <div class="flex items-center gap-3.5 min-w-[210px] shrink-0">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <FileText class="size-5 text-primary" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="font-bold text-base text-foreground tabular-nums font-sans">
                            {inv.formatted_number}
                        </span>
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full border inline-block {getCbteBadgeStyle(inv.cbte_tipo)}">
                                {getCbteName(inv.cbte_tipo, inv.cbte_letter)}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Receptor / Cliente -->
                <div class="flex flex-col min-w-[190px] max-w-[260px]">
                    <span class="font-semibold text-sm sm:text-base text-foreground leading-snug truncate max-w-[250px] block font-sans" title={inv.receptor_name}>
                        {inv.receptor_name}
                    </span>
                    <span class="text-xs text-muted-foreground block tabular-nums font-sans">
                        {inv.doc_tipo === 80 ? 'CUIT' : (inv.doc_tipo === 96 ? 'DNI' : 'Doc')}: {inv.doc_nro}
                    </span>
                </div>

                <!-- Pedido Imputado & CAE -->
                <div class="min-w-[160px] max-w-[210px] flex flex-col gap-1">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        {#if inv.customer_order}
                            <span class="text-xs font-semibold font-sans tabular-nums px-2 py-0.5 rounded-md bg-muted/60 dark:bg-secondary/45 text-foreground border border-border/80 inline-block shadow-2xs">
                                {inv.customer_order.order_number}
                            </span>
                        {/if}
                        <span class="text-xs text-muted-foreground tabular-nums">
                            {formatDate(inv.issue_date)}
                        </span>
                    </div>
                    <span class="text-xs text-muted-foreground/80 tabular-nums truncate" title={`CAE: ${inv.cae}`}>
                        CAE: {inv.cae}
                    </span>
                </div>

                <!-- Estado Fiscal -->
                <div class="min-w-[95px] flex items-center justify-center">
                    {#if inv.status === 'aprobado'}
                        <span class="bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider font-sans">
                            Aprobado
                        </span>
                    {:else}
                        <span class="bg-muted text-muted-foreground border border-border text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider font-sans">
                            {inv.status}
                        </span>
                    {/if}
                </div>

                <!-- Importe Total -->
                <div class="min-w-[140px] text-right">
                    <span class="font-sans font-bold text-base sm:text-lg tabular-nums block {isCreditNote ? 'text-rose-700 dark:text-rose-400' : 'text-foreground'}">
                        {isCreditNote ? '-' : ''}{formatPrice(inv.importe_total)}
                    </span>
                    <span class="text-xs text-muted-foreground block font-sans tabular-nums font-medium">
                        Neto: {formatPrice(inv.importe_neto)}
                    </span>
                </div>

                <!-- Acciones Rápidas -->
                <div class="flex items-center gap-2 shrink-0">
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-9 px-3 rounded-xl text-sm font-medium gap-1.5 shadow-2xs min-h-[36px]"
                        onclick={() => onViewDetail?.(inv)}
                    >
                        <Eye class="size-3.5" />
                        <span>Ver Detalle</span>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    class="size-9 min-h-[36px] min-w-[36px] rounded-xl border border-border/80 text-muted-foreground hover:text-foreground hover:bg-muted/60 dark:hover:bg-secondary/60 transition-colors shadow-2xs flex items-center justify-center cursor-pointer shrink-0 focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-hidden"
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
            </div>
        {/each}
    {/if}
</div>
