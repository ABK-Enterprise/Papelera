<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import Box from 'lucide-svelte/icons/box';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import PackageSearch from 'lucide-svelte/icons/package-search';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';
    import WarehouseLookupFilterModalMobile from './WarehouseLookupFilterModalMobile.svelte';
    import type { WarehouseItem, WarehouseOption, CategoryOption } from '../desktop/WarehouseLookupDesktop.svelte';

    let {
        items,
        warehouses = [],
        categories = [],
        searchTerm = $bindable(''),
        selectedWarehouseId = $bindable(''),
        selectedCategoryId = $bindable(''),
        selectedStockStatus = $bindable(''),
        onsearch,
        onfilterchange,
        onreset,
    }: {
        items: {
            data: WarehouseItem[];
            links: any[];
            current_page: number;
            last_page: number;
            total: number;
            from: number;
            to: number;
        };
        warehouses: WarehouseOption[];
        categories: CategoryOption[];
        searchTerm?: string;
        selectedWarehouseId?: string | number | null;
        selectedCategoryId?: string | number | null;
        selectedStockStatus?: string | null;
        onsearch?: (query: string) => void;
        onfilterchange?: () => void;
        onreset?: () => void;
    } = $props();

    let isFilterModalOpen = $state(false);

    const hasActiveFilters = $derived(
        Boolean(searchTerm || selectedWarehouseId || selectedCategoryId || selectedStockStatus)
    );

    const activeFiltersCount = $derived(
        (selectedWarehouseId ? 1 : 0) +
        (selectedCategoryId ? 1 : 0) +
        (selectedStockStatus ? 1 : 0)
    );

    function formatNumber(value: number | string, isWeight: boolean): string {
        const num = Number(value) || 0;
        return isWeight ? num.toFixed(2) : Math.round(num).toString();
    }

    function handleApplyMobileFilters(filters: { warehouse_id: string | number; category_id: string | number; stock_status: string }) {
        selectedWarehouseId = filters.warehouse_id;
        selectedCategoryId = filters.category_id;
        selectedStockStatus = filters.stock_status;
        onfilterchange?.();
    }
</script>

<div class="flex md:hidden flex-col gap-4 w-full font-sans">
    <!-- Header Mobile: Título a la izquierda, Botón secundario a la derecha -->
    <div class="flex items-center justify-between gap-2">
        <h1 class="text-xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <WarehouseIcon class="size-6 text-primary" />
            <span>Buscar en Depósito</span>
        </h1>

        <div class="flex items-center justify-end ml-auto">
            <Link href="/tenant/productos">
                <Button variant="secondary" size="sm" class="min-h-[36px] px-3 gap-1.5 rounded-xl text-xs font-semibold shadow-2xs">
                    <Box class="size-3.5 text-primary" />
                    <span>catálogo de productos</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Barra de Búsqueda y Botón Filtros Mobile -->
    <Card class="rounded-xl p-3 shadow-2xs border border-border/60">
        <div class="flex items-center gap-2">
            <div class="flex-1 min-w-0">
                <SearchCombobox
                    bind:value={searchTerm}
                    items={items.data}
                    searchKeys={['product_name', 'product_sku', 'warehouse_name', 'warehouse_code', 'aisle_location']}
                    placeholder="Buscar producto, SKU, depósito..."
                    onsearch={(q) => {
                        searchTerm = q;
                        onsearch?.(q);
                    }}
                    onclear={onreset}
                >
                    {#snippet itemSnippet(item)}
                        <div class="flex items-center justify-between gap-2 w-full min-w-0">
                            <div class="flex flex-col min-w-0">
                                <span class="truncate font-medium text-foreground text-xs">{item.product_name}</span>
                                <span class="text-[10px] text-muted-foreground">{item.warehouse_name} • SKU: {item.product_sku || 'N/A'}</span>
                            </div>
                            <span class="text-[11px] font-semibold tabular-nums text-primary shrink-0">
                                {formatNumber(item.stock, item.product_sale_type === 'weight')} {item.product_sale_type === 'weight' ? 'kg' : 'u.'}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <Button
                variant={activeFiltersCount > 0 ? 'default' : 'outline'}
                size="icon"
                onclick={() => (isFilterModalOpen = true)}
                class="size-10 rounded-xl relative shrink-0"
                aria-label="Abrir filtros de búsqueda"
            >
                <SlidersHorizontal class="size-4" />
                {#if activeFiltersCount > 0}
                    <span class="absolute -top-1 -right-1 size-4 bg-primary text-primary-foreground text-[10px] font-bold rounded-full flex items-center justify-center">
                        {activeFiltersCount}
                    </span>
                {/if}
            </Button>
        </div>

        {#if hasActiveFilters}
            <div class="pt-2 flex items-center justify-between border-t border-border/40 mt-2">
                <span class="text-xs text-muted-foreground">Filtros aplicados</span>
                <Button
                    variant="ghost"
                    size="sm"
                    onclick={onreset}
                    class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground gap-1"
                >
                    <RotateCcw class="size-3" />
                    Limpiar
                </Button>
            </div>
        {/if}
    </Card>

    <!-- Modal de Filtros Mobile -->
    <WarehouseLookupFilterModalMobile
        bind:open={isFilterModalOpen}
        {warehouses}
        {categories}
        {selectedWarehouseId}
        {selectedCategoryId}
        {selectedStockStatus}
        onapply={handleApplyMobileFilters}
        onreset={onreset}
    />

    <!-- Lista de Cards en Mobile -->
    {#if items.data.length === 0}
        <Card class="p-8 text-center border-dashed border-border/80 rounded-2xl">
            <div class="flex flex-col items-center justify-center">
                <div class="size-12 rounded-2xl bg-muted/60 flex items-center justify-center mb-3 text-muted-foreground">
                    <PackageSearch class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-foreground mb-1">No se encontraron productos</h3>
                <p class="text-xs text-muted-foreground mb-3">
                    {hasActiveFilters ? 'No hay existencias coincidentes con los filtros.' : 'No hay existencias registradas.'}
                </p>
                {#if hasActiveFilters}
                    <Button variant="outline" size="sm" onclick={onreset} class="gap-1.5 text-xs">
                        <RotateCcw class="size-3" />
                        Restablecer filtros
                    </Button>
                {/if}
            </div>
        </Card>
    {:else}
        <div class="flex flex-col gap-3">
            {#each items.data as item (item.id)}
                {@const isWeight = item.product_sale_type === 'weight'}
                {@const unitLabel = isWeight ? 'kg' : 'u.'}
                {@const isLowStock = Number(item.stock) <= Number(item.min_stock) && Number(item.stock) > 0}
                {@const isOutOfStock = Number(item.stock) <= 0}

                <Card class="rounded-2xl p-4 border border-border/60 shadow-2xs flex flex-col justify-between gap-3 bg-card">
                    <!-- Cabecera de la Card -->
                    <div class="space-y-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm text-foreground truncate" title={item.product_name}>
                                    {item.product_name}
                                </h3>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    {#if item.product_sku}
                                        <Badge variant="outline" class="text-[10px] font-mono px-1 py-0">
                                            {item.product_sku}
                                        </Badge>
                                    {/if}
                                    {#if item.category_name}
                                        <span class="text-[11px] text-muted-foreground truncate">
                                            {item.category_name}
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Badge de Estado de Stock en este depósito -->
                            {#if isOutOfStock}
                                <Badge variant="destructive" class="text-[10px] font-semibold shrink-0">
                                    Sin Stock
                                </Badge>
                            {:else if isLowStock}
                                <Badge class="bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 text-[10px] font-semibold shrink-0">
                                    Stock Bajo
                                </Badge>
                            {:else}
                                <Badge variant="secondary" class="text-[10px] font-semibold shrink-0 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20">
                                    Disponible
                                </Badge>
                            {/if}
                        </div>

                        <!-- Información de Ubicación Física -->
                        <div class="pt-1.5 border-t border-border/40 flex items-center justify-between text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5 font-medium text-foreground">
                                <Building2 class="size-3.5 text-primary shrink-0" />
                                <span class="truncate">{item.warehouse_name}</span>
                            </div>
                            {#if item.aisle_location}
                                <div class="flex items-center gap-1 text-[10px] bg-muted/60 px-1.5 py-0.5 rounded font-mono">
                                    <MapPin class="size-3 text-muted-foreground" />
                                    <span>{item.aisle_location}</span>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Pie de la Card: Mismo tamaño de fuente para stock depósito y stock comercio -->
                    <div class="pt-2.5 border-t border-border/50 flex items-center justify-between bg-muted/20 -mx-4 -mb-4 px-4 py-2.5 rounded-b-2xl">
                        <div class="flex flex-col">
                            <span class="text-xs font-normal text-muted-foreground">Stock en este depósito</span>
                            <span class="text-sm font-semibold tabular-nums text-foreground">
                                {formatNumber(item.stock, isWeight)} {unitLabel}
                            </span>
                        </div>
                        <div class="flex flex-col text-right">
                            <span class="text-xs font-normal text-muted-foreground">Stock total comercio</span>
                            <span class="text-sm font-semibold tabular-nums text-muted-foreground">
                                {formatNumber(item.total_consolidated_stock, isWeight)} {unitLabel}
                            </span>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>

        <!-- Paginación Mobile -->
        {#if items.last_page > 1}
            <div class="pt-2 flex flex-col items-center gap-2">
                <p class="text-[11px] text-muted-foreground">
                    Mostrando <span class="font-medium text-foreground tabular-nums">{items.from}</span> - <span class="font-medium text-foreground tabular-nums">{items.to}</span> de <span class="font-medium text-foreground tabular-nums">{items.total}</span>
                </p>
                <AppPagination links={items.links} />
            </div>
        {/if}
    {/if}
</div>
