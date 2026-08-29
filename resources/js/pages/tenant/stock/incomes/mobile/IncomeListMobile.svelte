<script lang="ts">
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Package from 'lucide-svelte/icons/package';
    import User from 'lucide-svelte/icons/user';

    interface StockMovementItem {
        id: number;
        type: string;
        reason: string;
        quantity: number;
        previous_stock: number;
        current_stock: number;
        notes: string | null;
        created_at: string;
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
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-xs">
                <!-- Header de Card Mobile: Producto y Cantidad -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-border/40">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="size-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                            <Package class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate text-sm leading-snug">
                                {mov.product?.name ?? 'Producto eliminado'}
                            </div>
                            {#if mov.product?.sku}
                                <div class="text-xs text-muted-foreground tracking-wide tabular-nums">
                                    SKU: {mov.product.sku}
                                </div>
                            {/if}
                        </div>
                    </div>

                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0 tabular-nums">
                        <ArrowDownLeft class="size-3" />
                        +{mov.quantity}
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
                        <span class="font-medium text-foreground capitalize">
                            {mov.reason ? mov.reason.replace(/_/g, ' ') : 'Ingreso manual'}
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
        icon={ArrowDownLeft}
        title="No hay ingresos registrados"
        description="Registra el primer ingreso de stock para añadir unidades a tu catálogo."
    />
{/if}
