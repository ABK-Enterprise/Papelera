<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Check from 'lucide-svelte/icons/check';
    import History from 'lucide-svelte/icons/history';
    import Calendar from 'lucide-svelte/icons/calendar';
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';

    let {
        open = $bindable(false),
        selectedType = '',
        selectedDateFrom = '',
        selectedDateTo = '',
        selectedSortBy = 'latest',
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedType?: string;
        selectedDateFrom?: string;
        selectedDateTo?: string;
        selectedSortBy?: string;
        onapply?: (filters: { type: string; date_from: string; date_to: string; sort_by: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('type');
    let localType = $state('');
    let localDateFrom = $state('');
    let localDateTo = $state('');
    let localSortBy = $state('latest');

    $effect(() => {
        if (open) {
            localType = selectedType;
            localDateFrom = selectedDateFrom;
            localDateTo = selectedDateTo;
            localSortBy = selectedSortBy || 'latest';
        }
    });

    let isTypeActive = $derived(localType !== '');
    let isDateActive = $derived(Boolean(localDateFrom || localDateTo));
    let isSortActive = $derived(Boolean(localSortBy && localSortBy !== 'latest'));

    let localActiveCount = $derived(
        (isTypeActive ? 1 : 0) + (isDateActive ? 1 : 0) + (isSortActive ? 1 : 0)
    );

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'type',
            label: 'Tipo',
            icon: History,
            isActive: isTypeActive,
        },
        {
            id: 'sort',
            label: 'Orden',
            icon: ArrowUpDown,
            isActive: isSortActive,
        },
        {
            id: 'date',
            label: 'Fechas',
            icon: Calendar,
            isActive: isDateActive,
        },
    ]);

    const typeOptions = [
        { value: '', label: 'Todos los movimientos' },
        { value: 'income', label: 'Solo Ingresos de Stock', icon: ArrowDownLeft, color: 'text-emerald-600 dark:text-emerald-400' },
        { value: 'outcome', label: 'Solo Egresos de Stock', icon: ArrowUpRight, color: 'text-rose-600 dark:text-rose-400' },
    ];

    const sortOptions = [
        { value: 'latest', label: 'Más recientes primero' },
        { value: 'oldest', label: 'Más antiguos primero' },
        { value: 'quantity_high', label: 'Mayor cantidad' },
        { value: 'quantity_low', label: 'Menor cantidad' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                type: localType,
                date_from: localDateFrom,
                date_to: localDateTo,
                sort_by: localSortBy,
            });
        }
        open = false;
    }

    function handleReset() {
        localType = '';
        localDateFrom = '';
        localDateTo = '';
        localSortBy = 'latest';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Kardex"
    description="Filtra los movimientos de inventario por tipo, orden y fechas."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    {#if activeTab === 'type'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Tipo de movimiento
            </h4>
            {#each typeOptions as opt (opt.value)}
                {@const isSelected = localType === opt.value}
                {@const OptIcon = opt.icon}
                <button
                    type="button"
                    onclick={() => (localType = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
                        ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                        : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                >
                    <div class="flex items-center gap-2">
                        {#if OptIcon}
                            <OptIcon class="size-4 {opt.color}" />
                        {/if}
                        <span>{opt.label}</span>
                    </div>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {:else if activeTab === 'sort'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Criterio de ordenamiento
            </h4>
            {#each sortOptions as opt (opt.value)}
                {@const isSelected = localSortBy === opt.value}
                <button
                    type="button"
                    onclick={() => (localSortBy = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
                        ? 'border-primary bg-primary/10 text-primary font-semibold shadow-2xs'
                        : 'border-border/60 bg-card hover:bg-muted/40 text-foreground'}"
                >
                    <span>{opt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {:else if activeTab === 'date'}
        <div class="space-y-3 p-1">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-1">
                Rango de fechas
            </h4>
            <div class="space-y-1.5">
                <label for="mobile-date-from" class="text-xs font-medium text-foreground block">Fecha desde</label>
                <input
                    id="mobile-date-from"
                    type="date"
                    bind:value={localDateFrom}
                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs min-h-[44px]"
                />
            </div>
            <div class="space-y-1.5">
                <label for="mobile-date-to" class="text-xs font-medium text-foreground block">Fecha hasta</label>
                <input
                    id="mobile-date-to"
                    type="date"
                    bind:value={localDateTo}
                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs min-h-[44px]"
                />
            </div>
        </div>
    {/if}
</FilterModalLayoutMobile>
