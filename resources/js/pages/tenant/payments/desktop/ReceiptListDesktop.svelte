<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import Ban from 'lucide-svelte/icons/ban';
    import User from 'lucide-svelte/icons/user';
    import ReceiptText from 'lucide-svelte/icons/receipt-text';
    import Calendar from 'lucide-svelte/icons/calendar';

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
        onRequestVoid = undefined,
    }: {
        receipts: ReceiptItem[];
        onRequestVoid?: (receipt: ReceiptItem) => void;
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
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(val));
    }
</script>

<Card class="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-muted/40 border-b border-border/70 text-muted-foreground uppercase font-bold text-[11px] tracking-wider">
                <tr>
                    <th class="px-4 py-3.5">Nº Recibo</th>
                    <th class="px-4 py-3.5">Fecha</th>
                    <th class="px-4 py-3.5">Cliente</th>
                    <th class="px-4 py-3.5 text-right">Monto</th>
                    <th class="px-4 py-3.5 text-center">Método</th>
                    <th class="px-4 py-3.5">Referencia</th>
                    <th class="px-4 py-3.5">Cajero</th>
                    <th class="px-4 py-3.5 text-center">Estado</th>
                    <th class="px-4 py-3.5 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/50">
                {#if receipts.length === 0}
                    <tr>
                        <td colspan="9" class="p-8 text-center text-muted-foreground text-xs">
                            No se encontraron recibos que coincidan con los criterios seleccionados.
                        </td>
                    </tr>
                {:else}
                    {#each receipts as rc}
                        <tr class="hover:bg-muted/30 transition-colors {rc.is_cancelled ? 'opacity-60 bg-muted/10' : ''}">
                            <td class="px-4 py-3 font-bold font-sans text-foreground">
                                {rc.receipt_number}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">
                                {formatDate(rc.created_at)}
                            </td>
                            <td class="px-4 py-3 max-w-[180px]">
                                {#if rc.customer}
                                    <span class="font-semibold text-foreground block truncate">{rc.customer.name}</span>
                                    {#if rc.customer.tax_id}
                                        <span class="text-[10px] text-muted-foreground block">{rc.customer.tax_id}</span>
                                    {/if}
                                {:else}
                                    <span class="text-muted-foreground italic">Cliente Ocasional</span>
                                {/if}
                            </td>
                            <td class="px-4 py-3 text-right font-bold font-sans tabular-nums text-foreground">
                                {formatPrice(rc.amount)}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block text-[10px] font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded-full border border-primary/20 uppercase">
                                    {rc.payment_method}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground font-mono text-[11px]">
                                {rc.reference_number || '—'}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {rc.created_by_user?.name || 'Sistema'}
                            </td>
                            <td class="px-4 py-3 text-center">
                                {#if rc.is_cancelled}
                                    <span
                                        title={rc.cancellation_reason ? `Motivo: ${rc.cancellation_reason}` : undefined}
                                        class="inline-block text-[10px] font-bold text-destructive bg-destructive/10 px-2 py-0.5 rounded-full border border-destructive/20 line-through"
                                    >
                                        Anulado
                                    </span>
                                {:else}
                                    <span class="inline-block text-[10px] font-semibold text-emerald-600 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                                        Válido
                                    </span>
                                {/if}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {#if !rc.is_cancelled}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 px-2.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive border-destructive/30 gap-1 rounded-lg"
                                        onclick={() => onRequestVoid?.(rc)}
                                    >
                                        <Ban class="size-3" />
                                        <span>Anular</span>
                                    </Button>
                                {:else}
                                    <span class="text-[10px] text-muted-foreground italic">Sin acciones</span>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>
</Card>
