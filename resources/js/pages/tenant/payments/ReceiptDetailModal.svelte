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
    import { cn } from '@/lib/utils';
    import Ban from 'lucide-svelte/icons/ban';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Check from 'lucide-svelte/icons/check';
    import Copy from 'lucide-svelte/icons/copy';
    import FileText from 'lucide-svelte/icons/file-text';
    import Hash from 'lucide-svelte/icons/hash';
    import Printer from 'lucide-svelte/icons/printer';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import { hasPermission } from '@/lib/permissions';

    export interface ReceiptItem {
        id: number;
        receipt_number: string;
        amount: string | number;
        payment_method: string;
        reference_number: string | null;
        notes: string | null;
        is_cancelled: boolean;
        cancelled_at: string | null;
        cancellation_reason: string | null;
        created_at: string;
        customer?: { id: number; name: string; tax_id: string | null } | null;
        created_by_user?: { id: number; name: string } | null;
        cancelled_by_user?: { id: number; name: string } | null;
        orders?: { id: number; order_number: string; total_amount: string | number }[];
    }

    let {
        open = $bindable(false),
        receipt = null,
        onPreviewPdf = undefined,
        onRequestVoid = undefined,
    }: {
        open?: boolean;
        receipt: ReceiptItem | null;
        onPreviewPdf?: (receipt: ReceiptItem) => void;
        onRequestVoid?: (receipt: ReceiptItem) => void;
    } = $props();

    let copiedReference = $state(false);

    $effect(() => {
        if (!open) {
            copiedReference = false;
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
        try {
            const d = new Date(val);
            if (isNaN(d.getTime())) return val;
            const day = d.getDate();
            const months = [
                'ene',
                'feb',
                'mar',
                'abr',
                'may',
                'jun',
                'jul',
                'ago',
                'sep',
                'oct',
                'nov',
                'dic',
            ];
            const month = months[d.getMonth()];
            const year = d.getFullYear();
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            return `${day} ${month} ${year}, ${hours}:${minutes} hs`;
        } catch {
            return val;
        }
    }

    let methodBadge = $derived.by(() => {
        const method = (receipt?.payment_method || '').toLowerCase().trim();
        switch (method) {
            case 'efectivo':
                return {
                    label: 'Efectivo',
                    classes:
                        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                };
            case 'mercadopago':
            case 'mercado_pago':
                return {
                    label: 'Mercado Pago',
                    classes: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
                };
            case 'transferencia':
                return {
                    label: 'Transferencia',
                    classes:
                        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
                };
            case 'cheque':
                return {
                    label: 'Cheque',
                    classes: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                };
            case 'debito':
            case 'débito':
                return {
                    label: 'Débito',
                    classes:
                        'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                };
            case 'credito':
            case 'crédito':
                return {
                    label: 'Crédito',
                    classes:
                        'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20',
                };
            case 'cuenta_corriente':
                return {
                    label: 'Cuenta Corriente',
                    classes: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
                };
            default:
                return {
                    label: receipt?.payment_method || 'Otro',
                    classes: 'bg-primary/10 text-primary border-primary/20',
                };
        }
    });

    function copyReference() {
        if (!receipt?.reference_number) return;
        navigator.clipboard.writeText(receipt.reference_number);
        copiedReference = true;
        setTimeout(() => {
            copiedReference = false;
        }, 2000);
    }

    function handlePreviewPdf() {
        if (!receipt) return;
        onPreviewPdf?.(receipt);
    }

    function handleVoid() {
        if (!receipt) return;
        open = false;
        onRequestVoid?.(receipt);
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-xl sm:max-w-2xl w-[calc(100%-2rem)] mx-auto p-0 rounded-2xl sm:rounded-3xl border border-border bg-card shadow-2xl overflow-hidden">
        {#if receipt}
            <DialogHeader class="p-5 sm:p-6 pb-4 border-b border-border/70 flex flex-row items-center justify-between gap-3 bg-card shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <ReceiptText class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <DialogTitle class="text-base sm:text-lg font-bold font-sans text-foreground truncate">
                                Recibo {receipt.receipt_number}
                            </DialogTitle>
                            {#if receipt.is_cancelled}
                                <span class="bg-destructive/10 text-destructive border border-destructive/20 text-xs font-bold px-2 py-0.5 rounded-full line-through shrink-0">
                                    Anulado
                                </span>
                            {:else}
                                <span class="bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 text-xs font-semibold px-2 py-0.5 rounded-full shrink-0">
                                    Válido
                                </span>
                            {/if}
                        </div>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5 font-sans">
                            Comprobante oficial de cobro e imputación de cuenta corriente.
                        </DialogDescription>
                    </div>
                </div>

                <DialogClose asChild>
                    {#snippet children(props)}
                        <Button
                            {...props}
                            variant="ghost"
                            size="icon"
                            class="size-9 rounded-xl text-muted-foreground hover:text-foreground shrink-0 cursor-pointer"
                        >
                            <X class="size-4" />
                            <span class="sr-only">Cerrar</span>
                        </Button>
                    {/snippet}
                </DialogClose>
            </DialogHeader>

            <DialogBody class="p-5 sm:p-6 space-y-4 overflow-y-auto">
                <!-- Bloque de Monto Destacado -->
                <div class="p-4 sm:p-5 rounded-2xl bg-muted/40 border border-border/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-xs font-semibold text-muted-foreground uppercase tracking-wider block font-sans">
                            Monto Percibido
                        </span>
                        <div class="text-2xl sm:text-3xl font-bold font-sans tabular-nums text-foreground tracking-tight">
                            {formatPrice(receipt.amount)}
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class={cn('inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border uppercase tracking-wider font-sans', methodBadge.classes)}>
                            <span class="size-1.5 rounded-full bg-current"></span>
                            {methodBadge.label}
                        </span>
                    </div>
                </div>

                <!-- Grid de Metadatos -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <!-- Fecha de emisión -->
                    <div class="p-3.5 sm:p-4 rounded-xl bg-card border border-border/70 space-y-1">
                        <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-semibold">
                            <Calendar class="size-4 text-primary shrink-0" />
                            <span class="uppercase tracking-wider">Fecha de emisión</span>
                        </div>
                        <p class="text-sm sm:text-base font-semibold text-foreground tabular-nums font-sans">
                            {formatDate(receipt.created_at)}
                        </p>
                    </div>

                    <!-- Cajero / Operador -->
                    <div class="p-3.5 sm:p-4 rounded-xl bg-card border border-border/70 space-y-1">
                        <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-semibold">
                            <User class="size-4 text-primary shrink-0" />
                            <span class="uppercase tracking-wider">Cajero / Operador</span>
                        </div>
                        <p class="text-sm sm:text-base font-semibold text-foreground truncate font-sans">
                            {receipt.created_by_user?.name || 'Sistema'}
                        </p>
                    </div>

                    <!-- Cliente -->
                    <div class="p-3.5 sm:p-4 rounded-xl bg-card border border-border/70 space-y-1">
                        <div class="flex items-center gap-1.5 text-muted-foreground text-xs font-semibold">
                            <User class="size-4 text-primary shrink-0" />
                            <span class="uppercase tracking-wider">Cliente</span>
                        </div>
                        {#if receipt.customer}
                            <p class="text-sm sm:text-base font-semibold text-foreground truncate font-sans" title={receipt.customer.name}>
                                {receipt.customer.name}
                            </p>
                            {#if receipt.customer.tax_id}
                                <p class="text-xs sm:text-sm text-muted-foreground font-sans tabular-nums">
                                    CUIT/DNI: {receipt.customer.tax_id}
                                </p>
                            {/if}
                        {:else}
                            <p class="text-sm sm:text-base font-medium text-muted-foreground italic font-sans">
                                Cliente Ocasional
                            </p>
                        {/if}
                    </div>

                    <!-- Referencia / Transacción -->
                    <div class="p-3.5 sm:p-4 rounded-xl bg-card border border-border/70 space-y-1">
                        <div class="flex items-center justify-between gap-1.5 text-muted-foreground text-xs font-semibold">
                            <div class="flex items-center gap-1.5">
                                <Hash class="size-4 text-primary shrink-0" />
                                <span class="uppercase tracking-wider">Referencia / Transacción</span>
                            </div>
                            {#if receipt.reference_number}
                                <button
                                    type="button"
                                    class="size-6 rounded-md inline-flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                                    onclick={copyReference}
                                    title={copiedReference ? 'Copiado al portapapeles' : 'Copiar referencia'}
                                    aria-label="Copiar referencia al portapapeles"
                                >
                                    {#if copiedReference}
                                        <Check class="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                    {:else}
                                        <Copy class="size-3.5" />
                                    {/if}
                                </button>
                            {/if}
                        </div>
                        {#if receipt.reference_number}
                            <p class="text-xs sm:text-sm font-mono font-semibold text-foreground break-all select-all pt-0.5">
                                {receipt.reference_number}
                            </p>
                        {:else}
                            <p class="text-xs sm:text-sm text-muted-foreground italic pt-0.5 font-sans">
                                Sin referencia registrada
                            </p>
                        {/if}
                    </div>
                </div>

                <!-- Sección de Auditoría (si está anulado) -->
                {#if receipt.is_cancelled}
                    <div class="p-4 rounded-2xl bg-destructive/10 border border-destructive/25 space-y-2.5">
                        <div class="flex items-center gap-2 text-destructive font-bold text-xs sm:text-sm">
                            <Ban class="size-4 shrink-0" />
                            <span>Comprobante Anulado</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs sm:text-sm">
                            <div>
                                <span class="block text-xs font-semibold text-destructive/80">Fecha de anulación:</span>
                                <span class="font-semibold text-foreground tabular-nums font-sans">
                                    {formatDate(receipt.cancelled_at)}
                                </span>
                            </div>
                            <div>
                                <span class="block text-xs font-semibold text-destructive/80">Anulado por:</span>
                                <span class="font-semibold text-foreground font-sans">
                                    {receipt.cancelled_by_user?.name || 'Sistema'}
                                </span>
                            </div>
                        </div>
                        {#if receipt.cancellation_reason}
                            <div class="pt-2 border-t border-destructive/20 text-xs sm:text-sm">
                                <span class="block text-xs font-semibold text-destructive/80">Motivo de anulación:</span>
                                <p class="text-foreground font-medium mt-0.5 whitespace-pre-wrap leading-relaxed font-sans">
                                    {receipt.cancellation_reason}
                                </p>
                            </div>
                        {/if}
                    </div>
                {/if}

                <!-- Pedidos Imputados (si existen) -->
                {#if receipt.orders && receipt.orders.length > 0}
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs sm:text-sm">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5 font-sans">
                                <ShoppingBag class="size-4 text-primary shrink-0" />
                                <span>Pedidos Imputados</span>
                            </h3>
                            <span class="text-xs sm:text-sm font-semibold text-muted-foreground tabular-nums font-sans">
                                {receipt.orders.length} {receipt.orders.length === 1 ? 'pedido' : 'pedidos'}
                            </span>
                        </div>

                        <div class="divide-y divide-border/60 rounded-xl border border-border/70 bg-card overflow-hidden">
                            {#each receipt.orders as order (order.id)}
                                <div class="p-3 sm:p-3.5 flex items-center justify-between gap-3 text-xs sm:text-sm hover:bg-muted/20 transition-colors">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="size-2 rounded-full bg-primary shrink-0"></span>
                                        <span class="font-bold font-sans text-sm sm:text-base text-foreground tabular-nums truncate">
                                            Pedido {order.order_number}
                                        </span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm font-bold tabular-nums text-foreground font-sans">
                                            {formatPrice(order.total_amount)}
                                        </span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}

                <!-- Notas / Observaciones -->
                {#if receipt.notes && receipt.notes.trim()}
                    <div class="p-3.5 rounded-xl bg-muted/30 border border-border/60 space-y-1 text-xs">
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground block flex items-center gap-1.5">
                            <FileText class="size-3.5" />
                            <span>Notas / Observaciones</span>
                        </span>
                        <p class="text-foreground whitespace-pre-wrap leading-relaxed">
                            {receipt.notes}
                        </p>
                    </div>
                {/if}
            </DialogBody>

            <DialogFooter class="p-4 sm:p-6 border-t border-border flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-2.5 bg-card shrink-0">
                <Button
                    type="button"
                    variant="outline"
                    class="h-11 min-h-[44px] px-4 rounded-xl font-semibold text-xs sm:text-sm w-full sm:w-auto cursor-pointer"
                    onclick={() => (open = false)}
                >
                    Cerrar
                </Button>

                <div class="flex flex-col sm:flex-row items-center gap-2.5 w-full sm:w-auto">
                    <Button
                        type="button"
                        variant="secondary"
                        class="h-11 min-h-[44px] px-4 rounded-xl font-semibold text-xs sm:text-sm gap-2 w-full sm:w-auto cursor-pointer"
                        onclick={handlePreviewPdf}
                    >
                        <Printer class="size-4" />
                        <span>Ver / Imprimir PDF</span>
                    </Button>

                    {#if !receipt.is_cancelled && hasPermission('cobranzas.anular')}
                        <Button
                            type="button"
                            variant="destructive"
                            class="h-11 min-h-[44px] px-4 rounded-xl font-bold text-xs sm:text-sm gap-2 w-full sm:w-auto cursor-pointer"
                            onclick={handleVoid}
                        >
                            <Ban class="size-4" />
                            <span>Anular Recibo</span>
                        </Button>
                    {/if}
                </div>
            </DialogFooter>
        {/if}
    </DialogContent>
</Dialog>
