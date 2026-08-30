<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import KardexDetailModal from '@/pages/tenant/stock/kardex/KardexDetailModal.svelte';
    import KardexListDesktop from '@/pages/tenant/stock/kardex/desktop/KardexListDesktop.svelte';
    import KardexListMobile from '@/pages/tenant/stock/kardex/mobile/KardexListMobile.svelte';
    import StockNavigationTabs from '@/pages/tenant/stock/StockNavigationTabs.svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import { router } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';

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

    $effect(() => {
        searchInput = filters?.search ?? '';
        typeFilter = filters?.type ?? '';
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

    function handleTypeChange(e: Event) {
        const target = e.target as HTMLSelectElement;
        typeFilter = target.value;
        applyFilters();
    }

    function openDetailModal(mov: StockMovementItem) {
        selectedMovement = mov;
        isDetailModalOpen = true;
    }
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
            <div class="flex-1 min-w-[240px]">
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
                                <span class="text-[11px] text-muted-foreground tabular-nums shrink-0">SKU: {prod.sku}</span>
                            {/if}
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>
        </div>

        <div class="flex items-center justify-between lg:justify-end gap-2 text-xs text-muted-foreground font-medium pt-1 lg:pt-0">
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
