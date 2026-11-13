<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import Check from 'lucide-svelte/icons/check';
    import Tag from 'lucide-svelte/icons/tag';
    import Activity from 'lucide-svelte/icons/activity';

    let {
        open = $bindable(false),
        selectedType = '',
        selectedStatus = '',
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedType?: string;
        selectedStatus?: string;
        onapply?: (filters: { type: string; status: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('type');
    let localType = $state('');
    let localStatus = $state('');

    $effect(() => {
        if (open) {
            localType = selectedType;
            localStatus = selectedStatus;
        }
    });

    let isTypeActive = $derived(localType !== '');
    let isStatusActive = $derived(localStatus !== '');
    let localActiveCount = $derived((isTypeActive ? 1 : 0) + (isStatusActive ? 1 : 0));

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'type',
            label: 'Tipo',
            icon: Tag,
            isActive: isTypeActive,
        },
        {
            id: 'status',
            label: 'Estado',
            icon: Activity,
            isActive: isStatusActive,
        },
    ]);

    const typeOptions = [
        { value: '', label: 'Todos los ajustes' },
        { value: 'discount', label: 'Solo Descuentos' },
        { value: 'surcharge', label: 'Solo Recargos' },
    ];

    const statusOptions = [
        { value: '', label: 'Todos los estados' },
        { value: 'active', label: 'Solo Activos' },
        { value: 'inactive', label: 'Solo Pausados' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                type: localType,
                status: localStatus,
            });
        }
        open = false;
    }

    function handleReset() {
        localType = '';
        localStatus = '';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Recargos y Descuentos"
    description="Filtra por tipo de ajuste comercial y estado de actividad."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    {#if activeTab === 'type'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Tipo de ajuste
            </h4>
            {#each typeOptions as opt (opt.value)}
                {@const isSelected = localType === opt.value}
                <button
                    type="button"
                    onclick={() => (localType = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-semibold shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <span>{opt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {:else if activeTab === 'status'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Estado de actividad
            </h4>
            {#each statusOptions as opt (opt.value)}
                {@const isSelected = localStatus === opt.value}
                <button
                    type="button"
                    onclick={() => (localStatus = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-semibold shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <div class="flex items-center gap-2">
                        {#if opt.value === 'active'}
                            <span class="h-2 w-2 rounded-full bg-emerald-500 shrink-0"></span>
                        {:else if opt.value === 'inactive'}
                            <span class="h-2 w-2 rounded-full bg-muted-foreground shrink-0"></span>
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
