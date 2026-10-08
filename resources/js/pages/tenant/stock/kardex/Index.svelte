<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import KardexDetailModal from '@/pages/tenant/stock/kardex/KardexDetailModal.svelte';
    import KardexFilterModalMobile from './mobile/KardexFilterModalMobile.svelte';
    import KardexListDesktop from '@/pages/tenant/stock/kardex/desktop/KardexListDesktop.svelte';
    import KardexListMobile from '@/pages/tenant/stock/kardex/mobile/KardexListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
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
    }

    let {
        movements = [],
        products = [],
        filters = { search: '', type: '', product_id: null },
    }: {
        movements: StockMovementItem[];
        products: ProductItem[];
        filters?: Filters;
    } = $props();

    let selectedMovement = $state<StockMovementItem | null>(null);
    let isDetailModalOpen = $state(false);

    let searchInput = $state('');
    let typeFilter = $state('');
    let isFilterModalOpen = $state(false);

    $effect(() => {
        searchInput = filters?.search ?? '';
        typeFilter = filters?.type ?? '';
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
                router.reload({ only: ['movements'], preserveScroll: true, preserveState: true });
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
        router.get('/tenant/stock/kardex');
    }

    function handleTypeChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        typeFilter = target.value;
        applyFilters();
    }

    function openDetailModal(mov: StockMovementItem) {
        selectedMovement = mov;
        isDetailModalOpen = true;
    }

    let hasActiveFilters = $derived(Boolean(searchInput || typeFilter));
    let activeFiltersCount = $derived(typeFilter ? 1 : 0);
</script>

<AppHead title="Kardex de Movimientos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-[1360px] mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="kardex" />

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground">Kardex e Historial</h1>
            <p class="mt-1 text-sm text-muted-foreground max-w-prose">
                Auditoría cronológica y trazabilidad de todos los movimientos de inventario de tu comercio.
            </p>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 bg-card/60 backdrop-blur-sm p-3.5 rounded-xl border border-border/60 shadow-xs">
        <div class="flex items-center gap-2.5 flex-1 w-full">
            <!-- Buscador por Producto / SKU + Disparador Mobile -->
            <div class="flex-1 min-w-0">
                <SearchCombobox
                    bind:value={searchInput}
                    items={products}
                    debounceMs={300}
                    placeholder="Buscar por producto, SKU o código de barras..."
                    onsearch={applyFilters}
                    onselect={(prod) => {
                        searchInput = prod.name;
                        applyFilters();
                    }}
                >
                    {#snippet item(prod)}
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
            <div class="hidden md:flex items-center gap-2.5">
                <div class="relative min-w-[160px]">
                    <select
                        value={typeFilter}
                        onchange={handleTypeChange}
                        class="w-full rounded-lg border border-border bg-background px-3.5 py-2 text-xs font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs h-10"
                    >
                        <option value="">Todos los tipos</option>
                        <option value="income">Solo Ingresos</option>
                        <option value="outcome">Solo Egresos</option>
                    </select>
                </div>


                {#if hasActiveFilters}
                    <Button variant="ghost" size="sm" class="h-10 px-2.5 text-xs text-muted-foreground hover:text-foreground hover:bg-muted/50 gap-1.5 rounded-xl transition-all" onclick={resetFilters}>
                        <RotateCcw class="size-3.5" />
                        <span>Limpiar</span>
                    </Button>
                {/if}
            </div>
        </div>

        <div class="flex items-center justify-between lg:justify-end gap-2 text-xs text-muted-foreground font-medium pt-1 lg:pt-0 shrink-0">
            <span>Mostrando <strong class="text-foreground tabular-nums font-semibold">{movements.length}</strong> {movements.length === 1 ? 'movimiento' : 'movimientos'}</span>
        </div>
    </div>

    <!-- Desglose Responsive Desktop & Mobile -->
    <div class="hidden md:block">
        <KardexListDesktop {movements} onSelectMovement={openDetailModal} />
    </div>
    <div class="block md:hidden">
        <KardexListMobile {movements} onSelectMovement={openDetailModal} />
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
    onapply={(f) => {
        typeFilter = f.type;
        applyFilters();
    }}
    onreset={resetFilters}
/>
