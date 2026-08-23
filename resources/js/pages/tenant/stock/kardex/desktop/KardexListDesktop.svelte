<script lang="ts">
    import MinimalDataTable from '@/components/common/MinimalDataTable.svelte';
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

<MinimalDataTable items={movements}>
    {#snippet headers()}
        <th class="py-3.5 px-6">Fecha / Hora</th>
        <th class="py-3.5 px-6">Producto</th>
        <th class="py-3.5 px-6 text-center">Tipo</th>
        <th class="py-3.5 px-6 text-center">Cantidad</th>
        <th class="py-3.5 px-6 text-center">Stock Resultante</th>
        <th class="py-3.5 px-6">Responsable</th>
        <th class="py-3.5 px-6 text-right">Acciones</th>
    {/snippet}

    {#snippet row(mov)}
        <!-- Fecha -->
        <td class="py-4 px-6 text-xs text-muted-foreground whitespace-nowrap">
            <div class="flex items-center gap-1.5">
                <Calendar class="size-3.5 text-muted-foreground/70" />
                <span>{formatDate(mov.created_at)}</span>
            </div>
        </td>

        <!-- Producto -->
        <td class="py-4 px-6">
            <div class="flex items-center gap-2.5">
                <div class="size-8 rounded-lg bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0">
                    <Package class="size-4" />
                </div>
                <div>
                    <div class="font-semibold text-foreground">{mov.product?.name ?? 'Producto eliminado'}</div>
                    {#if mov.product?.sku}
                        <div class="text-xs text-muted-foreground font-mono">SKU: {mov.product.sku}</div>
                    {/if}
                </div>
            </div>
        </td>

        <!-- Tipo -->
        <td class="py-4 px-6 text-center whitespace-nowrap">
            {#if mov.type === 'income'}
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <ArrowDownLeft class="size-3" />
                    Ingreso
                </span>
            {:else}
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                    <ArrowUpRight class="size-3" />
                    Egreso
                </span>
            {/if}
        </td>

        <!-- Cantidad -->
        <td class="py-4 px-6 text-center whitespace-nowrap font-bold text-xs {mov.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}">
            {mov.type === 'income' ? `+${mov.quantity}` : `-${mov.quantity}`}
        </td>

        <!-- Stock Resultante -->
        <td class="py-4 px-6 text-center whitespace-nowrap">
            <div class="text-sm font-semibold text-foreground font-mono">{mov.current_stock}</div>
            <div class="text-[11px] text-muted-foreground">antes: {mov.previous_stock}</div>
        </td>

        <!-- Responsable -->
        <td class="py-4 px-6 text-xs text-muted-foreground whitespace-nowrap">
            <div class="flex items-center gap-1.5">
                <User class="size-3.5 text-muted-foreground/70" />
                <span class="font-medium text-foreground">{mov.user?.name ?? 'Sistema'}</span>
            </div>
        </td>

        <!-- Acciones -->
        <td class="py-4 px-6 text-right whitespace-nowrap">
            <Button
                variant="outline"
                size="sm"
                onclick={() => onSelectMovement(mov)}
                class="h-8 text-xs gap-1.5"
            >
                <Eye class="size-3.5" />
                <span>Ver Detalle</span>
            </Button>
        </td>
    {/snippet}

    {#snippet empty()}
        <div class="rounded-xl border border-dashed border-border/80 p-12 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-4">
                <History class="size-7" />
            </div>
            <h3 class="text-lg font-bold text-foreground">El Kardex está vacío</h3>
            <p class="mt-1.5 text-sm text-muted-foreground max-w-md">
                Los movimientos de ingresos y egresos de stock se registrarán aquí automáticamente para auditoría.
            </p>
        </div>
    {/snippet}
</MinimalDataTable>
