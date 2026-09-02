<script lang="ts">
    import { Button } from '@/components/ui/button';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import History from 'lucide-svelte/icons/history';
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
            email?: string;
        } | null;
    }

    let {
        movements = [],
        onSelectMovement,
    }: {
        movements: StockMovementItem[];
        onSelectMovement: (movement: StockMovementItem) => void;
    } = $props();

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
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-xs transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-accent/25">

                <!-- Header Card Mobile: Producto y Badge de Operación -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-border/40">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="size-9 rounded-lg {mov.type === 'income' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-600 dark:text-rose-400'} border flex items-center justify-center shrink-0">
                            {#if mov.type === 'income'}
                                <ArrowDownLeft class="size-4" />
                            {:else}
                                <ArrowUpRight class="size-4" />
                            {/if}
                        </div>
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate text-sm leading-snug">
                                {mov.product?.name ?? 'Producto eliminado'}
                            </div>
                            {#if mov.product?.sku}
                                <div class="text-xs text-muted-foreground tabular-nums tracking-wide">
                                    SKU: {mov.product.sku}
                                </div>
                            {/if}
                        </div>
                    </div>

                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold tabular-nums shrink-0 {mov.type === 'income' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'}">
                        {mov.type === 'income' ? `+${mov.quantity}` : `-${mov.quantity}`}
                    </span>
                </div>

                <!-- Detalle de Balance y Motivo -->
                <div class="grid grid-cols-2 gap-2 pt-3 text-xs">
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Balance stock</span>
                        <div class="text-xs tabular-nums">
                            <span class="text-muted-foreground">{mov.previous_stock}</span>
                            <span class="text-muted-foreground mx-0.5">➔</span>
                            <span class="font-bold text-foreground">{mov.current_stock}</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Motivo</span>
                        <span class="font-medium text-foreground capitalize truncate block">
                            {mov.reason ? mov.reason.replace(/_/g, ' ') : 'Operación'}
                        </span>
                    </div>
                </div>

                <!-- Footer: Responsable, Fecha y Botón Ver Detalle -->
                <div class="flex items-center justify-between pt-3 mt-3 border-t border-border/40 text-[11px] text-muted-foreground">
                    <div class="flex flex-col gap-0.5 min-w-0">
                        <div class="flex items-center gap-1 text-foreground font-medium truncate">
                            <User class="size-3 text-muted-foreground/70" />
                            <span>{mov.user?.name ?? 'Sistema'}</span>
                        </div>
                        <div class="flex items-center gap-1 text-[10px] text-muted-foreground tabular-nums">
                            <Calendar class="size-3 text-muted-foreground/70" />
                            <span>{formatDate(mov.created_at)}</span>
                        </div>
                    </div>

                    <Button
                        variant="tertiary"
                        size="sm"
                        onclick={() => onSelectMovement(mov)}
                        class="min-h-[44px] text-xs px-3.5 rounded-xl shrink-0 font-medium"
                    >
                        <Eye class="size-3.5 mr-1.5" />
                        <span>Ver detalle</span>
                    </Button>



                </div>
            </div>
        {/each}
    </div>
{:else}
    <EmptyState
        icon={History}
        title="El Kardex está vacío"
        description="Los movimientos de ingresos y egresos de stock se registrarán aquí automáticamente para auditoría."
    />
{/if}
