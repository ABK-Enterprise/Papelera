<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';
    import Calendar from 'lucide-svelte/icons/calendar';
    import Check from 'lucide-svelte/icons/check';
    import FileText from 'lucide-svelte/icons/file-text';

    let {
        open = $bindable(false),
        selectedCbteTipo = 'all',
        dateFrom = '',
        dateTo = '',
        sortBy = 'latest',
        onapply = undefined,
        onreset = undefined,
    }: {
        open?: boolean;
        selectedCbteTipo?: string;
        dateFrom?: string;
        dateTo?: string;
        sortBy?: string;
        onapply?: (filters: { cbte_tipo: string; date_from: string; date_to: string; sort_by: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('cbte_tipo');
    let localCbteTipo = $state('all');
    let localDateFrom = $state('');
    let localDateTo = $state('');
    let localSortBy = $state('latest');

    $effect(() => {
        if (open) {
            localCbteTipo = selectedCbteTipo || 'all';
            localDateFrom = dateFrom || '';
            localDateTo = dateTo || '';
            localSortBy = sortBy || 'latest';
        }
    });

    let isCbteActive = $derived(localCbteTipo !== 'all' && localCbteTipo !== '');
    let isDatesActive = $derived(Boolean(localDateFrom || localDateTo));
    let isSortActive = $derived(localSortBy !== 'latest');

    let localActiveCount = $derived(
        (isCbteActive ? 1 : 0) + (isDatesActive ? 1 : 0) + (isSortActive ? 1 : 0)
    );

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'cbte_tipo',
            label: 'Comprobante',
            icon: FileText,
            isActive: isCbteActive,
        },
        {
            id: 'dates',
            label: 'Fechas',
            icon: Calendar,
            isActive: isDatesActive,
        },
        {
            id: 'sort',
            label: 'Orden',
            icon: ArrowUpDown,
            isActive: isSortActive,
        },
    ]);

    const cbteTipoOptions = [
        { value: 'all', label: 'Todos los comprobantes' },
        { value: '1', label: 'Factura A (001)' },
        { value: '6', label: 'Factura B (006)' },
        { value: '11', label: 'Factura C (011)' },
        { value: '3', label: 'Nota de Crédito A (003)' },
        { value: '8', label: 'Nota de Crédito B (008)' },
        { value: '13', label: 'Nota de Crédito C (013)' },
    ];

    const sortOptions = [
        { value: 'latest', label: 'Más recientes (Predeterminado)' },
        { value: 'oldest', label: 'Más antiguos' },
        { value: 'amount_desc', label: 'Mayor importe total' },
        { value: 'amount_asc', label: 'Menor importe total' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                cbte_tipo: localCbteTipo,
                date_from: localDateFrom,
                date_to: localDateTo,
                sort_by: localSortBy,
            });
        }
        open = false;
    }

    function handleReset() {
        localCbteTipo = 'all';
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
    title="Filtros de Facturas"
    description="Segmenta comprobantes fiscales y notas de crédito."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    <!-- Panel 1: Tipo de Comprobante -->
    {#if activeTab === 'cbte_tipo'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Tipo de comprobante
            </h4>
            {#each cbteTipoOptions as opt (opt.value)}
                {@const isSelected = localCbteTipo === opt.value}
                <button
                    type="button"
                    onclick={() => (localCbteTipo = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-semibold shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 hover:bg-muted/70 dark:bg-secondary/35 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <span>{opt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {/if}

    <!-- Panel 2: Rango de Fechas -->
    {#if activeTab === 'dates'}
        <div class="space-y-3 p-1">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground pb-1">
                Rango de emisión
            </h4>
            <div class="space-y-1.5">
                <Label for="invoice-date-from" class="text-xs font-medium text-muted-foreground">Fecha Desde</Label>
                <Input
                    id="invoice-date-from"
                    type="date"
                    bind:value={localDateFrom}
                    class="h-11 rounded-xl text-xs dark:[color-scheme:dark]"
                />
            </div>
            <div class="space-y-1.5">
                <Label for="invoice-date-to" class="text-xs font-medium text-muted-foreground">Fecha Hasta</Label>
                <Input
                    id="invoice-date-to"
                    type="date"
                    bind:value={localDateTo}
                    class="h-11 rounded-xl text-xs dark:[color-scheme:dark]"
                />
            </div>
        </div>
    {/if}

    <!-- Panel 3: Criterio de Ordenamiento -->
    {#if activeTab === 'sort'}
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
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-semibold shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 hover:bg-muted/70 dark:bg-secondary/35 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <span>{opt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {/if}
</FilterModalLayoutMobile>
