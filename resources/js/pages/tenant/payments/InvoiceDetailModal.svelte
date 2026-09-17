<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogClose,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Card } from '@/components/ui/card';
    import FileText from 'lucide-svelte/icons/file-text';
    import Printer from 'lucide-svelte/icons/printer';
    import Download from 'lucide-svelte/icons/download';
    import Copy from 'lucide-svelte/icons/copy';
    import Check from 'lucide-svelte/icons/check';
    import Calendar from 'lucide-svelte/icons/calendar';
    import User from 'lucide-svelte/icons/user';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import type { FiscalInvoiceItem } from './desktop/InvoiceListDesktop.svelte';

    let {
        open = $bindable(false),
        invoice = null,
        onPreviewPdf = undefined,
    }: {
        open?: boolean;
        invoice: FiscalInvoiceItem | null;
        onPreviewPdf?: (invoice: FiscalInvoiceItem) => void;
    } = $props();

    let copiedCae = $state(false);

    $effect(() => {
        if (!open) {
            copiedCae = false;
        }
    });

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

    function copyCae() {
        if (!invoice?.cae) return;
        navigator.clipboard.writeText(invoice.cae);
        copiedCae = true;
        setTimeout(() => {
            copiedCae = false;
        }, 2000);
    }

    function getIvaConditionName(id: number) {
        switch (id) {
            case 1:
                return 'IVA Responsable Inscripto';
            case 4:
                return 'IVA Exento';
            case 6:
                return 'Responsable Monotributo';
            default:
                return 'Consumidor Final';
        }
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-2xl p-5 sm:p-6 space-y-4 font-sans">
        {#if invoice}
            <DialogHeader class="space-y-1">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <FileText class="size-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle class="text-lg sm:text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
                                <span>{invoice.formatted_number}</span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border uppercase bg-primary/10 text-primary border-primary/20">
                                    {invoice.cbte_letter}
                                </span>
                            </DialogTitle>
                            <DialogDescription class="text-xs text-muted-foreground">
                                Comprobante electrónico autorizado por ARCA
                            </DialogDescription>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider">
                            {invoice.status}
                        </span>
                    </div>
                </div>
            </DialogHeader>

            <DialogBody class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                <!-- 1. Bloque de Auditoría Fiscal ARCA -->
                <Card class="p-4 rounded-xl border border-border/80 bg-muted/20 space-y-3">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground border-b border-border/60 pb-2">
                        <ShieldCheck class="size-4 text-primary" />
                        <span>Datos Fiscales Oficiales</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <!-- CAE -->
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-medium block">Código de Autorización Electrónica (CAE):</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-sm text-foreground tabular-nums">
                                    {invoice.cae}
                                </span>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground transition-colors p-1 rounded hover:bg-muted cursor-pointer"
                                    onclick={copyCae}
                                    title="Copiar CAE"
                                >
                                    {#if copiedCae}
                                        <Check class="size-3.5 text-emerald-600" />
                                    {:else}
                                        <Copy class="size-3.5" />
                                    {/if}
                                </button>
                            </div>
                        </div>

                        <!-- Vencimiento de CAE -->
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-medium block">Vencimiento de CAE:</span>
                            <span class="font-sans font-semibold text-foreground tabular-nums">
                                {formatDate(invoice.cae_due_date)}
                            </span>
                        </div>

                        <!-- Fecha de Emisión -->
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-medium block">Fecha de Emisión:</span>
                            <span class="font-sans font-semibold text-foreground tabular-nums flex items-center gap-1.5">
                                <Calendar class="size-3.5 text-muted-foreground" />
                                {formatDate(invoice.issue_date)}
                            </span>
                        </div>

                        <!-- Operador / Usuario Emisor -->
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-medium block">Emitido por:</span>
                            <span class="font-sans font-semibold text-foreground flex items-center gap-1.5">
                                <User class="size-3.5 text-muted-foreground" />
                                {invoice.created_by_user?.name || 'Sistema'}
                            </span>
                        </div>
                    </div>

                    <!-- Comprobante Asociado (si aplica) -->
                    {#if invoice.associated_invoice}
                        <div class="p-2.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-xs text-blue-700 dark:text-blue-400">
                            <strong>Comprobante Asociado:</strong> Factura {invoice.associated_invoice.formatted_number}
                        </div>
                    {/if}
                </Card>

                <!-- 2. Datos del Receptor / Cliente -->
                <Card class="p-4 rounded-xl border border-border/80 bg-card space-y-3">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground border-b border-border/60 pb-2">
                        <User class="size-4 text-primary" />
                        <span>Receptor del Comprobante</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-muted-foreground font-medium block">Razón Social / Nombre:</span>
                            <span class="font-semibold text-foreground text-sm block mt-0.5">
                                {invoice.receptor_name}
                            </span>
                        </div>

                        <div>
                            <span class="text-muted-foreground font-medium block">
                                {invoice.doc_tipo === 80 ? 'CUIT' : (invoice.doc_tipo === 96 ? 'DNI' : 'Documento')}:
                            </span>
                            <span class="font-semibold font-mono text-foreground text-sm block mt-0.5 tabular-nums">
                                {invoice.doc_nro}
                            </span>
                        </div>

                        <div>
                            <span class="text-muted-foreground font-medium block">Condición frente al IVA:</span>
                            <span class="font-medium text-foreground block mt-0.5">
                                {getIvaConditionName(invoice.receptor_condicion_iva_id)}
                            </span>
                        </div>

                        {#if invoice.receptor_address}
                            <div>
                                <span class="text-muted-foreground font-medium block">Domicilio Comercial:</span>
                                <span class="font-medium text-foreground block mt-0.5">
                                    {invoice.receptor_address}
                                </span>
                            </div>
                        {/if}
                    </div>
                </Card>

                <!-- 3. Pedido Asociado -->
                {#if invoice.customer_order}
                    <Card class="p-4 rounded-xl border border-border/80 bg-card space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                <ShoppingBag class="size-4 text-primary" />
                                <span>Pedido Asociado</span>
                            </div>
                            <span class="text-xs font-semibold font-sans tabular-nums px-2 py-0.5 rounded-md bg-muted text-foreground border border-border">
                                {invoice.customer_order.order_number}
                            </span>
                        </div>
                    </Card>
                {/if}

                <!-- 4. Cuadro de Totales y Desglose Impositivo -->
                <Card class="p-4 rounded-xl border border-border/80 bg-card space-y-2">
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Importe Neto Gravado:</span>
                            <span class="font-sans font-semibold text-foreground tabular-nums">
                                {formatPrice(invoice.importe_neto)}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-muted-foreground">
                            <span>Importe IVA:</span>
                            <span class="font-sans font-semibold text-foreground tabular-nums">
                                {formatPrice(invoice.importe_iva)}
                            </span>
                        </div>

                        {#if Number(invoice.importe_exento) > 0}
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span>No Gravado / Exento:</span>
                                <span class="font-sans font-semibold text-foreground tabular-nums">
                                    {formatPrice(invoice.importe_exento)}
                                </span>
                            </div>
                        {/if}

                        {#if Number(invoice.importe_tributos) > 0}
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span>Otros Tributos:</span>
                                <span class="font-sans font-semibold text-foreground tabular-nums">
                                    {formatPrice(invoice.importe_tributos)}
                                </span>
                            </div>
                        {/if}

                        <div class="flex items-center justify-between pt-2 border-t border-border/70 text-sm">
                            <span class="font-bold text-foreground">IMPORTE TOTAL:</span>
                            <span class="font-sans font-bold text-lg sm:text-xl text-primary tabular-nums">
                                {formatPrice(invoice.importe_total)}
                            </span>
                        </div>
                    </div>
                </Card>
            </DialogBody>

            <DialogFooter class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-border/60 pt-4">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <Button
                        type="button"
                        variant="default"
                        class="min-h-[44px] h-11 px-4 rounded-xl text-xs font-semibold gap-2 shadow-xs w-full sm:w-auto"
                        onclick={() => onPreviewPdf?.(invoice)}
                    >
                        <Printer class="size-4" />
                        <span>Ver e Imprimir PDF</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] h-11 px-4 rounded-xl text-xs font-semibold gap-2 shadow-xs w-full sm:w-auto"
                        onclick={() => window.open(`/tenant/cobranzas/facturas/${invoice.id}/pdf`, '_blank')}
                    >
                        <Download class="size-4" />
                        <span>Descargar PDF</span>
                    </Button>
                </div>

                <DialogClose asChild>
                    {#snippet children(props)}
                        <Button
                            {...props}
                            type="button"
                            variant="secondary"
                            class="min-h-[44px] h-11 px-5 rounded-xl text-xs font-semibold w-full sm:w-auto"
                        >
                            Cerrar
                        </Button>
                    {/snippet}
                </DialogClose>
            </DialogFooter>
        {/if}
    </DialogContent>
</Dialog>
