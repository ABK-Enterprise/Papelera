<script lang="ts">
    import FilterModalLayoutMobile, { type FilterTabItem } from '@/components/common/FilterModalLayoutMobile.svelte';
    import Check from 'lucide-svelte/icons/check';
    import ClipboardList from 'lucide-svelte/icons/clipboard-list';

    interface StatsProps {
        total?: number;
        solicitado?: number;
        recibido?: number;
        cancelado?: number;
    }

    let {
        open = $bindable(false),
        selectedStatus = 'all',
        stats = { total: 0, solicitado: 0, recibido: 0, cancelado: 0 },
        onapply,
        onreset,
    }: {
        open?: boolean;
        selectedStatus?: string;
        stats?: StatsProps;
        onapply?: (filters: { status: string }) => void;
        onreset?: () => void;
    } = $props();

    let activeTab = $state('status');
    let localStatus = $state(selectedStatus);

    $effect(() => {
        if (open) {
            localStatus = selectedStatus;
        }
    });

    let isStatusActive = $derived(localStatus !== 'all' && localStatus !== '');
    let localActiveCount = $derived(isStatusActive ? 1 : 0);

    let tabs: FilterTabItem[] = $derived([
        {
            id: 'status',
            label: 'Estado',
            icon: ClipboardList,
            isActive: isStatusActive,
        },
    ]);

    const statusOptions = [
        { value: 'all', label: 'Todos los estados', key: 'total' as keyof StatsProps, color: 'bg-muted-foreground' },
        { value: 'solicitado', label: 'Solicitado / Pendiente', key: 'solicitado' as keyof StatsProps, color: 'bg-amber-500' },
        { value: 'recibido', label: 'Recibido en Stock', key: 'recibido' as keyof StatsProps, color: 'bg-emerald-500' },
        { value: 'cancelado', label: 'Cancelado', key: 'cancelado' as keyof StatsProps, color: 'bg-rose-500' },
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
        localStatus = 'all';
        if (onreset) {
            onreset();
        }
        open = false;
    }
</script>

<FilterModalLayoutMobile
    bind:open
    title="Filtros de Órdenes de Compra"
    description="Filtra por estado de recepción y abastecimiento."
    {tabs}
    bind:activeTab
    activeCount={localActiveCount}
    onapply={handleApply}
    onreset={handleReset}
>
    {#if activeTab === 'status'}
        <div class="space-y-1.5">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-2 pb-1">
                Estado de la orden
            </h4>
            {#each statusOptions as opt (opt.value)}
                {@const isSelected = localStatus === opt.value}
                {@const count = stats[opt.key] ?? 0}
                <button
                    type="button"
                    onclick={() => (localStatus = opt.value)}
                    class="w-full flex items-center justify-between min-h-[44px] px-3 py-2.5 rounded-xl border text-left text-xs transition-all {isSelected
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
</FilterModalLayoutMobile>
