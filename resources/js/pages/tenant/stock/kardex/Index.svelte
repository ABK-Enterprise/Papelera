<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import KardexDetailModal from '@/pages/tenant/stock/kardex/KardexDetailModal.svelte';
    import KardexFilterModalMobile from './mobile/KardexFilterModalMobile.svelte';
    import KardexListDesktop from '@/pages/tenant/stock/kardex/desktop/KardexListDesktop.svelte';
    import KardexListMobile from '@/pages/tenant/stock/kardex/mobile/KardexListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import { ClearFilterButton } from '@/components/common';
    import type { PaginationMeta } from '@/components/common/AppPagination.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import { router, page } from '@inertiajs/svelte';
    import { echo } from '@/lib/echo';
    import { toast } from 'svelte-sonner';


    interface StockMovementItem {
        id: number;
        type: string;
        reason: string;
        quantity: number;
        previous_stock: number;
        current_stock: number;
        notes: string | null;
        created_at: string;
        product?: {
            id: number;
            name: string;
            sku: string | null;
            barcode: string | null;
        } | null;
        user?: {
            id: number;
            name: string;
            email?: string;
        } | null;
    }

    interface ProductItem {
        id: number;
        name: string;
        sku: string | null;
    }

    interface Filters {
        product_id?: number | null;
        type?: string | null;
        search?: string | null;
        date_from?: string | null;
        date_to?: string | null;
        sort_by?: string | null;
    }

    let {
        movements = [],
        products = [],
        stats = { total_incomes: 0, total_outcomes: 0, net_balance: 0 },
        filters = { search: '', type: '', product_id: null, date_from: '', date_to: '', sort_by: 'latest' },
    }: {
        movements: (PaginationMeta & { data: StockMovementItem[] }) | StockMovementItem[];
        products?: ProductItem[];
        stats?: { total_incomes: number; total_outcomes: number; net_balance: number };
        filters?: Filters;
    } = $props();

    let movementItems = $derived(Array.isArray(movements) ? movements : (movements?.data ?? []));
    let movementPagination = $derived(Array.isArray(movements) ? null : movements);
    let suggestedProducts = $derived.by(() => {
        if (products && products.length > 0) return products;
        const seen = new Set<number>();
        const list: ProductItem[] = [];
        for (const m of movementItems) {
            if (m.product && !seen.has(m.product.id)) {
                seen.add(m.product.id);
                list.push({ id: m.product.id, name: m.product.name, sku: m.product.sku });
            }
        }
        return list;
    });

    let selectedMovement = $state<StockMovementItem | null>(null);
    let isDetailModalOpen = $state(false);

    let searchInput = $state('');
    let typeFilter = $state('');
    let dateFrom = $state('');
    let dateTo = $state('');
    let sortBy = $state('latest');
    let isFilterModalOpen = $state(false);

    $effect(() => {
        searchInput = filters?.search ?? '';
        typeFilter = filters?.type ?? '';
        dateFrom = filters?.date_from ?? '';
        dateTo = filters?.date_to ?? '';
        sortBy = filters?.sort_by ?? 'latest';
    });

    let tenantId = $derived(
        Number(page.props.auth?.tenant?.id ?? page.props.auth?.user?.tenant_id ?? 1)
    );

    // Sincronización en tiempo real vía Laravel Reverb
    $effect(() => {
        const stockChannel = `tenant.${tenantId}.stock`;
        const alertChannel = `tenant.${tenantId}.stock-alerts`;

        echo.private(stockChannel)
            .listen('.StockMovementRecorded', () => {
                router.reload({ only: ['movements', 'stats'], preserveScroll: true, preserveState: true });
            });

        echo.private(alertChannel)
            .listen('.LowStockAlertTriggered', (event: any) => {
                toast.warning(`Alerta de Stock Crítico: ${event.product_name ?? 'Producto'} (${event.current_stock} u restantes)`, {
                    duration: 8000,
                });
            });

        return () => {
            echo.leave(stockChannel);
            echo.leave(alertChannel);
        };
    });

    function applyFilters() {
        router.get(
            '/tenant/stock/kardex',
            {
                search: searchInput || undefined,
                type: typeFilter || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
                sort_by: sortBy !== 'latest' ? sortBy : undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }

    function resetFilters() {
        searchInput = '';
        typeFilter = '';
        dateFrom = '';
        dateTo = '';
        sortBy = 'latest';
        router.get('/tenant/stock/kardex');
    }

    function handleTypeChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        typeFilter = target.value;
        applyFilters();
    }

    function handleSortChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        sortBy = target.value;
        applyFilters();
    }

    function handleDateFromChange(e: Event) {
        const target = e.target as HTMLInputElement;
        dateFrom = target.value;
        applyFilters();
    }

    function handleDateToChange(e: Event) {
        const target = e.target as HTMLInputElement;
        dateTo = target.value;
        applyFilters();
    }

    function openDetailModal(mov: StockMovementItem) {
        selectedMovement = mov;
        isDetailModalOpen = true;
    }

    let hasActiveFilters = $derived(
        Boolean(searchInput || typeFilter || dateFrom || dateTo || (sortBy && sortBy !== 'latest'))
    );
    let activeFiltersCount = $derived(
        (typeFilter ? 1 : 0) + (dateFrom || dateTo ? 1 : 0) + (sortBy && sortBy !== 'latest' ? 1 : 0)
    );
</script>

<AppHead title="Kardex de Movimientos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1460px] mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="kardex" />

    <!-- Page Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">Kardex e Historial</h1>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-3.5 bg-card/60 backdrop-blur-sm p-3.5 rounded-xl border border-border/60 shadow-xs">
        <div class="flex flex-wrap items-center gap-2.5 flex-1 w-full min-w-0">
            <!-- Buscador por Producto / SKU + Disparador Mobile -->
            <div class="flex-1 min-w-[240px]">
                <SearchCombobox
                    bind:value={searchInput}
                    items={suggestedProducts}
                    debounceMs={300}
                    placeholder="Buscar por producto, SKU o código de barras..."
                    onsearch={applyFilters}
                    onselect={(prod) => {
                        searchInput = prod.name;
                        applyFilters();
                    }}
                >
                    {#snippet itemSnippet(prod)}
                        <div class="flex items-center justify-between gap-3 w-full min-w-0">
                            <span class="truncate font-medium text-foreground">{prod.name}</span>
                            {#if prod.sku}
                                <span class="text-xs text-muted-foreground tabular-nums shrink-0">SKU: {prod.sku}</span>
                            {/if}
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Disparador de Filtros Mobile -->
            <div class="block md:hidden shrink-0">
                <Button
                    type="button"
                    variant={activeFiltersCount > 0 ? 'secondary' : 'outline'}
                    size="icon"
                    onclick={() => (isFilterModalOpen = true)}
                    class="relative h-10 w-10 shrink-0 rounded-xl border border-border/70 shadow-2xs"
                    aria-label="Abrir filtros"
                >
                    <SlidersHorizontal class="h-4 w-4 text-foreground" />
                    {#if activeFiltersCount > 0}
                        <span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-primary-foreground tabular-nums shadow-xs">
                            {activeFiltersCount}
                        </span>
                    {/if}
                </Button>
            </div>

            <!-- Filtros Desktop -->
            <div class="hidden md:flex flex-wrap items-center gap-2.5">
                <div class="relative min-w-[150px]">
                    <select
                        value={typeFilter}
                        onchange={handleTypeChange}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs h-10"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="income">Solo Ingresos</option>
                        <option value="outcome">Solo Egresos</option>
                    </select>
                </div>

                <!-- Selector de Ordenamiento -->
                <div class="relative min-w-[160px]">
                    <select
                        value={sortBy}
                        onchange={handleSortChange}
                        class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs h-10"
                    >
                        <option value="latest">Más recientes</option>
                        <option value="oldest">Más antiguos</option>
                        <option value="quantity_high">Mayor cantidad</option>
                        <option value="quantity_low">Menor cantidad</option>
                    </select>
                </div>

                <!-- Rango de Fechas -->
                <div class="flex items-center gap-1.5">
                    <input
                        type="date"
                        value={dateFrom}
                        onchange={handleDateFromChange}
                        title="Fecha desde"
                        class="rounded-lg border border-border bg-background px-2.5 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs h-10"
                    />
                    <span class="text-xs text-muted-foreground">-</span>
                    <input
                        type="date"
                        value={dateTo}
                        onchange={handleDateToChange}
                        title="Fecha hasta"
                        class="rounded-lg border border-border bg-background px-2.5 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs h-10"
                    />
                </div>

                <ClearFilterButton show={hasActiveFilters} onclick={resetFilters} />
            </div>
        </div>
    </div>

    <!-- Desglose Responsive Desktop & Mobile -->
    <div class="hidden md:block">
        <KardexListDesktop movements={movementItems} pagination={movementPagination} {stats} onSelectMovement={openDetailModal} />
    </div>
    <div class="block md:hidden">
        <KardexListMobile movements={movementItems} pagination={movementPagination} onSelectMovement={openDetailModal} />
    </div>
</div>

<!-- Modal de Detalle de Movimiento -->
<KardexDetailModal
    bind:open={isDetailModalOpen}
    movement={selectedMovement}
/>

<!-- Modal de Filtros Mobile -->
<KardexFilterModalMobile
    bind:open={isFilterModalOpen}
    selectedType={typeFilter}
    selectedDateFrom={dateFrom}
    selectedDateTo={dateTo}
    selectedSortBy={sortBy}
    onapply={(f) => {
        typeFilter = f.type;
        dateFrom = f.date_from;
        dateTo = f.date_to;
        sortBy = f.sort_by;
        applyFilters();
    }}
    onreset={resetFilters}
/>
