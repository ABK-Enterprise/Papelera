<script lang="ts">
    import { Button } from '@/components/ui/button';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Eye from 'lucide-svelte/icons/eye';
    import History from 'lucide-svelte/icons/history';
    import Package from 'lucide-svelte/icons/package';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
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

    let searchTerm = $state('');
    let selectedType = $state<'all' | 'income' | 'outcome'>('all');

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

    // Cálculos de Bento Grid
    let totalIncomes = $derived(
        movements
            .filter((m) => m.type === 'income')
            .reduce((sum, m) => sum + m.quantity, 0)
    );

    let totalOutcomes = $derived(
        movements
            .filter((m) => m.type === 'outcome')
            .reduce((sum, m) => sum + m.quantity, 0)
    );

    let netBalance = $derived(totalIncomes - totalOutcomes);

    let filteredMovements = $derived.by(() => {
        let list = movements;

        if (selectedType !== 'all') {
            list = list.filter((m) => m.type === selectedType);
        }

        if (searchTerm.trim()) {
            const q = searchTerm.toLowerCase();
            list = list.filter((m) =>
                (m.product?.name && m.product.name.toLowerCase().includes(q)) ||
                (m.product?.sku && m.product.sku.toLowerCase().includes(q)) ||
                (m.reason && m.reason.toLowerCase().includes(q)) ||
                (m.notes && m.notes.toLowerCase().includes(q))
            );
        }

        return list;
    });
</script>

<div class="space-y-6">
    <!-- Bento Grid de Métricas Clave (diseño-stitch-stock-kardex.html) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Tarjeta 1: Total Ingresos -->
        <div class="p-5 rounded-2xl bg-card border border-border/70 shadow-[0px_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between h-36">
            <div class="flex items-center justify-between">
                <div class="size-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                    <ArrowDownLeft class="size-5" />
                </div>
                <span class="text-xs font-semibold text-emerald-600 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                    Entradas
                </span>
            </div>
            <div>
                <span class="text-xs text-muted-foreground font-medium block">Total Ingresos de Stock</span>
                <span class="text-2xl font-bold text-foreground tabular-nums">+{totalIncomes} u.</span>
            </div>
        </div>

        <!-- Tarjeta 2: Total Salidas -->
        <div class="p-5 rounded-2xl bg-card border border-border/70 shadow-[0px_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between h-36">
            <div class="flex items-center justify-between">
                <div class="size-9 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center border border-rose-500/20">
                    <ArrowUpRight class="size-5" />
                </div>
                <span class="text-xs font-semibold text-rose-600 bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20">
                    Salidas
                </span>
            </div>
            <div>
                <span class="text-xs text-muted-foreground font-medium block">Total Salidas / Devoluciones</span>
                <span class="text-2xl font-bold text-foreground tabular-nums">-{totalOutcomes} u.</span>
            </div>
        </div>

        <!-- Tarjeta 3: Balance Neto -->
        <div class="p-5 rounded-2xl bg-secondary/10 dark:bg-secondary/20 border border-secondary/20 shadow-[0px_4px_20px_rgba(0,0,0,0.02)] flex flex-col justify-between h-36">
            <div class="flex items-center justify-between">
                <div class="size-9 rounded-xl bg-secondary/20 text-secondary flex items-center justify-center border border-secondary/30">
                    <RefreshCw class="size-5" />
                </div>
                <span class="text-xs font-semibold text-secondary bg-secondary/20 px-2.5 py-0.5 rounded-full border border-secondary/30">
                    Flujo de Stock
                </span>
            </div>
            <div>
                <span class="text-xs text-muted-foreground font-medium block">Balance Neto del Período</span>
                <span class="text-2xl font-bold text-foreground tabular-nums">
                    {netBalance >= 0 ? `+${netBalance}` : netBalance} u.
                </span>
            </div>
        </div>
    </div>

    <!-- Contenedor de Tabla con Toolbar Stitch -->
    <div class="bg-card text-card-foreground rounded-2xl shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/70 overflow-hidden">
        <!-- Toolbar -->
        <div class="p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3 border-b border-border/60 bg-muted/20">
            <!-- Filter Pills -->
            <div class="flex items-center gap-1.5">
                <button
                    type="button"
                    class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all {selectedType === 'all' ? 'bg-primary text-primary-foreground shadow-2xs' : 'text-muted-foreground hover:bg-muted/80'}"
                    onclick={() => (selectedType = 'all')}
                >
                    Todos (<span class="tabular-nums font-semibold">{movements.length}</span>)
                </button>
                <button
                    type="button"
                    class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all {selectedType === 'income' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-muted-foreground hover:bg-muted/80'}"
                    onclick={() => (selectedType = 'income')}
                >
                    Solo Ingresos
                </button>
                <button
                    type="button"
                    class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all {selectedType === 'outcome' ? 'bg-rose-600 text-white shadow-2xs' : 'text-muted-foreground hover:bg-muted/80'}"
                    onclick={() => (selectedType = 'outcome')}
                >
                    Solo Egresos
                </button>
            </div>

            <!-- Buscador -->
            <div class="relative w-full sm:w-72">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                <input
                    type="text"
                    bind:value={searchTerm}
                    placeholder="Buscar producto, SKU, notas..."
                    class="h-9 w-full rounded-full border border-input bg-background pl-8 pr-8 text-sm placeholder:text-muted-foreground focus:ring-1 focus:ring-primary outline-none transition-all"
                />
                {#if searchTerm}
                    <button
                        type="button"
                        onclick={() => (searchTerm = '')}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    >
                        <X class="size-3.5" />
                    </button>
                {/if}
            </div>
        </div>

        <!-- Tabla Minimalista -->
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-border/60 bg-muted/10 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        <th class="py-3.5 px-6 text-left min-w-[140px]">Fecha y hora</th>
                        <th class="py-3.5 px-6 text-left min-w-[200px]">Producto</th>
                        <th class="py-3.5 px-6 text-center min-w-[100px]">Tipo</th>
                        <th class="py-3.5 px-6 text-right min-w-[100px]">Cantidad</th>
                        <th class="py-3.5 px-6 text-right min-w-[120px]">Stock resultante</th>
                        <th class="py-3.5 px-6 text-left min-w-[140px]">Responsable</th>
                        <th class="py-3.5 px-6 text-right min-w-[110px]">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40 text-xs text-foreground">
                    {#if filteredMovements.length === 0}
                        <tr>
                            <td colspan="7" class="py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                        <History class="size-5" />
                                    </div>
                                    <span class="font-medium text-xs">No se encontraron movimientos en el historial.</span>
                                </div>
                            </td>
                        </tr>
                    {:else}
                        {#each filteredMovements as mov (mov.id)}
                            <tr class="hover:bg-muted/30 transition-colors group">
                                <!-- Fecha -->
                                <td class="py-4 px-6 text-muted-foreground whitespace-nowrap text-left">
                                    <div class="flex items-center gap-1.5 text-xs tabular-nums">
                                        <Calendar class="size-3.5 text-muted-foreground/60" />
                                        <span>{formatDate(mov.created_at)}</span>
                                    </div>
                                </td>

                                <!-- Producto con Avatar de Iniciales -->
                                <td class="py-4 px-6 text-left">
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-full {mov.type === 'income' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-700 dark:text-emerald-300' : 'bg-rose-500/15 border-rose-500/30 text-rose-700 dark:text-rose-300'} border flex items-center justify-center font-bold text-xs shrink-0">
                                            {getInitials(mov.product?.name)}
                                        </div>
                                        <div class="min-w-0">
                                             <div class="font-semibold text-foreground truncate max-w-xs leading-snug">{mov.product?.name ?? 'Producto eliminado'}</div>
                                            {#if mov.product?.sku}
                                                <div class="text-xs text-muted-foreground tabular-nums tracking-wide">SKU: {mov.product.sku}</div>
                                            {/if}
                                        </div>
                                    </div>
                                </td>

                                <!-- Tipo Badge -->
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    {#if mov.type === 'income'}
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            <ArrowDownLeft class="size-3" />
                                            Ingreso
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20">
                                            <ArrowUpRight class="size-3" />
                                            Egreso
                                        </span>
                                    {/if}
                                </td>

                                <!-- Cantidad -->
                                <td class="py-4 px-6 text-right whitespace-nowrap font-semibold text-xs tabular-nums {mov.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}">
                                    {mov.type === 'income' ? `+${mov.quantity}` : `-${mov.quantity}`} u.
                                </td>

                                <!-- Stock Resultante -->
                                <td class="py-4 px-6 text-right whitespace-nowrap tabular-nums">
                                    <span class="font-semibold text-foreground">{mov.current_stock}</span>
                                    <span class="text-[10px] text-muted-foreground block font-sans">antes: {mov.previous_stock}</span>
                                </td>

                                <!-- Responsable -->
                                <td class="py-4 px-6 text-muted-foreground whitespace-nowrap text-left">
                                    <div class="flex items-center gap-1.5">
                                        <User class="size-3 text-muted-foreground/60" />
                                        <span>{mov.user?.name ?? 'Sistema'}</span>
                                    </div>
                                </td>

                                <!-- Acciones -->
                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                    <Button
                                        variant="tertiary"
                                        size="sm"
                                        onclick={() => onSelectMovement(mov)}
                                        class="min-h-[36px] text-xs gap-1.5 rounded-lg font-medium px-3"
                                    >
                                        <Eye class="size-3.5" />
                                        <span>Ver detalle</span>
                                    </Button>
                                </td>


                            </tr>
                        {/each}
                    {/if}
                </tbody>
            </table>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-border/60 bg-muted/10 flex items-center justify-between text-xs text-muted-foreground">
            <div>Mostrando <strong class="text-foreground tabular-nums font-semibold">{filteredMovements.length}</strong> de <strong class="text-foreground tabular-nums font-semibold">{movements.length}</strong> movimientos</div>
            <div class="text-xs tabular-nums">Kardex Audit Trail</div>
        </div>
    </div>
</div>
