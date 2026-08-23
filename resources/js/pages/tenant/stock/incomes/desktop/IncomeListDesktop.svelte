<script lang="ts">
    import MinimalDataTable from '@/components/common/MinimalDataTable.svelte';
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

<MinimalDataTable items={movements}>
    {#snippet headers()}
        <th class="py-3.5 px-6">Fecha / Hora</th>
        <th class="py-3.5 px-6">Producto</th>
        <th class="py-3.5 px-6 text-center">Cantidad</th>
        <th class="py-3.5 px-6 text-center">Stock Resultante</th>
        <th class="py-3.5 px-6">Motivo</th>
        <th class="py-3.5 px-6">Responsable</th>
        <th class="py-3.5 px-6">Notas</th>
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
                <div class="size-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
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

        <!-- Cantidad -->
        <td class="py-4 px-6 text-center whitespace-nowrap">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                <ArrowDownLeft class="size-3.5" />
                +{mov.quantity}
            </span>
        </td>

        <!-- Stock Resultante -->
        <td class="py-4 px-6 text-center whitespace-nowrap">
            <div class="text-sm font-semibold text-foreground font-mono">{mov.current_stock}</div>
            <div class="text-[11px] text-muted-foreground">antes: {mov.previous_stock}</div>
        </td>

        <!-- Motivo -->
        <td class="py-4 px-6 text-xs text-foreground font-medium">
            <span class="capitalize">{mov.reason ? mov.reason.replace(/_/g, ' ') : 'Ingreso manual'}</span>
        </td>

        <!-- Responsable -->
        <td class="py-4 px-6 text-xs text-muted-foreground whitespace-nowrap">
            <div class="flex items-center gap-1.5">
                <User class="size-3.5 text-muted-foreground/70" />
                <span class="font-medium text-foreground">{mov.user?.name ?? 'Sistema'}</span>
            </div>
        </td>

        <!-- Notas -->
        <td class="py-4 px-6 text-xs text-muted-foreground max-w-xs truncate">
            {mov.notes ?? '-'}
        </td>
    {/snippet}

    {#snippet empty()}
        <div class="rounded-xl border border-dashed border-border/80 p-12 text-center bg-card/40 flex flex-col items-center justify-center shadow-xs">
            <div class="flex size-14 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 mb-4">
                <ArrowDownLeft class="size-7" />
            </div>
            <h3 class="text-lg font-bold text-foreground">No hay ingresos registrados</h3>
            <p class="mt-1.5 text-sm text-muted-foreground max-w-md">
                Registra el primer ingreso de stock para añadir unidades a tu catálogo.
            </p>
        </div>
    {/snippet}
</MinimalDataTable>
