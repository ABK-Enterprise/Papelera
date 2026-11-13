<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import Check from 'lucide-svelte/icons/check';
    import Truck from 'lucide-svelte/icons/truck';

    let {
        open = $bindable(false),
        selectedStatus = '',
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedStatus?: string;
        onapply?: (filters: { status: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('status');
    let localStatus = $state('');

    $effect(() => {
        if (open) {
            localStatus = selectedStatus;
        }
    });

    let isStatusActive = $derived(localStatus !== '');
    let localActiveCount = $derived(isStatusActive ? 1 : 0);

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'status',
            label: 'Estado',
            icon: Truck,
            isActive: isStatusActive,
        },
    ]);

    const statusOptions = [
        { value: '', label: 'Todos los proveedores' },
        { value: 'active', label: 'Solo Proveedores Activos' },
        { value: 'inactive', label: 'Solo Proveedores Inactivos' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                status: localStatus,
            });
        }
        open = false;
    }

    function handleReset() {
        localStatus = '';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Proveedores"
    description="Filtra el directorio de proveedores por estado de actividad."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    {#if activeTab === 'status'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Estado del proveedor
            </h4>
            {#each statusOptions as opt (opt.value)}
                {@const isSelected = localStatus === opt.value}
                <button
                    type="button"
                    onclick={() => (localStatus = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
                        ? 'border-primary bg-primary/10 dark:bg-primary/15 text-primary font-semibold shadow-2xs'
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
