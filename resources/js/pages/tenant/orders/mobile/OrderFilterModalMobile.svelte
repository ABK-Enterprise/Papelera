<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';
    import Check from 'lucide-svelte/icons/check';
    import ShoppingBag from 'lucide-svelte/icons/shopping-bag';

    interface StatsProps {
        total?: number;
        en_preparacion?: number;
        preparado?: number;
        en_camino?: number;
        entregado?: number;
        cancelado?: number;
    }

    let {
        open = $bindable(false),
        selectedStatus = 'all',
        sortBy = 'latest',
        stats = { total: 0, en_preparacion: 0, preparado: 0, en_camino: 0, entregado: 0, cancelado: 0 },
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedStatus?: string;
        sortBy?: string;
        stats?: StatsProps;
        onapply?: (filters: { status: string; sortBy: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('status');
    let localStatus = $state('all');
    let localSortBy = $state('latest');

    $effect(() => {
        if (open) {
            localStatus = selectedStatus;
            localSortBy = sortBy;
        }
    });

    let isStatusActive = $derived(localStatus !== 'all' && localStatus !== '');
    let isSortActive = $derived(localSortBy !== 'latest');

    let localActiveCount = $derived(
        (isStatusActive ? 1 : 0) + (isSortActive ? 1 : 0)
    );

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'status',
            label: 'Estado',
            icon: ShoppingBag,
            isActive: isStatusActive,
        },
        {
            id: 'sort',
            label: 'Orden',
            icon: ArrowUpDown,
            isActive: isSortActive,
        },
    ]);

    const statusOptions = [
        { value: 'all', label: 'Todos los estados', key: 'total' as keyof StatsProps, color: 'bg-muted-foreground' },
        { value: 'en_preparacion', label: 'En Preparación', key: 'en_preparacion' as keyof StatsProps, color: 'bg-amber-500' },
        { value: 'preparado', label: 'Preparado', key: 'preparado' as keyof StatsProps, color: 'bg-blue-500' },
        { value: 'en_camino', label: 'En Camino', key: 'en_camino' as keyof StatsProps, color: 'bg-indigo-500' },
        { value: 'entregado', label: 'Entregado', key: 'entregado' as keyof StatsProps, color: 'bg-emerald-500' },
        { value: 'cancelado', label: 'Cancelado', key: 'cancelado' as keyof StatsProps, color: 'bg-rose-500' },
    ];

    const sortOptions = [
        { value: 'latest', label: 'Más recientes (Predeterminado)' },
        { value: 'oldest', label: 'Más antiguos' },
        { value: 'amount_desc', label: 'Mayor monto total' },
        { value: 'amount_asc', label: 'Menor monto total' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                status: localStatus,
                sortBy: localSortBy,
            });
        }
        open = false;
    }

    function handleReset() {
        localStatus = 'all';
        localSortBy = 'latest';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Pedidos"
    description="Filtra por estado logístico y ordena las ventas."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    <!-- Panel 1: Estado del Pedido -->
    {#if activeTab === 'status'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Estado del pedido
            </h4>
            {#each statusOptions as opt (opt.value)}
                {@const isSelected = localStatus === opt.value}
                {@const count = stats[opt.key] ?? 0}
                <button
                    type="button"
                    onclick={() => (localStatus = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-sm transition-all {isSelected
                        ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                        : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                >
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {opt.color} shrink-0"></span>
                        <span>{opt.label}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground tabular-nums">({count})</span>
                        {#if isSelected}
                            <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                        {/if}
                    </div>
                </button>
            {/each}
        </div>
    {/if}

    <!-- Panel 2: Ordenamiento -->
    {#if activeTab === 'sort'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Criterio de orden
            </h4>
            {#each sortOptions as sortOpt (sortOpt.value)}
                {@const isSelected = localSortBy === sortOpt.value}
                <button
                    type="button"
                    onclick={() => (localSortBy = sortOpt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-sm transition-all {isSelected
                        ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                        : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                >
                    <span>{sortOpt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {/if}
</FilterModalLayoutMobile>
