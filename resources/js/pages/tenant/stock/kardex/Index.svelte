<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import KardexDetailModal from '@/pages/tenant/stock/kardex/KardexDetailModal.svelte';
    import KardexListDesktop from '@/pages/tenant/stock/kardex/desktop/KardexListDesktop.svelte';
    import KardexListMobile from '@/pages/tenant/stock/kardex/mobile/KardexListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import { router } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Search from 'lucide-svelte/icons/search';
    import X from 'lucide-svelte/icons/x';

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

    let searchInput = $state(filters.search ?? '');
    let typeFilter = $state(filters.type ?? '');

    $effect(() => {
        searchInput = filters.search ?? '';
        typeFilter = filters.type ?? '';
    });

    let debounceTimer: ReturnType<typeof setTimeout> | undefined;

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

    function handleSearchInput(e: Event) {
        const target = e.target as HTMLInputElement;
        searchInput = target.value;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            applyFilters();
        }, 300);
    }

    function handleTypeChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        typeFilter = target.value;
        applyFilters();
    }

    function clearSearch() {
        searchInput = '';
        applyFilters();
    }

    function openDetailModal(mov: StockMovementItem) {
        selectedMovement = mov;
        isDetailModalOpen = true;
    }
</script>

<AppHead title="Kardex de Movimientos - Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Sub-Navegación de Pestañas de Stock y Productos -->
    <StockNavigationTabs activeTab="kardex" />

    <!-- Page Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Kardex / Historial</h1>
            <p class="mt-1.5 text-sm text-muted-foreground sm:text-base max-w-2xl">
                Auditoría cronológica y trazabilidad de todos los movimientos de inventario de tu comercio.
            </p>
        </div>
    </div>

    <!-- Filter / Action Toolbar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 bg-card/60 backdrop-blur-sm p-3.5 rounded-xl border border-border/60 shadow-xs">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <!-- Tipo de Movimiento Select -->
            <div class="relative min-w-[160px]">
                <select
                    value={typeFilter}
                    onchange={handleTypeChange}
                    class="w-full appearance-none rounded-lg border border-border bg-background px-3.5 py-2 pr-9 text-xs sm:text-sm font-medium text-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                >
                    <option value="">Todos los tipos</option>
                    <option value="income">Solo Ingresos</option>
                    <option value="outcome">Solo Egresos</option>
                </select>
                <ChevronDown class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
            </div>

            <!-- Buscador por Producto / SKU -->
            <div class="relative flex-1 min-w-[240px]">
                <Search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <input
                    type="text"
                    value={searchInput}
                    oninput={handleSearchInput}
                    placeholder="Buscar por producto, SKU o código de barras..."
                    class="w-full rounded-lg border border-border bg-background pl-9 pr-8 py-2 text-xs sm:text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary shadow-2xs"
                />
                {#if searchInput}
                    <button
                        type="button"
                        onclick={clearSearch}
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        aria-label="Limpiar búsqueda"
                    >
                        <X class="size-3.5" />
                    </button>
                {/if}
            </div>
        </div>

        <div class="flex items-center justify-between lg:justify-end gap-2 text-xs text-muted-foreground font-medium pt-1 lg:pt-0">
            <span>Mostrando {movements.length} {movements.length === 1 ? 'movimiento' : 'movimientos'}</span>
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
