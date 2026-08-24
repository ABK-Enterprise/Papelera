<script lang="ts">
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Filter from 'lucide-svelte/icons/filter';
    import Package from 'lucide-svelte/icons/package';
    import Search from 'lucide-svelte/icons/search';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';

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

    let searchTerm = $state('');
    let selectedPeriod = $state<'all' | 'week' | 'month'>('all');

    function getInitials(name?: string): string {
        if (!name) return 'PR';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.slice(0, 2).toUpperCase();
    }

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

    let filteredMovements = $derived.by(() => {
        let list = movements;

        if (searchTerm.trim()) {
            const q = searchTerm.toLowerCase();
            list = list.filter((m) =>
                (m.product?.name && m.product.name.toLowerCase().includes(q)) ||
                (m.product?.sku && m.product.sku.toLowerCase().includes(q)) ||
                (m.reason && m.reason.toLowerCase().includes(q)) ||
                (m.notes && m.notes.toLowerCase().includes(q))
            );
        }

        if (selectedPeriod !== 'all') {
            const now = new Date();
            if (selectedPeriod === 'week') {
                const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                list = list.filter((m) => new Date(m.created_at) >= oneWeekAgo);
            } else if (selectedPeriod === 'month') {
                const oneMonthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                list = list.filter((m) => new Date(m.created_at) >= oneMonthAgo);
            }
        }

        return list;
    });
</script>

<div class="bg-card text-card-foreground rounded-2xl shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/70 overflow-hidden">
    <!-- Toolbar de Filtros y Búsqueda Stitch Layout -->
    <div class="p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3 border-b border-border/60 bg-muted/20">
        <!-- Pills de Filtro Rápido -->
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all {selectedPeriod === 'all' ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-muted/80'}"
                onclick={() => (selectedPeriod = 'all')}
            >
                Todos ({movements.length})
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all {selectedPeriod === 'week' ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-muted/80'}"
                onclick={() => (selectedPeriod = 'week')}
            >
                Esta semana
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all {selectedPeriod === 'month' ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-muted/80'}"
                onclick={() => (selectedPeriod = 'month')}
            >
                Mes actual
            </button>
        </div>

        <!-- Buscador integrado -->
        <div class="relative w-full sm:w-72">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            <input
                type="text"
                bind:value={searchTerm}
                placeholder="Buscar por producto, notas..."
                class="h-9 w-full rounded-full border border-input bg-background pl-8 pr-8 text-xs placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none transition-all"
            />
            {#if searchTerm}
                <button
                    type="button"
                    onclick={() => (searchTerm = '')}
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X class="size-3" />
                </button>
            {/if}
        </div>
    </div>

    <!-- Tabla Minimalista -->
    <div class="w-full overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-border/60 bg-muted/10 text-[11px] font-bold text-muted-foreground uppercase tracking-wider">
                    <th class="py-3.5 px-6">Fecha / Hora</th>
                    <th class="py-3.5 px-6">Producto</th>
                    <th class="py-3.5 px-6 text-center">Cantidad</th>
                    <th class="py-3.5 px-6 text-center">Stock Resultante</th>
                    <th class="py-3.5 px-6">Motivo</th>
                    <th class="py-3.5 px-6">Responsable</th>
                    <th class="py-3.5 px-6">Notas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/40 text-xs text-foreground">
                {#if filteredMovements.length === 0}
                    <tr>
                        <td colspan="7" class="py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <div class="size-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                                    <ArrowDownLeft class="size-5" />
                                </div>
                                <span class="font-medium text-xs">No se encontraron movimientos de ingreso.</span>
                            </div>
                        </td>
                    </tr>
                {:else}
                    {#each filteredMovements as mov (mov.id)}
                        <tr class="hover:bg-muted/30 transition-colors group">
                            <!-- Fecha -->
                            <td class="py-4 px-6 text-muted-foreground whitespace-nowrap">
                                <div class="flex items-center gap-1.5 font-mono text-[11px]">
                                    <Calendar class="size-3.5 text-muted-foreground/60" />
                                    <span>{formatDate(mov.created_at)}</span>
                                </div>
                            </td>

                            <!-- Producto con Avatar de Iniciales -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-700 dark:text-emerald-300 font-bold text-[11px] shrink-0">
                                        {getInitials(mov.product?.name)}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-foreground truncate max-w-xs">{mov.product?.name ?? 'Producto eliminado'}</div>
                                        {#if mov.product?.sku}
                                            <div class="text-[10px] text-muted-foreground font-mono">SKU: {mov.product.sku}</div>
                                        {/if}
                                    </div>
                                </div>
                            </td>

                            <!-- Cantidad con Badge Verde -->
                            <td class="py-4 px-6 text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-mono">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    +{mov.quantity} u.
                                </span>
                            </td>

                            <!-- Stock Resultante -->
                            <td class="py-4 px-6 text-center whitespace-nowrap font-mono">
                                <span class="font-bold text-foreground">{mov.current_stock}</span>
                                <span class="text-[10px] text-muted-foreground block font-sans">antes: {mov.previous_stock}</span>
                            </td>

                            <!-- Motivo -->
                            <td class="py-4 px-6 font-medium capitalize">
                                {mov.reason ? mov.reason.replace(/_/g, ' ') : 'Ingreso manual'}
                            </td>

                            <!-- Responsable -->
                            <td class="py-4 px-6 text-muted-foreground whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <User class="size-3 text-muted-foreground/60" />
                                    <span>{mov.user?.name ?? 'Sistema'}</span>
                                </div>
                            </td>

                            <!-- Notas -->
                            <td class="py-4 px-6 text-muted-foreground max-w-xs truncate">
                                {mov.notes ?? '-'}
                            </td>
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>

    <!-- Footer de Paginación / Conteo -->
    <div class="p-4 border-t border-border/60 bg-muted/10 flex items-center justify-between text-xs text-muted-foreground">
        <div>Mostrando {filteredMovements.length} de {movements.length} ingresos</div>
        <div class="text-[11px] font-mono">Control de Stock</div>
    </div>
</div>
