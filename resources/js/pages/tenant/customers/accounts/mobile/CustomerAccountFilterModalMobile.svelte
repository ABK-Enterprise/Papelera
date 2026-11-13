<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import AlertTriangle from 'lucide-svelte/icons/alert-triangle';
    import ArrowUpDown from 'lucide-svelte/icons/arrow-up-down';
    import Check from 'lucide-svelte/icons/check';
    import FileText from 'lucide-svelte/icons/file-text';
    import UserCheck from 'lucide-svelte/icons/user-check';

    let {
        open = $bindable(false),
        selectedTaxCondition = '',
        selectedStatus = '',
        selectedDefaulter = '',
        selectedSort = 'balance_high',
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedTaxCondition?: string;
        selectedStatus?: string;
        selectedDefaulter?: string;
        selectedSort?: string;
        onapply?: (filters: { tax_condition: string; status: string; defaulters_only: string; sort_by: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('sort');
    let localTaxCondition = $state('');
    let localStatus = $state('');
    let localDefaulter = $state('');
    let localSort = $state('balance_high');

    $effect(() => {
        if (open) {
            localTaxCondition = selectedTaxCondition || '';
            localStatus = selectedStatus || '';
            localDefaulter = selectedDefaulter || '';
            localSort = selectedSort || 'balance_high';
        }
    });

    let isTaxActive = $derived(localTaxCondition !== '');
    let isStatusActive = $derived(localStatus !== '');
    let isDefaulterActive = $derived(localDefaulter !== '');
    let isSortActive = $derived(localSort !== 'balance_high' && localSort !== '');

    let localActiveCount = $derived(
        (isTaxActive ? 1 : 0) + (isStatusActive ? 1 : 0) + (isDefaulterActive ? 1 : 0) + (isSortActive ? 1 : 0)
    );

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'sort',
            label: 'Orden',
            icon: ArrowUpDown as any,
            isActive: isSortActive,
        },
        {
            id: 'defaulter',
            label: 'Morosidad',
            icon: AlertTriangle as any,
            isActive: isDefaulterActive,
        },
        {
            id: 'tax_condition',
            label: 'Cond. Fiscal',
            icon: FileText as any,
            isActive: isTaxActive,
        },
        {
            id: 'status',
            label: 'Estado',
            icon: UserCheck as any,
            isActive: isStatusActive,
        },
    ]);

    const sortOptions = [
        { value: 'balance_high', label: 'Mayor saldo deudor' },
        { value: 'balance_low', label: 'Menor saldo deudor' },
        { value: 'credit_high', label: 'Mayor límite de crédito' },
        { value: 'name_asc', label: 'Nombre: A - Z' },
        { value: 'name_desc', label: 'Nombre: Z - A' },
        { value: 'latest', label: 'Más recientes' },
        { value: 'oldest', label: 'Más antiguos' },
    ];

    const defaulterOptions = [
        { value: '', label: 'Todos los clientes' },
        { value: 'yes', label: 'Solo Clientes Morosos' },
    ];

    const taxOptions = [
        { value: '', label: 'Todas las condiciones' },
        { value: 'consumidor_final', label: 'Consumidor Final' },
        { value: 'responsable_inscripto', label: 'Responsable Inscripto' },
        { value: 'monotributo', label: 'Monotributo' },
        { value: 'exento', label: 'Exento' },
    ];

    const statusOptions = [
        { value: '', label: 'Todos los estados' },
        { value: 'active', label: 'Solo Clientes Activos' },
        { value: 'inactive', label: 'Solo Clientes Inactivos' },
    ];

    function handleApply() {
        if (onapply) {
            onapply({
                tax_condition: localTaxCondition,
                status: localStatus,
                defaulters_only: localDefaulter,
                sort_by: localSort,
            });
        }
        open = false;
    }

    function handleReset() {
        localTaxCondition = '';
        localStatus = '';
        localDefaulter = '';
        localSort = 'balance_high';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Cuentas Corrientes"
    description="Segmenta y ordena las cuentas corrientes de tus clientes."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    <!-- Panel 1: Ordenamiento -->
    {#if activeTab === 'sort'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Criterio de orden
            </h4>
            {#each sortOptions as opt (opt.value)}
                {@const isSelected = localSort === opt.value}
                <button
                    type="button"
                    onclick={() => (localSort = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-sm transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-medium shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <span>{opt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {/if}

    <!-- Panel 2: Morosidad -->
    {#if activeTab === 'defaulter'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Morosidad de cuenta
            </h4>
            {#each defaulterOptions as opt (opt.value)}
                {@const isSelected = localDefaulter === opt.value}
                <button
                    type="button"
                    onclick={() => (localDefaulter = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-sm transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-medium shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <div class="flex items-center gap-2">
                        {#if opt.value === 'yes'}
                            <span class="h-2 w-2 rounded-full bg-rose-500 shrink-0"></span>
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

    <!-- Panel 3: Condición Fiscal -->
    {#if activeTab === 'tax_condition'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Condición ante ARCA / AFIP
            </h4>
            {#each taxOptions as opt (opt.value)}
                {@const isSelected = localTaxCondition === opt.value}
                <button
                    type="button"
                    onclick={() => (localTaxCondition = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-sm transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-medium shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground'}"
                >
                    <span>{opt.label}</span>
                    {#if isSelected}
                        <Check class="h-4 w-4 text-primary shrink-0 stroke-[2.5]" />
                    {/if}
                </button>
            {/each}
        </div>
    {/if}

    <!-- Panel 4: Estado -->
    {#if activeTab === 'status'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Estado del cliente
            </h4>
            {#each statusOptions as opt (opt.value)}
                {@const isSelected = localStatus === opt.value}
                <button
                    type="button"
                    onclick={() => (localStatus = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-sm transition-all {isSelected
                        ? 'border-primary/40 bg-primary/15 text-foreground dark:text-sky-100 font-medium shadow-2xs'
                        : 'border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 hover:bg-muted/70 dark:hover:bg-secondary/55 text-foreground'}"
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
