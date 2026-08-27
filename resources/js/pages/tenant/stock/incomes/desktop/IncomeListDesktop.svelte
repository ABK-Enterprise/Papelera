<script lang="ts">
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import Calendar from 'lucide-svelte/icons/calendar';
    import FileText from 'lucide-svelte/icons/file-text';
    import Filter from 'lucide-svelte/icons/filter';
    import Package from 'lucide-svelte/icons/package';
    import Search from 'lucide-svelte/icons/search';
    import Tag from 'lucide-svelte/icons/tag';
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

    function formatReason(reason: string): string {
        switch (reason) {
            case 'compra_proveedor':
            case 'orden_compra_proveedor':
                return 'Orden de Compra / Proveedor';
            case 'ajuste_positivo':
                return 'Ajuste de Inventario (+)';
            case 'cancelacion_pedido':
                return 'Cancelación de Pedido';
            case 'devolucion_cliente':
                return 'Devolución de Cliente';
            default:
                return reason ? reason.replace(/_/g, ' ') : 'Ingreso Manual';
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

<div class="space-y-4">
    <!-- Toolbar de Filtros y Búsqueda -->
    <div class="p-4 rounded-2xl border border-border/70 bg-card shadow-[0px_10px_30px_rgba(0,0,0,0.02)] flex items-center justify-between gap-3">
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
        <div class="relative w-72">
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

    <!-- Lista de Wide Cards (Jerarquía Visual de Elementos) -->
    <div class="flex flex-col gap-3">
        {#each filteredMovements as mov (mov.id)}
            <div class="bento-card group flex items-center justify-between gap-6 rounded-2xl border border-border/70 bg-card p-4.5 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] transition-all duration-200 hover:border-primary/40 hover:shadow-md hover:bg-muted/5">
                <!-- Lado Izquierdo: Icon Avatar + Datos Principales -->
                <div class="flex items-center gap-4.5 min-w-0 flex-1">
                    <!-- Icon Avatar Badge -->
                    <div class="size-13 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 flex items-center justify-center font-bold text-sm font-mono shrink-0 shadow-2xs transition-transform group-hover:scale-105">
                        {getInitials(mov.product?.name)}
                    </div>

                    <!-- Jerarquía de Información -->
                    <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                        <!-- Fila 1: Nombre Destacado + SKU + Motivo -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <h3 class="font-bold text-base text-foreground group-hover:text-primary transition-colors truncate max-w-md">
                                {mov.product?.name ?? 'Producto eliminado'}
                            </h3>

                            {#if mov.product?.sku}
                                <span class="inline-flex items-center rounded-md bg-muted/60 px-2 py-0.5 text-[11px] font-mono text-muted-foreground border border-border/50">
                                    SKU: {mov.product.sku}
                                </span>
                            {/if}

                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/50 capitalize">
                                <Tag class="size-3" />
                                {formatReason(mov.reason)}
                            </span>
                        </div>

                        <!-- Fila 2: Notas y Detalles -->
                        {#if mov.notes}
                            <p class="text-xs text-muted-foreground line-clamp-1 flex items-center gap-1.5">
                                <FileText class="size-3 text-muted-foreground/60 shrink-0" />
                                <span>{mov.notes}</span>
                            </p>
                        {/if}

                        <!-- Fila 3: Metadatos (Responsable y Fecha) -->
                        <div class="flex items-center gap-4 text-xs text-muted-foreground mt-0.5">
                            <div class="flex items-center gap-1.5">
                                <User class="size-3.5 text-muted-foreground/70" />
                                <span>Registrado por: <strong class="text-foreground font-normal">{mov.user?.name ?? 'Sistema'}</strong></span>
                            </div>
                            <span class="text-border">•</span>
                            <div class="flex items-center gap-1.5 font-mono text-[11px]">
                                <Calendar class="size-3.5 text-muted-foreground/70" />
                                <span>{formatDate(mov.created_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lado Derecho: Variación de Unidades + Progresión de Stock -->
                <div class="flex items-center gap-4 shrink-0 pl-4 border-l border-border/60">
                    <div class="text-right flex flex-col items-end gap-1">
                        <!-- Badge de Cantidad Ingresada -->
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-mono shadow-2xs">
                            <ArrowDownLeft class="size-3.5" />
                            +{mov.quantity} u.
                        </span>

                        <!-- Progresión de Stock (Antes -> Después) -->
                        <div class="flex items-center gap-1 text-xs font-mono text-muted-foreground">
                            <span>Stock:</span>
                            <span class="text-muted-foreground font-semibold">{mov.previous_stock}</span>
                            <ArrowRight class="size-3 text-muted-foreground/60" />
                            <span class="font-bold text-foreground text-sm">{mov.current_stock}</span>
                        </div>
                    </div>
                </div>
            </div>
        {/each}

        {#if filteredMovements.length === 0}
            <EmptyState
                icon={ArrowDownLeft}
                title="No se encontraron ingresos de stock"
                description="No hay registros de ingreso que coincidan con los filtros aplicados en este período."
            />
        {/if}
    </div>

    <!-- Footer Informativo -->
    <div class="flex items-center justify-between text-xs text-muted-foreground px-2">
        <span>Mostrando <strong>{filteredMovements.length}</strong> de <strong>{movements.length}</strong> movimientos de ingreso</span>
        <span class="font-mono text-[11px]">Kardex Incomes</span>
    </div>
</div>
