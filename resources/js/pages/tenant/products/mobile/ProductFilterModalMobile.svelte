<script lang="ts">
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogBody,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Badge } from '@/components/ui/badge';
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';
    import Tag from 'lucide-svelte/icons/tag';
    import Boxes from 'lucide-svelte/icons/boxes';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import Check from 'lucide-svelte/icons/check';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';

    interface CategoryOption {
        id: number;
        name: string;
        products_count?: number;
    }

    interface StatsProps {
        total: number;
        in_stock: number;
        low_stock: number;
        out_of_stock: number;
        incomplete?: number;
    }

    interface SortOption {
        value: string;
        label: string;
    }

    let {
        open = $bindable(false),
        categories = [],
        stats = { total: 0, in_stock: 0, low_stock: 0, out_of_stock: 0, incomplete: 0 },
        incompleteCount = 0,
        selectedCategoryId = null,
        selectedStockStatus = '',
        selectedInfoStatus = '',
        sortBy = 'latest',
        sortOptions = [],
        onapply,
        onreset,
    }: {
        open?: boolean;
        categories?: CategoryOption[];
        stats?: StatsProps;
        incompleteCount?: number;
        selectedCategoryId?: number | null;
        selectedStockStatus?: string;
        selectedInfoStatus?: string;
        sortBy?: string;
        sortOptions?: SortOption[];
        onapply: (filters: {
            category_id: number | null;
            stock_status: string;
            info_status: string;
            sort_by: string;
        }) => void;
        onreset: () => void;
    } = $props();

    let localCategoryId = $state<number | null>(null);
    let localStockStatus = $state('');
    let localInfoStatus = $state('');
    let localSortBy = $state('latest');
    let activeTab = $state<'sort' | 'category' | 'stock' | 'info'>('sort');

    $effect(() => {
        if (open) {
            localCategoryId = selectedCategoryId;
            localStockStatus = selectedStockStatus;
            localInfoStatus = selectedInfoStatus;
            localSortBy = sortBy;
        }
    });

    let localActiveCount = $derived(
        (localCategoryId !== null ? 1 : 0) +
        (localStockStatus ? 1 : 0) +
        (localInfoStatus ? 1 : 0) +
        (localSortBy && localSortBy !== 'latest' ? 1 : 0)
    );

    function handleApply() {
        onapply({
            category_id: localCategoryId,
            stock_status: localStockStatus,
            info_status: localInfoStatus,
            sort_by: localSortBy,
        });
        open = false;
    }

    function handleReset() {
        localCategoryId = null;
        localStockStatus = '';
        localInfoStatus = '';
        localSortBy = 'latest';
        onreset();
        open = false;
    }
</script>

<Dialog bind:open>
    <DialogContent class="max-w-xl p-0 gap-0 overflow-hidden rounded-2xl sm:rounded-3xl border-border/80 shadow-xl flex flex-col max-h-[85vh]">
        <DialogHeader class="p-4 sm:p-5 border-b border-border/50 shrink-0">
            <div class="flex items-center justify-between pr-6">
                <div class="flex items-center gap-2">
                    <DialogTitle class="text-base sm:text-lg font-bold text-foreground">
                        Filtros y ordenamiento
                    </DialogTitle>
                    {#if localActiveCount > 0}
                        <Badge variant="default" class="h-5 px-2 text-xs font-semibold tabular-nums">
                            {localActiveCount} {localActiveCount === 1 ? 'activo' : 'activos'}
                        </Badge>
                    {/if}
                </div>
            </div>
            <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                Selecciona las opciones para filtrar los artículos del catálogo.
            </DialogDescription>
        </DialogHeader>

        <!-- Split Layout de 2 Columnas -->
        <DialogBody class="p-0 flex-1 min-h-0 overflow-hidden flex flex-row bg-background">
            <!-- Columna Izquierda: Pestañas de Filtro (~1/4 del ancho) -->
            <nav class="w-[34%] sm:w-[30%] shrink-0 border-r border-border/60 bg-muted/30 p-1.5 flex flex-col gap-1 overflow-y-auto custom-scrollbar">
                <!-- Tab Ordenar -->
                <button
                    type="button"
                    onclick={() => (activeTab = 'sort')}
                    class="w-full flex items-center justify-between gap-1.5 px-2.5 py-3 rounded-xl text-left text-xs font-medium transition-all {activeTab === 'sort'
                        ? 'bg-background text-foreground shadow-xs font-semibold border border-border/60'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'}"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <ArrowUpDown class="h-4 w-4 shrink-0 {activeTab === 'sort' ? 'text-primary' : 'text-muted-foreground'}" />
                        <span class="truncate">Ordenar</span>
                    </div>
                    {#if localSortBy && localSortBy !== 'latest'}
                        <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                    {/if}
                </button>

                <!-- Tab Categorías -->
                <button
                    type="button"
                    onclick={() => (activeTab = 'category')}
                    class="w-full flex items-center justify-between gap-1.5 px-2.5 py-3 rounded-xl text-left text-xs font-medium transition-all {activeTab === 'category'
                        ? 'bg-background text-foreground shadow-xs font-semibold border border-border/60'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'}"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <Tag class="h-4 w-4 shrink-0 {activeTab === 'category' ? 'text-primary' : 'text-muted-foreground'}" />
                        <span class="truncate">Categoría</span>
                    </div>
                    {#if localCategoryId !== null}
                        <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                    {/if}
                </button>

                <!-- Tab Stock -->
                <button
                    type="button"
                    onclick={() => (activeTab = 'stock')}
                    class="w-full flex items-center justify-between gap-1.5 px-2.5 py-3 rounded-xl text-left text-xs font-medium transition-all {activeTab === 'stock'
                        ? 'bg-background text-foreground shadow-xs font-semibold border border-border/60'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'}"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <Boxes class="h-4 w-4 shrink-0 {activeTab === 'stock' ? 'text-primary' : 'text-muted-foreground'}" />
                        <span class="truncate">Stock</span>
                    </div>
                    {#if localStockStatus !== ''}
                        <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                    {/if}
                </button>

                <!-- Tab Datos / Completitud -->
                <button
                    type="button"
                    onclick={() => (activeTab = 'info')}
                    class="w-full flex items-center justify-between gap-1.5 px-2.5 py-3 rounded-xl text-left text-xs font-medium transition-all {activeTab === 'info'
                        ? 'bg-background text-foreground shadow-xs font-semibold border border-border/60'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'}"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <AlertTriangle class="h-4 w-4 shrink-0 {activeTab === 'info' ? 'text-primary' : 'text-muted-foreground'}" />
                        <span class="truncate">Datos</span>
                    </div>
                    {#if localInfoStatus !== ''}
                        <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                    {/if}
                </button>
            </nav>

            <!-- Columna Derecha: Panel de Opciones (~3/4 del ancho) -->
            <div class="flex-1 min-h-0 overflow-y-auto p-3 sm:p-4 space-y-1.5 custom-scrollbar">
                <!-- Panel 1: Opciones de Ordenamiento -->
                {#if activeTab === 'sort'}
                    <div class="space-y-1.5">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                            Criterio de orden
                        </h4>
                        {#each sortOptions as opt}
                            <button
                                type="button"
                                onclick={() => (localSortBy = opt.value)}
                                class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localSortBy === opt.value
                                    ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                    : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                            >
                                <span>{opt.label}</span>
                                {#if localSortBy === opt.value}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </button>
                        {/each}
                    </div>
                {/if}

                <!-- Panel 2: Opciones de Categoría -->
                {#if activeTab === 'category'}
                    <div class="space-y-1.5">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                            Seleccionar categoría
                        </h4>
                        <!-- Opción Todas -->
                        <button
                            type="button"
                            onclick={() => (localCategoryId = null)}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localCategoryId === null
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <span>Todas las categorías</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground tabular-nums">({stats.total})</span>
                                {#if localCategoryId === null}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </div>
                        </button>

                        {#each categories as cat}
                            <button
                                type="button"
                                onclick={() => (localCategoryId = cat.id)}
                                class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localCategoryId === cat.id
                                    ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                    : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                            >
                                <span class="truncate">{cat.name}</span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs text-muted-foreground tabular-nums">({cat.products_count ?? 0})</span>
                                    {#if localCategoryId === cat.id}
                                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                    {/if}
                                </div>
                            </button>
                        {/each}
                    </div>
                {/if}

                <!-- Panel 3: Opciones de Stock -->
                {#if activeTab === 'stock'}
                    <div class="space-y-1.5">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                            Disponibilidad de stock
                        </h4>

                        <!-- Todos -->
                        <button
                            type="button"
                            onclick={() => (localStockStatus = '')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localStockStatus === ''
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <span>Todos los niveles de stock</span>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground tabular-nums">({stats.total})</span>
                                {#if localStockStatus === ''}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </div>
                        </button>

                        <!-- En Stock -->
                        <button
                            type="button"
                            onclick={() => (localStockStatus = 'in_stock')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localStockStatus === 'in_stock'
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
                                <span>En Stock disponible</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground tabular-nums">({stats.in_stock})</span>
                                {#if localStockStatus === 'in_stock'}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </div>
                        </button>

                        <!-- Bajo Stock -->
                        <button
                            type="button"
                            onclick={() => (localStockStatus = 'low_stock')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localStockStatus === 'low_stock'
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-amber-500 shrink-0"></span>
                                <span>Bajo Stock (Alerta)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground tabular-nums">({stats.low_stock})</span>
                                {#if localStockStatus === 'low_stock'}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </div>
                        </button>

                        <!-- Agotado -->
                        <button
                            type="button"
                            onclick={() => (localStockStatus = 'out_of_stock')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localStockStatus === 'out_of_stock'
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-rose-500 shrink-0"></span>
                                <span>Agotado / Sin stock</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground tabular-nums">({stats.out_of_stock})</span>
                                {#if localStockStatus === 'out_of_stock'}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </div>
                        </button>
                    </div>
                {/if}

                <!-- Panel 4: Opciones de Información / Completitud -->
                {#if activeTab === 'info'}
                    <div class="space-y-1.5">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                            Estado de información
                        </h4>

                        <!-- Todos -->
                        <button
                            type="button"
                            onclick={() => (localInfoStatus = '')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localInfoStatus === ''
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <span>Todos los datos</span>
                            {#if localInfoStatus === ''}
                                <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                            {/if}
                        </button>

                        <!-- Completos -->
                        <button
                            type="button"
                            onclick={() => (localInfoStatus = 'complete')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localInfoStatus === 'complete'
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <span>Datos completos</span>
                            {#if localInfoStatus === 'complete'}
                                <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                            {/if}
                        </button>

                        <!-- Incompletos -->
                        <button
                            type="button"
                            onclick={() => (localInfoStatus = 'incomplete')}
                            class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {localInfoStatus === 'incomplete'
                                ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                                : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                        >
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-amber-500 shrink-0"></span>
                                <span>Incompletos (Faltan datos)</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-muted-foreground tabular-nums">({incompleteCount})</span>
                                {#if localInfoStatus === 'incomplete'}
                                    <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                                {/if}
                            </div>
                        </button>
                    </div>
                {/if}
            </div>
        </DialogBody>

        <DialogFooter class="p-3 sm:p-4 border-t border-border/50 bg-muted/20 flex flex-row items-center justify-between gap-2 shrink-0">
            <Button
                type="button"
                variant="ghost"
                size="sm"
                onclick={handleReset}
                disabled={localActiveCount === 0}
                class="min-h-[40px] px-3 rounded-xl text-xs font-medium text-muted-foreground hover:text-foreground gap-1.5"
            >
                <RotateCcw class="h-3.5 w-3.5" />
                <span>Limpiar</span>
            </Button>
            <Button
                type="button"
                size="sm"
                onclick={handleApply}
                class="min-h-[40px] px-5 rounded-xl text-xs font-semibold shadow-sm"
            >
                <span>Aplicar filtros</span>
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
