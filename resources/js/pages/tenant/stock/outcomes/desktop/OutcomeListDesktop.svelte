<script lang="ts">
    import EmptyState from '@/components/common/EmptyState.svelte';
    import AppPagination, { type PaginationMeta } from '@/components/common/AppPagination.svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Calendar from 'lucide-svelte/icons/calendar';
    import FileText from 'lucide-svelte/icons/file-text';
    import Package from 'lucide-svelte/icons/package';
    import Search from 'lucide-svelte/icons/search';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import Tag from 'lucide-svelte/icons/tag';
    import User from 'lucide-svelte/icons/user';
    import X from 'lucide-svelte/icons/x';
    import Building2 from 'lucide-svelte/icons/building-2';
    import { formatDateTime } from '@/lib/utils';

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

    let {
        movements = [],
        pagination = null,
    }: {
        movements: StockMovementItem[];
        pagination?: PaginationMeta | null;
    } = $props();

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
            case 'pedido_cliente':
                return 'Despacho de Pedido';
            case 'ajuste_negativo':
                return 'Ajuste de inventario';
            case 'merma_rotura':
                return 'Merma / Rotura';
            case 'vencimiento':
                return 'Mercadería Vencida';
            case 'uso_interno':
                return 'Consumo / Uso Interno';
            case 'merma_devolucion_cliente':
                return 'Merma (Devolución Cliente)';
            default:
                return reason ? reason.replace(/_/g, ' ') : 'Egreso Manual';
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
    <div class="p-4 rounded-2xl border border-border/80 dark:border-border/90 bg-card shadow-[0px_10px_30px_rgba(0,0,0,0.02)] flex items-center justify-between gap-3">
        <!-- Pills de Filtro Rápido -->
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-full text-sm font-medium transition-all {selectedPeriod === 'all' ? 'bg-rose-700 text-white shadow-2xs' : 'text-muted-foreground hover:bg-muted/80 dark:hover:bg-secondary/60 hover:text-foreground'}"
                onclick={() => (selectedPeriod = 'all')}
            >
                Todos ({pagination ? pagination.total : movements.length})
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-full text-sm font-medium transition-all {selectedPeriod === 'week' ? 'bg-rose-700 text-white shadow-2xs' : 'text-muted-foreground hover:bg-muted/80 dark:hover:bg-secondary/60 hover:text-foreground'}"
                onclick={() => (selectedPeriod = 'week')}
            >
                Esta semana
            </button>
            <button
                type="button"
                class="px-3.5 py-1.5 rounded-full text-sm font-medium transition-all {selectedPeriod === 'month' ? 'bg-rose-700 text-white shadow-2xs' : 'text-muted-foreground hover:bg-muted/80 dark:hover:bg-secondary/60 hover:text-foreground'}"
                onclick={() => (selectedPeriod = 'month')}
            >
                Mes actual
            </button>
        </div>

        <!-- Buscador integrado con SearchCombobox -->
        <div class="w-80">
            <SearchCombobox
                bind:value={searchTerm}
                items={movements}
                filterLocally={true}
                debounceMs={0}
                placeholder="Buscar egresos, mermas, notas..."
                onsearch={(q) => (searchTerm = q)}
                onselect={(mov) => (searchTerm = mov.product?.name ?? '')}
                onclear={() => (searchTerm = '')}
                inputClass="h-9 text-xs"
            >
                {#snippet itemSnippet(mov)}
                    <div class="flex items-center justify-between gap-3 w-full min-w-0">
                        <div class="flex flex-col min-w-0">
                            <span class="truncate font-medium text-foreground">{mov.product?.name ?? 'Sin producto'}</span>
                            <span class="text-xs text-muted-foreground tabular-nums">{formatReason(mov.reason)} · {formatDateTime(mov.created_at)}</span>
                        </div>
                        <span class="tabular-nums font-bold text-rose-700 dark:text-rose-400 text-xs shrink-0">
                            -{mov.quantity}
                        </span>
                    </div>
                {/snippet}
            </SearchCombobox>
        </div>
    </div>

    <!-- Lista de Wide Cards (Jerarquía Visual de Elementos) -->
    <div class="flex flex-col gap-3">
        {#each filteredMovements as mov (mov.id)}
            <div class="bento-card group flex items-center justify-between gap-6 rounded-2xl border border-border/80 dark:border-border/90 bg-card p-4.5 shadow-[0px_10px_30px_rgba(0,0,0,0.02)] transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 hover:border-rose-500/40">

                <!-- Lado Izquierdo: Icon Avatar + Datos Principales -->
                <div class="flex items-center gap-4.5 min-w-0 flex-1">
                    <!-- Icon Avatar Badge -->
                    <div class="size-13 rounded-2xl bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/25 dark:border-rose-500/30 flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs transition-transform group-hover:scale-105">
                        {getInitials(mov.product?.name)}
                    </div>

                    <!-- Jerarquía de Información -->
                    <div class="flex flex-col gap-1.5 min-w-0 flex-1">
                        <!-- Fila 1: Nombre Destacado + SKU + Motivo -->
                        <div class="flex items-center gap-3 flex-wrap">
                            <h3 class="font-semibold text-base text-foreground group-hover:text-rose-700 dark:group-hover:text-rose-400 transition-colors truncate max-w-md leading-snug">
                                {mov.product?.name ?? 'Producto eliminado'}
                            </h3>

                            {#if mov.product?.sku}
                                <span class="inline-flex items-center rounded-md bg-muted/60 dark:bg-secondary/40 px-2 py-0.5 text-xs tracking-wide text-muted-foreground border border-border/70 dark:border-border/80 tabular-nums">
                                     SKU: {mov.product.sku}
                                 </span>
                            {/if}

                            <span class={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider border ${
                                mov.reason === 'merma_devolucion_cliente'
                                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30 dark:border-amber-500/30'
                                    : 'bg-rose-500/10 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border-rose-500/25 dark:border-rose-500/30'
                            }`}>
                                <Tag class="size-3" />
                                {formatReason(mov.reason)}
                            </span>

                            {#if mov.warehouse}
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/80 dark:bg-secondary/50 px-2 py-0.5 text-xs font-semibold text-foreground border border-border/70 dark:border-border/80 shadow-2xs">
                                    <Building2 class="size-3 text-muted-foreground shrink-0" />
                                    {mov.warehouse.name}
                                </span>
                            {/if}
                        </div>

                        <!-- Fila 2: Notas y Detalles -->
                        {#if mov.notes}
                            <p class="text-xs text-muted-foreground line-clamp-1 flex items-center gap-1.5">
                                <FileText class="size-3 text-muted-foreground/80 shrink-0" />
                                <span>{mov.notes}</span>
                            </p>
                        {/if}

                        <!-- Fila 3: Metadatos (Responsable y Fecha) -->
                        <div class="flex items-center gap-4 text-xs text-muted-foreground mt-0.5">
                            <div class="flex items-center gap-1.5">
                                <User class="size-3.5 text-muted-foreground/80" />
                                <span>Registrado por: <strong class="text-foreground font-medium">{mov.user?.name ?? 'Sistema'}</strong></span>
                            </div>
                            <span class="text-border">•</span>
                            <div class="flex items-center gap-1.5 text-sm tabular-nums">
                                <Calendar class="size-3.5 text-muted-foreground/80" />
                                <span>{formatDate(mov.created_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lado Derecho: Variación de Unidades + Progresión de Stock -->
                <div class="flex items-center gap-4 shrink-0 pl-4 border-l border-border/60">
                    <div class="text-right flex flex-col items-end gap-1">
                        <!-- Badge de Cantidad Egresada -->
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-bold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/25 dark:border-rose-500/30 tabular-nums shadow-2xs">
                            <ArrowUpRight class="size-3.5" />
                            -{mov.quantity} u.
                        </span>

                        <!-- Progresión de Stock (Antes -> Después) -->
                        <div class="flex items-center gap-1 text-sm tabular-nums text-muted-foreground">
                            <span>Stock:</span>
                            <span class="text-muted-foreground font-semibold">{mov.previous_stock}</span>
                            <ArrowRight class="size-3 text-muted-foreground/80" />
                            <span class="font-bold text-foreground text-sm">{mov.current_stock}</span>
                        </div>
                    </div>
                </div>
            </div>
        {/each}

        {#if filteredMovements.length === 0}
            <EmptyState
                icon={ArrowUpRight}
                title="No se encontraron egresos de stock"
                description="No hay registros de egreso que coincidan con los filtros aplicados en este período."
            />
        {/if}
    </div>

    <!-- Footer Informativo y Paginación -->
    {#if pagination}
        <div class="pt-2">
            <AppPagination {pagination} />
        </div>
    {/if}

    <div class="flex items-center justify-between text-xs text-muted-foreground px-2">
        <span>Mostrando <strong>{filteredMovements.length}</strong> de <strong>{pagination ? pagination.total : movements.length}</strong> movimientos de egreso</span>
        <span class="text-xs tabular-nums">Kardex Outcomes</span>
    </div>
</div>
