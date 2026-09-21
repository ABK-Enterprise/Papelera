<script lang="ts">
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Package from 'lucide-svelte/icons/package';
    import User from 'lucide-svelte/icons/user';
    import Building2 from 'lucide-svelte/icons/building-2';

    interface StockMovementItem {
        id: number;
        type: string;
        reason: string;
        quantity: number;
        previous_stock: number;
        current_stock: number;
        notes: string | null;
        created_at: string;
        warehouse?: {
            id: number;
            name: string;
            code?: string | null;
        } | null;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            barcode: string | null;
        } | null;
        user?: {
            id: number;
            name: string;
        } | null;
    }

    let { movements = [] }: { movements: StockMovementItem[] } = $props();

    function formatDate(dateStr: string): string {
        if (!dateStr) return '-';
        try {
            const date = new Date(dateStr);
            return date.toLocaleDateString('es-AR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return dateStr;
        }
    }
</script>

{#if movements.length > 0}
    <div class="flex flex-col gap-3">
        {#each movements as mov (mov.id)}
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20">

                <!-- Header de Card Mobile: Producto y Cantidad -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-border/40">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="size-9 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-600 dark:text-rose-400 shrink-0">
                            <Package class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate text-sm leading-snug">
                                {mov.product?.name ?? 'Producto eliminado'}
                            </div>
                            <div class="flex items-center gap-2 flex-wrap mt-0.5">
                                {#if mov.product?.sku}
                                    <span class="text-xs text-muted-foreground tracking-wide tabular-nums">
                                        SKU: {mov.product.sku}
                                    </span>
                                {/if}
                                {#if mov.warehouse}
                                    <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-muted/80 text-foreground border border-border/70 shadow-2xs">
                                        <Building2 class="size-2.5 text-muted-foreground shrink-0" />
                                        {mov.warehouse.name}
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 shrink-0 tabular-nums">
                        <ArrowUpRight class="size-3" />
                        -{mov.quantity}
                    </span>
                </div>

                <!-- Detalles de Stock y Motivo -->
                <div class="grid grid-cols-2 gap-2 pt-3 text-xs">
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Stock resultante</span>
                        <span class="font-semibold text-foreground tabular-nums">{mov.current_stock}</span>
                        <span class="text-muted-foreground text-[10px] tabular-nums"> (previo: {mov.previous_stock})</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Motivo</span>
                        <span class={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                            mov.reason === 'merma_devolucion_cliente'
                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30'
                                : 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                        }`}>
                            {mov.reason === 'merma_devolucion_cliente'
                                ? 'Merma (Devolución Cliente)'
                                : mov.reason === 'devolucion_cliente'
                                  ? 'Devolución'
                                  : mov.reason.replace(/_/g, ' ')}
                        </span>
                    </div>
                </div>

                <!-- Footer: Responsable y Fecha -->
                <div class="flex items-center justify-between pt-3 mt-3 border-t border-border/40 text-[11px] text-muted-foreground">
                    <div class="flex items-center gap-1.5 truncate">
                        <User class="size-3 text-muted-foreground/70" />
                        <span>{mov.user?.name ?? 'Sistema'}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0 tabular-nums">
                        <Calendar class="size-3 text-muted-foreground/70" />
                        <span>{formatDate(mov.created_at)}</span>
                    </div>
                </div>

                {#if mov.notes}
                    <div class="mt-2 text-[11px] text-muted-foreground bg-muted/40 p-2 rounded-lg">
                        <span class="font-medium text-foreground">Nota:</span> {mov.notes}
                    </div>
                {/if}
            </div>
        {/each}
    </div>
{:else}
    <EmptyState
        icon={ArrowUpRight}
        title="No hay egresos registrados"
        description="No se han registrado devoluciones ni salidas de stock en este período."
    />
{/if}
