<script lang="ts">
    import {
        DropdownMenu,
        DropdownMenuContent,
        DropdownMenuItem,
        DropdownMenuSeparator,
        DropdownMenuTrigger,
    } from '@/components/ui/dropdown-menu';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import MoreVertical from 'lucide-svelte/icons/more-vertical';
    import Eye from 'lucide-svelte/icons/eye';
    import Printer from 'lucide-svelte/icons/printer';
    import Ban from 'lucide-svelte/icons/ban';

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

<div class="flex flex-col gap-3">
    {#if receipts.length === 0}
        <div class="p-8 rounded-2xl border border-border/70 bg-card text-center text-sm text-muted-foreground shadow-xs">
            No se encontraron recibos que coincidan con los criterios seleccionados.
        </div>
    {:else}
        {#each receipts as rc (rc.id)}
            <div
                class="flex items-center justify-between p-4.5 rounded-2xl border border-border/70 bg-card shadow-xs hover:border-border transition-all duration-200 gap-4 select-none {rc.is_cancelled ? 'opacity-70 bg-muted/10' : ''}"
            >
                <!-- Lado Izquierdo: Icono temático + Nº de Recibo y Método -->
                <div class="flex items-center gap-3.5 min-w-[200px] shrink-0">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <ReceiptText class="size-5 text-primary" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="font-bold text-base text-foreground tabular-nums font-sans">
                            {rc.receipt_number}
                        </span>
                        <div>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full border border-primary/20 bg-primary/10 text-primary uppercase inline-block">
                                {rc.payment_method}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cliente: Nombre y CUIT -->
                <div class="flex flex-col min-w-[190px] max-w-[250px]">
                    {#if rc.customer}
                        <span class="font-semibold text-sm sm:text-base text-foreground leading-snug truncate max-w-[210px] sm:max-w-[250px] block font-sans" title={rc.customer.name}>
                            {rc.customer.name}
                        </span>
                        {#if rc.customer.tax_id}
                            <span class="text-xs text-muted-foreground block tabular-nums font-sans">
                                {rc.customer.tax_id}
                            </span>
                        {/if}
                    {:else}
                        <span class="text-xs font-medium text-muted-foreground italic">
                            Cliente Ocasional
                        </span>
                    {/if}
                </div>

                <!-- Pedidos Imputados (Hasta 3 en 2 filas + indicador '+ X') / Referencia -->
                <div class="min-w-[170px] max-w-[220px] flex flex-col gap-1">
                    {#if rc.orders && rc.orders.length > 0}
                        <!-- Fila 1: Primeros 2 pedidos -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            {#each rc.orders.slice(0, 2) as ord (ord.id)}
                                <span class="text-xs font-semibold font-sans tabular-nums px-2 py-0.5 rounded-md bg-muted/70 text-foreground border border-border/70 inline-block shadow-2xs">
                                    {ord.order_number}
                                </span>
                            {/each}
                        </div>

                        <!-- Fila 2: 3er pedido + indicador '+ X' si hay más -->
                        {#if rc.orders.length > 2}
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-xs font-semibold font-sans tabular-nums px-2 py-0.5 rounded-md bg-muted/70 text-foreground border border-border/70 inline-block shadow-2xs">
                                    {rc.orders[2].order_number}
                                </span>
                                {#if rc.orders.length > 3}
                                    <span
                                        class="size-5 inline-flex items-center justify-center rounded-full bg-primary/10 text-primary border border-primary/25 text-xs font-bold font-sans tabular-nums shrink-0 cursor-default"
                                        title={rc.orders.slice(3).map((o) => o.order_number).join(', ')}
                                    >
                                        +{rc.orders.length - 3}
                                    </span>
                                {/if}
                            </div>
                        {/if}

                        <!-- Referencia complementaria opcional si existe -->
                        {#if rc.reference_number}
                            <span class="text-[11px] font-mono text-muted-foreground truncate" title={rc.reference_number}>
                                Ref: {truncateRef(rc.reference_number, 12)}
                            </span>
                        {/if}
                    {:else if rc.reference_number}
                        <span
                            class="text-xs font-mono text-muted-foreground bg-muted/50 px-2 py-1 rounded-lg border border-border/50 max-w-[150px] truncate inline-block"
                            title={rc.reference_number}
                        >
                            {truncateRef(rc.reference_number, 14)}
                        </span>
                    {:else}
                        <span class="text-xs font-medium text-muted-foreground/60">—</span>
                    {/if}
                </div>

                <!-- Estado: Badge Válido / Anulado -->
                <div class="min-w-[95px] flex items-center justify-center">
                    {#if rc.is_cancelled}
                        <span
                            title={rc.cancellation_reason ? `Motivo: ${rc.cancellation_reason}` : undefined}
                            class="bg-destructive/10 text-destructive border border-destructive/20 text-xs font-semibold px-2.5 py-1 rounded-full line-through uppercase tracking-wider font-sans"
                        >
                            Anulado
                        </span>
                    {:else}
                        <span class="bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider font-sans">
                            Válido
                        </span>
                    {/if}
                </div>

                <!-- Lado Derecho: Monto y Menú '...' -->
                <div class="flex items-center gap-4 shrink-0">
                    <div class="text-right">
                        <span class="font-bold text-base sm:text-lg text-foreground tabular-nums text-right font-sans block">
                            {formatPrice(rc.amount)}
                        </span>
                    </div>

                    <!-- Menú Desplegable '...' -->
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            {#snippet children(props)}
                                <button
                                    {...props}
                                    type="button"
                                    class="size-10 min-h-[40px] min-w-[40px] rounded-xl border border-border/70 text-muted-foreground hover:text-foreground hover:bg-muted/60 transition-colors shadow-2xs flex items-center justify-center cursor-pointer focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-hidden"
                                    aria-label={`Opciones para recibo ${rc.receipt_number}`}
                                >
                                    <MoreVertical class="size-4" />
                                </button>
                            {/snippet}
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48 shadow-lg">
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-xs font-semibold cursor-pointer py-2.5 px-3"
                                onclick={() => onViewDetail?.(rc)}
                            >
                                <Eye class="size-4 text-muted-foreground" />
                                <span>Ver detalle</span>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="flex items-center gap-2 text-xs font-semibold cursor-pointer py-2.5 px-3"
                                onclick={() => onPreviewPdf?.(rc)}
                            >
                                <Printer class="size-4 text-muted-foreground" />
                                <span>Ver e Imprimir PDF</span>
                            </DropdownMenuItem>
                            {#if !rc.is_cancelled}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    class="flex items-center gap-2 text-xs font-semibold text-destructive focus:text-destructive focus:bg-destructive/10 hover:text-destructive hover:bg-destructive/10 cursor-pointer py-2.5 px-3"
                                    onclick={() => onRequestVoid?.(rc)}
                                >
                                    <Ban class="size-4 text-destructive" />
                                    <span>Anular recibo</span>
                                </DropdownMenuItem>
                            {/if}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        {/each}
    {/if}
</div>
