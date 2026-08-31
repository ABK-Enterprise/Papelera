<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import ArrowDownLeft from 'lucide-svelte/icons/arrow-down-left';
    import ArrowUpRight from 'lucide-svelte/icons/arrow-up-right';
    import Check from 'lucide-svelte/icons/check';
    import History from 'lucide-svelte/icons/history';

    let {
        open = $bindable(false),
        selectedType = '',
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedType?: string;
        onapply?: (filters: { type: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('type');
    let localType = $state('');

    $effect(() => {
        if (open) {
            localType = selectedType;
        }
    });

    let isTypeActive = $derived(localType !== '');
    let localActiveCount = $derived(isTypeActive ? 1 : 0);

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'type',
            label: 'Tipo',
            icon: History,
            isActive: isTypeActive,
        },
    ]);

    const typeOptions = [
        { value: '', label: 'Todos los movimientos' },
        { value: 'income', label: 'Solo Ingresos de Stock', icon: ArrowDownLeft, color: 'text-emerald-600 dark:text-emerald-400' },
        { value: 'outcome', label: 'Solo Egresos de Stock', icon: ArrowUpRight, color: 'text-rose-600 dark:text-rose-400' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                type: localType,
            });
        }
        open = false;
    }

    function handleReset() {
        localType = '';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Kardex"
    description="Filtra los movimientos de inventario por tipo de operación."
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
    {/if}
</FilterModalLayoutMobile>
