<script lang="ts">
    import { Button } from '@/components/ui/button';
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
            <div class="rounded-xl border border-border/70 bg-card p-4 shadow-xs">
                <!-- Header Card: Producto y Tipo/Cantidad -->
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-border/40">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="size-9 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                            <Package class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-foreground truncate text-sm">
                                {mov.product?.name ?? 'Producto eliminado'}
                            </div>
                            {#if mov.product?.sku}
                                <div class="text-[11px] text-muted-foreground font-mono">
                                    SKU: {mov.product.sku}
                                </div>
                            {/if}
                        </div>
                    </div>

                    {#if mov.type === 'income'}
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 shrink-0">
                            <ArrowDownLeft class="size-3" />
                            +{mov.quantity}
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 shrink-0">
                            <ArrowUpRight class="size-3" />
                            -{mov.quantity}
                        </span>
                    {/if}
                </div>

                <!-- Detalles de Stock y Motivo -->
                <div class="grid grid-cols-2 gap-2 pt-3 text-xs">
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Balance de Stock</span>
                        <span class="font-semibold text-foreground font-mono">{mov.previous_stock} ➔ {mov.current_stock}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground block text-[11px]">Tipo / Motivo</span>
                        <span class="font-medium text-foreground capitalize">
                            {mov.type === 'income' ? 'Ingreso' : 'Egreso'}
                        </span>
                    </div>
                </div>

                <!-- Footer: Responsable y Botón Ver Detalle -->
                <div class="flex items-center justify-between pt-3 mt-3 border-t border-border/40 text-[11px] text-muted-foreground">
                    <div class="flex items-center gap-1.5 truncate">
                        <User class="size-3 text-muted-foreground/70" />
                        <span>{mov.user?.name ?? 'Sistema'}</span>
                    </div>
                    <Button
                        variant="secondary"
                        size="sm"
                        onclick={() => onSelectMovement(mov)}
                        class="h-7 text-xs px-2.5 gap-1"
                    >
                        <Eye class="size-3" />
                        <span>Ver Detalle</span>
                    </Button>
                </div>
            </div>
        {/each}
    </div>
{:else}
    <div class="rounded-xl border border-dashed border-border/80 p-8 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
        <div class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-3">
            <History class="size-6" />
        </div>
        <h3 class="text-base font-bold text-foreground">El Kardex está vacío</h3>
        <p class="mt-1 text-xs text-muted-foreground">
            Los movimientos de ingresos y egresos de stock se registrarán aquí automáticamente para auditoría.
        </p>
    </div>
{/if}
