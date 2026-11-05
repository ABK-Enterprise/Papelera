<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';
    import GlideSelect, { type StatScreenId } from '@/components/svelte-bits/GlideSelect.svelte';
    import { RotateCcw, ChevronDown } from 'lucide-svelte';

    interface WarehouseItem {
        id: number;
        name: string;
        code: string;
    }

    interface Props {
        view?: StatScreenId;
        dateFrom?: string;
        dateTo?: string;
        warehouseId?: number | null;
        warehouses?: WarehouseItem[];
        class?: string;
        onViewChange?: (view: StatScreenId) => void;
    }

    let {
        view = 'inicial',
        dateFrom = '',
        dateTo = '',
        warehouseId = null,
        warehouses = [],
        class: className = '',
        onViewChange,
    }: Props = $props();

    // Helper to format dates YYYY-MM-DD
    function formatDate(d: Date): string {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    const today = new Date();
    const defaultTo = formatDate(today);
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 29);
    const defaultFrom = formatDate(thirtyDaysAgo);

    // Local reactive filter state
    let localView = $state<StatScreenId>(view);
    let localDateFrom = $state<string>(dateFrom || defaultFrom);
    let localDateTo = $state<string>(dateTo || defaultTo);
    let localWarehouseId = $state<number | null>(warehouseId);

    // Sync from parent props when they update
    $effect(() => {
        localView = view;
    });
    $effect(() => {
        if (dateFrom && dateFrom !== localDateFrom) {
            localDateFrom = dateFrom;
        }
    });
    $effect(() => {
        if (dateTo && dateTo !== localDateTo) {
            localDateTo = dateTo;
        }
    });
    $effect(() => {
        localWarehouseId = warehouseId;
    });

    type PresetKey = 'hoy' | '7d' | '30d' | 'este_mes' | 'ano_actual';

    interface PresetItem {
        id: PresetKey;
        label: string;
        getFromTo: () => { from: string; to: string };
    }

    const PRESETS: PresetItem[] = [
        {
            id: 'hoy',
            label: 'Hoy',
            getFromTo: () => {
                const now = new Date();
                const d = formatDate(now);
                return { from: d, to: d };
            },
        },
        {
            id: '7d',
            label: '7 días',
            getFromTo: () => {
                const toD = new Date();
                const fromD = new Date();
                fromD.setDate(toD.getDate() - 6);
                return { from: formatDate(fromD), to: formatDate(toD) };
            },
        },
        {
            id: '30d',
            label: '30 días',
            getFromTo: () => {
                const toD = new Date();
                const fromD = new Date();
                fromD.setDate(toD.getDate() - 29);
                return { from: formatDate(fromD), to: formatDate(toD) };
            },
        },
        {
            id: 'este_mes',
            label: 'Este mes',
            getFromTo: () => {
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                return { from: formatDate(firstDay), to: formatDate(now) };
            },
        },
        {
            id: 'ano_actual',
            label: 'Año actual',
            getFromTo: () => {
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), 0, 1);
                return { from: formatDate(firstDay), to: formatDate(now) };
            },
        },
    ];

    // Compute active preset based on current local dates
    const activePreset = $derived.by<PresetKey | null>(() => {
        for (const p of PRESETS) {
            const { from, to } = p.getFromTo();
            if (localDateFrom === from && localDateTo === to) {
                return p.id;
            }
        }
        return null;
    });

    // Requirement: Botón "Limpiar" solo aparece si hay un filtro de rango temporal puesto
    const isDateFiltered = $derived(
        localDateFrom !== defaultFrom || localDateTo !== defaultTo
    );

    function triggerFilterUpdate(overrides: Partial<{
        view: StatScreenId;
        date_from: string;
        date_to: string;
        warehouse_id: number | null;
    }> = {}) {
        const targetView = overrides.view ?? localView;
        const targetFrom = overrides.date_from ?? localDateFrom;
        const targetTo = overrides.date_to ?? localDateTo;
        const targetWarehouse = 'warehouse_id' in overrides ? overrides.warehouse_id : localWarehouseId;

        router.get(
            '/tenant/reportes',
            {
                view: targetView,
                date_from: targetFrom,
                date_to: targetTo,
                warehouse_id: targetWarehouse ?? undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }

    function selectPreset(preset: PresetItem) {
        const { from, to } = preset.getFromTo();
        localDateFrom = from;
        localDateTo = to;
        triggerFilterUpdate({ date_from: from, date_to: to });
    }

    function handleDateFromChange(e: Event) {
        const val = (e.target as HTMLInputElement).value;
        localDateFrom = val;
        triggerFilterUpdate({ date_from: val });
    }

    function handleDateToChange(e: Event) {
        const val = (e.target as HTMLInputElement).value;
        localDateTo = val;
        triggerFilterUpdate({ date_to: val });
    }

    function handleWarehouseChange(e: Event) {
        const val = (e.target as HTMLSelectElement).value;
        const parsed = val ? parseInt(val, 10) : null;
        localWarehouseId = parsed;
        triggerFilterUpdate({ warehouse_id: parsed });
    }

    function handleViewChange(newView: StatScreenId) {
        localView = newView;
        if (onViewChange) {
            onViewChange(newView);
        }
        triggerFilterUpdate({ view: newView });
    }

    // Reset date filters back to default (30 days)
    function resetDateFilters() {
        localDateFrom = defaultFrom;
        localDateTo = defaultTo;
        triggerFilterUpdate({
            date_from: defaultFrom,
            date_to: defaultTo,
        });
    }
</script>

<div
    class={cn(
        'w-full flex flex-col gap-3 p-4 rounded-xl border border-border/70 bg-card/60 backdrop-blur-xs shadow-xs relative',
        className
    )}
>
    <!-- Row 1: Presets & Visualization Selector (Glide Select) -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <!-- Preset Quick Buttons (Equal 40px height, balanced padding) -->
        <div class="flex items-center gap-1.5 flex-wrap">
            {#each PRESETS as preset (preset.id)}
                {@const isSelected = activePreset === preset.id}
                <button
                    type="button"
                    onclick={() => selectPreset(preset)}
                    class={cn(
                        'min-h-[40px] px-3.5 py-1.5 rounded-lg text-sm font-medium transition-all duration-150 cursor-pointer select-none',
                        isSelected
                            ? 'bg-primary text-primary-foreground font-semibold shadow-xs hover:bg-primary/90'
                            : 'border border-input/80 bg-select-background text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                    )}
                >
                    {preset.label}
                </button>
            {/each}

            <!-- Botón Limpiar: Visible ÚNICAMENTE si hay filtro temporal activo -->
            {#if isDateFiltered}
                <button
                    type="button"
                    onclick={resetDateFilters}
                    title="Restablecer fechas al rango por defecto (30 días)"
                    class="flex items-center gap-1.5 min-h-[40px] px-3 py-1.5 rounded-lg text-sm font-medium text-destructive hover:bg-destructive/10 transition-colors cursor-pointer select-none border border-destructive/20 ml-auto lg:ml-2"
                >
                    <RotateCcw class="size-3.5 shrink-0" />
                    <span>Limpiar</span>
                </button>
            {/if}
        </div>

        <!-- Glide Select for Analytics Views -->
        <div class="flex items-center gap-2 w-full lg:w-auto">
            <GlideSelect
                value={localView}
                onchange={handleViewChange}
                class="w-full lg:w-auto"
            />
        </div>
    </div>

    <!-- Row 2: Precise Datepickers & Amplified Warehouse Select (+10px margen/separación) -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-5 mt-2.5 border-t border-border/50 text-sm">
        <!-- Date from -->
        <div class="flex items-center gap-2 flex-1 sm:max-w-[220px]">
            <span class="text-xs text-muted-foreground shrink-0 font-medium">Desde:</span>
            <div class="relative w-full">
                <input
                    type="date"
                    bind:value={localDateFrom}
                    onchange={handleDateFromChange}
                    class="w-full min-h-[40px] px-3 py-1.5 text-sm rounded-lg border border-input/80 bg-select-background text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                />
            </div>
        </div>

        <!-- Date to -->
        <div class="flex items-center gap-2 flex-1 sm:max-w-[220px]">
            <span class="text-xs text-muted-foreground shrink-0 font-medium">Hasta:</span>
            <div class="relative w-full">
                <input
                    type="date"
                    bind:value={localDateTo}
                    onchange={handleDateToChange}
                    class="w-full min-h-[40px] px-3 py-1.5 text-sm rounded-lg border border-input/80 bg-select-background text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                />
            </div>
        </div>

        <!-- Warehouse selector: ampliado en Desktop (+10px: sm:w-[350px]), sin texto "Depósito:" ni icono de casa -->
        <div class="flex items-center w-full sm:w-[350px] sm:ml-auto">
            <div class="relative w-full">
                <select
                    value={localWarehouseId ?? ''}
                    onchange={handleWarehouseChange}
                    aria-label="Filtrar por depósito"
                    class="w-full min-h-[40px] px-3.5 py-1.5 text-sm rounded-lg border border-input/80 bg-select-background text-foreground shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer appearance-none pr-8 truncate font-medium"
                >
                    <option value="">Todos los depósitos</option>
                    {#each warehouses as w (w.id)}
                        <option value={w.id}>{w.name} ({w.code})</option>
                    {/each}
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-muted-foreground">
                    <ChevronDown class="size-4 opacity-50" />
                </div>
            </div>
        </div>
    </div>
</div>
