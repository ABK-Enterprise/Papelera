<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import EmptyState from '@/components/common/EmptyState.svelte';
    import ManageAdjustmentModal, { type PriceAdjustmentItem } from '@/components/adjustments/ManageAdjustmentModal.svelte';
    import ConfirmActionModal from '@/components/common/ConfirmActionModal.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import { ClearFilterButton, ModuleHeader } from '@/components/common';
    import AdjustmentListDesktop from './desktop/AdjustmentListDesktop.svelte';
    import AdjustmentListMobile from './mobile/AdjustmentListMobile.svelte';
    import AdjustmentFilterModalMobile from './mobile/AdjustmentFilterModalMobile.svelte';
    import { useTableFilters } from '@/lib/useTableFilters.svelte';
    import { router } from '@inertiajs/svelte';
    import Plus from 'lucide-svelte/icons/plus';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
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

    let isConfirmDeleteOpen = $state(false);
    let adjustmentToDelete = $state<PriceAdjustmentItem | null>(null);
    let isDeleting = $state(false);

    let hasActiveFilters = $derived(tableFilters.hasActiveFilters);
    let activeFiltersCount = $derived(tableFilters.activeFiltersCount);

    function resetFilters() {
        tableFilters.reset();
    }

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

    function requestDelete(adj: PriceAdjustmentItem) {
        adjustmentToDelete = adj;
        isConfirmDeleteOpen = true;
    }

    function confirmDelete() {
        if (!adjustmentToDelete) return;
        isDeleting = true;
        router.delete(`/tenant/configuracion/recargos-descuentos/${adjustmentToDelete.id}`, {
            preserveScroll: true,
            onFinish: () => {
                isDeleting = false;
                isConfirmDeleteOpen = false;
                adjustmentToDelete = null;
            },
        });
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

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1330px] mx-auto w-full">
    <!-- Encabezado del Módulo (Separación homogénea gap-6 sin subtítulos ni elementos decorativos) -->
    <ModuleHeader title="Recargos y Descuentos" badge={adjustments.length}>
        {#snippet actions()}
            <Button
                onclick={openCreateModal}
                class="min-h-[38px] px-4 font-semibold text-sm flex items-center gap-2 rounded-xl shadow-xs cursor-pointer w-full sm:w-auto justify-center"
            >
                <Plus class="h-4 w-4" />
                <span>Nuevo ajuste</span>
            </Button>
        {/snippet}
    </ModuleHeader>

    <!-- Barra Operativa de Búsqueda y Filtros con Persistencia en URL -->
    <Card class="rounded-xl p-3 sm:p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.02)] border border-border/60">
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Buscador Avanzado con Autocompletado y Filtrado Reactivo Automático -->
            <div class="flex-1 max-w-md">
                <SearchCombobox
                    bind:value={tableFilters.filters.search}
                    items={adjustments}
                    searchKeys={['name', 'reason']}
                    placeholder="Buscar por nombre o motivo..."
                    debounceMs={250}
                    onsearch={(q) => {
                        tableFilters.apply({ search: q });
                    }}
                    onselect={(adj) => {
                        tableFilters.apply({ search: adj.name });
                    }}
                    onclear={() => {
                        tableFilters.apply({ search: '' });
                    }}
                    class="w-full"
                    inputClass="min-h-[44px] sm:min-h-[40px] text-sm font-medium rounded-xl"
                >
                    {#snippet itemSnippet(adj)}
                        <div class="flex items-center justify-between w-full py-0.5">
                            <div class="flex flex-col min-w-0 text-left">
                                <span class="truncate font-semibold text-foreground text-sm">
                                    <HighlightMatch text={adj.name} query={tableFilters.filters.search} />
                                </span>
                                {#if adj.reason}
                                    <span class="text-xs text-muted-foreground truncate">
                                        <HighlightMatch text={adj.reason} query={tableFilters.filters.search} />
                                    </span>
                                {/if}
                            </div>
                            <span class={`text-xs font-bold shrink-0 ml-3 tabular-nums ${adj.type === 'discount' ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'}`}>
                                {adj.calculation_type === 'percentage' ? `${Number(adj.value)}%` : `$${Number(adj.value).toLocaleString('es-AR')}`}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Filtros Desktop con Escala Tipográfica Aumentada text-sm font-semibold -->
            <div class="hidden md:flex items-center gap-2.5">
                <!-- Filtro de Tipo -->
                <div class="flex items-center rounded-xl border border-border/80 dark:border-border/90 bg-muted/40 dark:bg-secondary/35 p-1">
                    <button
                        type="button"
                        onclick={() => setTypeFilter('')}
                        class={`min-h-[36px] px-3.5 rounded-lg text-sm font-semibold transition-all cursor-pointer ${
                            !tableFilters.filters.type
                                ? 'bg-card dark:bg-secondary text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Todos
                    </button>
                    <button
                        type="button"
                        onclick={() => setTypeFilter('discount')}
                        class={`min-h-[36px] px-3.5 rounded-lg text-sm font-semibold transition-all cursor-pointer ${
                            tableFilters.filters.type === 'discount'
                                ? 'bg-emerald-700 hover:bg-emerald-800 text-white shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        Descuentos
                    </button>
                    <button
                        type="button"
                        onclick={() => setTypeFilter('surcharge')}
                        class={`min-h-[36px] px-3.5 rounded-lg text-sm font-semibold transition-all cursor-pointer ${
                            tableFilters.filters.type === 'surcharge'
                                ? 'bg-amber-700 hover:bg-amber-800 text-white shadow-2xs'
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
                    class={`min-h-[40px] px-3.5 rounded-xl border text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer ${
                        tableFilters.filters.status === 'active'
                            ? 'bg-primary/15 text-foreground dark:text-sky-100 border-primary/40 hover:bg-primary/25 shadow-2xs'
                            : 'bg-card text-muted-foreground border-border/80 dark:border-border/90 hover:bg-muted/70 dark:hover:bg-secondary/55'
                    }`}
                >
                    <CheckCircle2 class="h-4 w-4" />
                    <span>Solo activos</span>
                </button>

                <ClearFilterButton show={hasActiveFilters} onclick={resetFilters} />
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
                ondelete={requestDelete}
            />
        </div>

        <!-- Vista Mobile -->
        <div class="block md:hidden">
            <AdjustmentListMobile
                {adjustments}
                onedit={openEditModal}
                ontoggle={toggleActive}
                ondelete={requestDelete}
            />
        </div>
    {:else}
        <!-- Estado Vacío -->
        <div class="rounded-2xl border border-dashed border-border/80 dark:border-border/90 p-8 sm:p-12 text-center bg-muted/30 dark:bg-secondary/20">
            <div class="mx-auto h-12 w-12 rounded-2xl bg-muted/50 dark:bg-secondary/40 flex items-center justify-center text-muted-foreground mb-4">
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

<!-- Modal de Confirmación de Eliminación -->
<ConfirmActionModal
    bind:open={isConfirmDeleteOpen}
    title="¿Eliminar este ajuste?"
    description={`Se eliminará permanentemente la regla "${adjustmentToDelete?.name || ''}". Esta acción no se puede deshacer.`}
    confirmText="Eliminar"
    cancelText="Cancelar"
    variant="danger"
    isLoading={isDeleting}
    onconfirm={confirmDelete}
/>

<!-- Modal de Filtros Móvil -->
<AdjustmentFilterModalMobile
    bind:open={isMobileFilterOpen}
    selectedType={tableFilters.filters.type}
    selectedStatus={tableFilters.filters.status}
    onapply={applyMobileFilters}
    onreset={() => tableFilters.reset()}
/>
