<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Card } from '@/components/ui/card';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ManageAdjustmentModal, { type PriceAdjustmentItem } from '@/components/adjustments/ManageAdjustmentModal.svelte';
    import AdjustmentListDesktop from './desktop/AdjustmentListDesktop.svelte';
    import AdjustmentListMobile from './mobile/AdjustmentListMobile.svelte';
    import AdjustmentFilterModalMobile from './mobile/AdjustmentFilterModalMobile.svelte';
    import { useTableFilters } from '@/lib/useTableFilters.svelte';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import Search from 'lucide-svelte/icons/search';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import CheckCircle2 from 'lucide-svelte/icons/check-circle-2';
    import Tag from 'lucide-svelte/icons/tag';

    interface FilterProps {
        search?: string | null;
        type?: string | null;
        status?: string | null;
    }

    let {
        adjustments = [],
        filters = {},
    }: {
        adjustments?: PriceAdjustmentItem[];
        filters?: FilterProps;
    } = $props();

    const tableFilters = useTableFilters({
        basePath: '/tenant/configuracion/recargos-descuentos',
        defaultFilters: {
            search: '',
            type: '',
            status: '',
        },
        serverFilters: () => filters,
    });

    let isManageModalOpen = $state(false);
    let isMobileFilterOpen = $state(false);
    let selectedAdjustment = $state<PriceAdjustmentItem | null>(null);

    let hasActiveFilters = $derived(tableFilters.hasActiveFilters);
    let activeFiltersCount = $derived(tableFilters.activeFiltersCount);

    function openCreateModal() {
        selectedAdjustment = null;
        isManageModalOpen = true;
    }

    function openEditModal(adj: PriceAdjustmentItem) {
        selectedAdjustment = adj;
        isManageModalOpen = true;
    }

    function toggleActive(adj: PriceAdjustmentItem) {
        router.patch(`/tenant/configuracion/recargos-descuentos/${adj.id}/alternar`, {}, {
            preserveScroll: true,
        });
    }

    function handleSearchKeydown(e: KeyboardEvent) {
        if (e.key === 'Enter') {
            e.preventDefault();
            tableFilters.apply();
        }
    }

    function setTypeFilter(typeVal: string) {
        tableFilters.filters.type = typeVal;
        tableFilters.apply();
    }

    function toggleStatusFilter() {
        tableFilters.filters.status = tableFilters.filters.status === 'active' ? '' : 'active';
        tableFilters.apply();
    }

    function applyMobileFilters(f: { type: string; status: string }) {
        tableFilters.filters.type = f.type;
        tableFilters.filters.status = f.status;
        tableFilters.apply();
    }
</script>

<AppHead title="Recargos y Descuentos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado del Módulo (Separación homogénea gap-6 sin métricas decorativas) -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pb-2 border-b border-border/70">
        <div>
            <div class="flex items-center gap-2.5">
                <div class="h-8 w-8 rounded-xl bg-primary-500/10 text-primary flex items-center justify-center shrink-0">
                    <Sparkles class="h-4 w-4" />
                </div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground">
                    Recargos y Descuentos
                </h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary tabular-nums">
                    {adjustments.length} {adjustments.length === 1 ? 'ajuste' : 'ajustes'}
                </span>
            </div>
            <p class="mt-1 text-xs sm:text-sm text-muted-foreground">
                Reglas automáticas y promociones por fecha para aplicar en pedidos comerciales.
            </p>
        </div>

        <div class="flex items-center justify-end gap-2.5">
            <Button
                onclick={openCreateModal}
                class="min-h-[44px] sm:min-h-[40px] px-4 font-semibold text-xs flex items-center gap-2 rounded-xl shadow-xs cursor-pointer w-full sm:w-auto justify-center"
            >
                <Plus class="h-4 w-4" />
                <span>Nuevo ajuste</span>
            </Button>
        </div>
    </div>

    <!-- Barra Operativa de Búsqueda y Filtros con Persistencia en URL -->
    <Card class="rounded-xl p-3 sm:p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Input de búsqueda -->
            <div class="relative flex-1 max-w-md">
                <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
                <Input
                    type="text"
                    placeholder="Buscar por nombre o motivo... (Enter para buscar)"
                    bind:value={tableFilters.filters.search}
                    onkeydown={handleSearchKeydown}
                    class="pl-10 min-h-[44px] sm:min-h-[40px] text-sm font-medium rounded-xl"
                />
            </div>

            <!-- Filtros Desktop -->
            <div class="hidden md:flex items-center gap-2.5">
                <!-- Filtro de Tipo -->
                <div class="flex items-center rounded-xl border border-border/80 bg-muted/30 p-1">
                    <button
                        type="button"
                        onclick={() => setTypeFilter('')}
                        class={`min-h-[34px] px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                            !tableFilters.filters.type
                                ? 'bg-background text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        onclick={() => setTypeFilter('discount')}
                        class={`min-h-[34px] px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                            tableFilters.filters.type === 'discount'
                                ? 'bg-emerald-500 text-white shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Descuentos
                    </button>
                    <button
                        type="button"
                        onclick={() => setTypeFilter('surcharge')}
                        class={`min-h-[34px] px-3 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                            tableFilters.filters.type === 'surcharge'
                                ? 'bg-amber-600 text-white shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Recargos
                    </button>
                </div>

                <!-- Filtro Activos -->
                <button
                    type="button"
                    onclick={toggleStatusFilter}
                    class={`min-h-[38px] px-3 rounded-xl border text-xs font-semibold transition-all flex items-center gap-1.5 cursor-pointer ${
                        tableFilters.filters.status === 'active'
                            ? 'bg-primary/10 text-primary border-primary/30 shadow-2xs'
                            : 'bg-background text-muted-foreground border-border/80 hover:bg-muted'
                    }`}
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    <span>Solo activos</span>
                </button>

                {#if hasActiveFilters}
                    <Button
                        variant="ghost"
                        size="sm"
                        onclick={() => tableFilters.reset()}
                        class="min-h-[38px] px-2.5 text-xs text-muted-foreground hover:text-foreground flex items-center gap-1.5 cursor-pointer"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        <span>Limpiar</span>
                    </Button>
                {/if}
            </div>

            <!-- Controles Móvil: Botón de Filtros Avanzados Modal + Limpiar -->
            <div class="flex md:hidden items-center justify-between gap-2 pt-1">
                <Button
                    variant="outline"
                    onclick={() => (isMobileFilterOpen = true)}
                    class="min-h-[44px] flex-1 rounded-xl text-xs font-semibold flex items-center justify-center gap-2 border-border/80 shadow-2xs"
                >
                    <SlidersHorizontal class="h-4 w-4" />
                    <span>Filtros</span>
                    {#if activeFiltersCount > 0}
                        <span class="h-5 w-5 rounded-full bg-primary text-primary-foreground text-2xs font-bold flex items-center justify-center">
                            {activeFiltersCount}
                        </span>
                    {/if}
                </Button>

                {#if hasActiveFilters}
                    <Button
                        variant="ghost"
                        onclick={() => tableFilters.reset()}
                        class="min-h-[44px] px-3 rounded-xl text-xs font-semibold text-muted-foreground flex items-center gap-1.5"
                    >
                        <RotateCcw class="h-4 w-4" />
                        <span>Limpiar</span>
                    </Button>
                {/if}
            </div>
        </div>
    </Card>

    <!-- Lista de Wide Cards (Desglose estricto Desktop / Mobile) -->
    {#if adjustments.length > 0}
        <!-- Vista Desktop -->
        <div class="hidden md:block">
            <AdjustmentListDesktop
                {adjustments}
                onedit={openEditModal}
                ontoggle={toggleActive}
            />
        </div>

        <!-- Vista Mobile -->
        <div class="block md:hidden">
            <AdjustmentListMobile
                {adjustments}
                onedit={openEditModal}
                ontoggle={toggleActive}
            />
        </div>
    {:else}
        <!-- Estado Vacío -->
        <div class="rounded-2xl border border-dashed border-border/80 p-8 sm:p-12 text-center bg-card/50">
            <div class="mx-auto h-12 w-12 rounded-2xl bg-muted/60 flex items-center justify-center text-muted-foreground mb-4">
                <Tag class="h-6 w-6" />
            </div>
            <h3 class="text-base font-bold text-foreground">
                {#if hasActiveFilters}
                    No se encontraron ajustes con los criterios seleccionados
                {:else}
                    Aún no hay recargos ni descuentos configurados
                {/if}
            </h3>
            <p class="text-xs text-muted-foreground mt-1.5 max-w-sm mx-auto">
                {#if hasActiveFilters}
                    Prueba modificando los términos de búsqueda o limpiando los filtros aplicados.
                {:else}
                    Crea reglas automáticas para aplicar descuentos semanales, promociones por fecha o recargos comerciales en tus pedidos.
                {/if}
            </p>
            <div class="mt-5 flex items-center justify-center gap-2">
                {#if hasActiveFilters}
                    <Button
                        variant="outline"
                        onclick={() => tableFilters.reset()}
                        class="min-h-[44px] sm:min-h-[40px] px-4 text-xs font-semibold gap-2 rounded-xl"
                    >
                        <RotateCcw class="h-4 w-4" />
                        <span>Limpiar filtros</span>
                    </Button>
                {:else}
                    <Button
                        onclick={openCreateModal}
                        class="min-h-[44px] sm:min-h-[40px] px-4 text-xs font-semibold gap-2 rounded-xl"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Crear primer ajuste</span>
                    </Button>
                {/if}
            </div>
        </div>
    {/if}
</div>

<!-- Modal de Creación / Edición (Exclusivamente) -->
<ManageAdjustmentModal
    bind:open={isManageModalOpen}
    adjustment={selectedAdjustment}
/>

<!-- Modal de Filtros Móvil -->
<AdjustmentFilterModalMobile
    bind:open={isMobileFilterOpen}
    selectedType={tableFilters.filters.type}
    selectedStatus={tableFilters.filters.status}
    onapply={applyMobileFilters}
    onreset={() => tableFilters.reset()}
/>
