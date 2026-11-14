<script lang="ts">
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link } from '@inertiajs/svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import HighlightMatch from '@/components/common/HighlightMatch.svelte';
    import AppPagination from '@/components/common/AppPagination.svelte';

    import Box from 'lucide-svelte/icons/box';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import PackageSearch from 'lucide-svelte/icons/package-search';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';
    import ArrowLeftRight from 'lucide-svelte/icons/arrow-left-right';
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
        ontransfer,
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
        ontransfer?: (item: WarehouseItem) => void;
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

<div class="flex flex-col gap-4 w-full font-sans">
    <!-- Barra de Búsqueda y Botón Filtros Mobile -->
    <Card class="rounded-xl p-3 shadow-2xs border border-border/60">



        <div class="flex items-center gap-2">
            <div class="flex-1 min-w-0">
                <SearchCombobox
                    bind:value={searchTerm}
                    items={items.data}
                    searchKeys={['product_name', 'product_sku', 'product_barcode']}
                    placeholder="Buscar por nombre o SKU..."
                    onsearch={(q) => {
                        searchTerm = q;
                        onsearch?.(q);
                    }}
                    onselect={(item) => {
                        searchTerm = item.product_name;
                        onsearch?.(item.product_name);
                    }}
                    onclear={onreset}
                >
                    {#snippet itemSnippet(item)}
                        <div class="flex items-center justify-between gap-2 w-full min-w-0">
                            <div class="flex flex-col min-w-0 text-left">
                                <span class="truncate font-medium text-foreground text-xs">
                                    <HighlightMatch text={item.product_name} query={searchTerm} />
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {item.warehouse_name} • SKU: <span class="tabular-nums"><HighlightMatch text={item.product_sku || 'N/A'} query={searchTerm} /></span>
                                </span>
                            </div>
                            <span class="text-xs font-semibold tabular-nums text-primary shrink-0">
                                {formatNumber(item.stock, item.product_sale_type === 'weight')} {item.product_sale_type === 'weight' ? 'kg' : 'u.'}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <Button
                variant={activeFiltersCount > 0 ? 'default' : 'secondary'}
                size="icon"
                onclick={() => (isFilterModalOpen = true)}
                class="size-11 min-h-[44px] min-w-[44px] rounded-xl relative shrink-0"
                aria-label="Abrir filtros de búsqueda"
            >
                <SlidersHorizontal class="size-4" />
                {#if activeFiltersCount > 0}
                    <span class="absolute -top-1 -right-1 size-4 bg-primary text-primary-foreground text-xs font-bold rounded-full flex items-center justify-center">
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
        <Card class="p-8 text-center border-dashed border-border/80 dark:border-border/90 rounded-2xl">
            <div class="flex flex-col items-center justify-center">
                <div class="size-12 rounded-2xl bg-muted/60 dark:bg-secondary/50 border border-border/70 flex items-center justify-center mb-3 text-muted-foreground">
                    <PackageSearch class="size-6" />
                </div>
                <h3 class="text-base font-semibold text-foreground mb-1">No se encontraron productos</h3>
                <p class="text-xs text-muted-foreground mb-3">
                    {hasActiveFilters ? 'No hay existencias coincidentes con los filtros.' : 'No hay existencias registradas.'}
                </p>
                {#if hasActiveFilters}
                    <Button variant="secondary" size="sm" onclick={onreset} class="gap-1.5 text-xs rounded-xl">
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

                <Card class="rounded-2xl p-4 border border-border/60 shadow-2xs transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 flex flex-col justify-between gap-3 bg-card">

                    <!-- Cabecera de la Card -->
                    <div class="space-y-1.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm text-foreground truncate" title={item.product_name}>
                                    <HighlightMatch text={item.product_name} query={searchTerm} />
                                </h3>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    {#if item.product_sku}
                                        <Badge variant="outline" class="text-xs tabular-nums px-1.5 py-0">
                                            <HighlightMatch text={item.product_sku} query={searchTerm} />
                                        </Badge>
                                    {/if}

                                    {#if item.category_name}
                                        <span class="text-xs text-muted-foreground truncate">
                                            {item.category_name}
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Badge de Estado de Stock en este depósito -->
                            {#if isOutOfStock}
                                <Badge variant="destructive" class="text-xs font-medium shrink-0">
                                    Sin Stock
                                </Badge>
                            {:else if isLowStock}
                                <Badge class="bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/30 text-xs font-medium shrink-0">
                                    Stock Bajo
                                </Badge>
                            {:else}
                                <Badge variant="secondary" class="text-xs font-medium shrink-0 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/25">
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
                                <div class="flex items-center gap-1 text-xs bg-muted/60 dark:bg-secondary/50 border border-border/60 px-1.5 py-0.5 rounded font-sans tabular-nums">
                                    <MapPin class="size-3 text-muted-foreground" />
                                    <span>{item.aisle_location}</span>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Pie de la Card: Mismo tamaño de fuente para stock depósito y stock comercio -->
                    <div class="pt-2.5 border-t border-border/70 dark:border-border/80 flex items-center justify-between gap-2 bg-muted/40 dark:bg-secondary/35 -mx-4 -mb-4 px-4 py-2.5 rounded-b-2xl">
                        <div class="flex flex-col">
                            <span class="text-xs font-normal text-muted-foreground">Stock en este depósito</span>
                            <span class="text-sm font-semibold tabular-nums text-foreground">
                                {formatNumber(item.stock, isWeight)} {unitLabel}
                            </span>
                        </div>

                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            class="min-h-[36px] h-9 px-2.5 gap-1.5 text-xs font-medium rounded-lg"
                            onclick={() => ontransfer?.(item)}
                            title="Transferir stock de este producto"
                        >
                            <ArrowLeftRight class="size-3 text-primary" />
                            <span>Transferir</span>
                        </Button>

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
                <p class="text-xs text-muted-foreground">
                    Mostrando <span class="font-medium text-foreground tabular-nums">{items.from}</span> - <span class="font-medium text-foreground tabular-nums">{items.to}</span> de <span class="font-medium text-foreground tabular-nums">{items.total}</span>
                </p>
                <AppPagination links={items.links} />
            </div>
        {/if}
    {/if}
</div>
