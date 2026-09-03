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
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';

    export interface WarehouseItem {
        id: number;
        product_id: number;
        warehouse_id: number;
        stock: number;
        min_stock: number;
        aisle_location: string | null;
        product_name: string;
        product_sku: string | null;
        product_barcode: string | null;
        product_sale_type: string;
        product_price: number | string;
        total_consolidated_stock: number;
        warehouse_name: string;
        warehouse_code: string;
        category_name: string | null;
    }

    export interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default: boolean;
    }

    export interface CategoryOption {
        id: number;
        name: string;
    }

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

    const hasActiveFilters = $derived(
        Boolean(searchTerm || selectedWarehouseId || selectedCategoryId || selectedStockStatus)
    );

    function formatNumber(value: number | string, isWeight: boolean): string {
        const num = Number(value) || 0;
        return isWeight ? num.toFixed(2) : Math.round(num).toString();
    }
</script>

<div class="flex flex-col gap-6 w-full font-sans">
    <!-- Barra de Búsqueda y Filtros de Depósito -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60">


        <div class="flex items-center justify-between gap-3">
            <!-- Buscador con SearchCombobox -->
            <div class="w-[420px]">
                <SearchCombobox
                    bind:value={searchTerm}
                    items={items.data}
                    searchKeys={['product_name', 'product_sku', 'product_barcode']}
                    placeholder="Buscar por nombre de producto o SKU..."
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
                        <div class="flex items-center justify-between gap-3 w-full min-w-0">
                            <div class="flex flex-col min-w-0 text-left">
                                <span class="truncate font-medium text-foreground">
                                    <HighlightMatch text={item.product_name} query={searchTerm} />
                                </span>
                                <span class="text-[11px] text-muted-foreground">
                                    {item.warehouse_name} • SKU: <HighlightMatch text={item.product_sku || 'N/A'} query={searchTerm} />
                                </span>
                            </div>
                            <span class="text-xs font-semibold tabular-nums text-primary shrink-0">
                                {formatNumber(item.stock, item.product_sale_type === 'weight')} {item.product_sale_type === 'weight' ? 'kg' : 'u.'}
                            </span>
                        </div>
                    {/snippet}
                </SearchCombobox>
            </div>

            <!-- Controles de Filtro -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Filtro de Depósito -->
                <select
                    bind:value={selectedWarehouseId}
                    onchange={onfilterchange}
                    class="h-10 rounded-xl border border-input bg-card px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[160px] cursor-pointer"
                >
                    <option value="">Todos los Depósitos</option>
                    {#each warehouses as wh (wh.id)}
                        <option value={wh.id}>
                            {wh.name} {wh.is_default ? '(Principal)' : ''}
                        </option>
                    {/each}
                </select>

                <!-- Filtro de Categoría -->
                <select
                    bind:value={selectedCategoryId}
                    onchange={onfilterchange}
                    class="h-10 rounded-xl border border-input bg-card px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[150px] cursor-pointer"
                >
                    <option value="">Todas las Categorías</option>
                    {#each categories as cat (cat.id)}
                        <option value={cat.id}>{cat.name}</option>
                    {/each}
                </select>

                <!-- Filtro de Estado de Stock -->
                <select
                    bind:value={selectedStockStatus}
                    onchange={onfilterchange}
                    class="h-10 rounded-xl border border-input bg-card px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[150px] cursor-pointer"
                >
                    <option value="">Todos los Estados</option>
                    <option value="in_stock">Con Stock (> 0)</option>
                    <option value="low_stock">Stock Bajo / Crítico</option>
                    <option value="out_of_stock">Sin Stock (0)</option>
                </select>

                {#if hasActiveFilters}
                    <Button
                        variant="ghost"
                        size="sm"
                        onclick={onreset}
                        class="h-10 px-3 text-xs text-muted-foreground hover:text-foreground gap-1.5"
                    >
                        <RotateCcw class="size-3.5" />
                        Limpiar
                    </Button>
                {/if}
            </div>
        </div>
    </Card>

    <!-- Grid de Resultados / Existencias -->
    {#if items.data.length === 0}
        <Card class="p-12 text-center border-dashed border-border/80 rounded-2xl">
            <div class="flex flex-col items-center justify-center max-w-md mx-auto">
                <div class="size-14 rounded-2xl bg-muted/60 flex items-center justify-center mb-4 text-muted-foreground">
                    <PackageSearch class="size-7" />
                </div>
                <h3 class="text-lg font-semibold text-foreground mb-1">No se encontraron productos</h3>
                <p class="text-xs text-muted-foreground mb-4">
                    {hasActiveFilters ? 'No hay existencias coincidentes con los filtros aplicados.' : 'No hay existencias registradas en los depósitos.'}
                </p>
                {#if hasActiveFilters}
                    <Button variant="outline" size="sm" onclick={onreset} class="gap-1.5 text-xs font-medium">
                        <RotateCcw class="size-3.5" />
                        Restablecer filtros
                    </Button>
                {/if}
            </div>
        </Card>
    {:else}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {#each items.data as item (item.id)}
                {@const isWeight = item.product_sale_type === 'weight'}
                {@const unitLabel = isWeight ? 'kg' : 'u.'}
                {@const isLowStock = Number(item.stock) <= Number(item.min_stock) && Number(item.stock) > 0}
                {@const isOutOfStock = Number(item.stock) <= 0}

                <Card class="rounded-2xl p-5 border border-border/60 hover:border-border transition-all duration-200 hover:shadow-lg hover:shadow-accent/20 flex flex-col justify-between gap-4 bg-card">
                    <!-- Cabecera de la Card -->
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-base text-foreground truncate tracking-tight" title={item.product_name}>
                                    <HighlightMatch text={item.product_name} query={searchTerm} />
                                </h3>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    {#if item.product_sku}
                                        <Badge variant="outline" class="text-[11px] font-mono px-1.5 py-0">
                                            <HighlightMatch text={item.product_sku} query={searchTerm} />
                                        </Badge>
                                    {/if}

                                    {#if item.category_name}
                                        <span class="text-xs text-muted-foreground truncate max-w-[140px]">
                                            {item.category_name}
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Badge de Estado de Stock en este depósito -->
                            {#if isOutOfStock}
                                <Badge variant="destructive" class="text-[11px] font-semibold shrink-0">
                                    Sin Stock
                                </Badge>
                            {:else if isLowStock}
                                <Badge class="bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 text-[11px] font-semibold shrink-0">
                                    Stock Bajo
                                </Badge>
                            {:else}
                                <Badge variant="secondary" class="text-[11px] font-semibold shrink-0 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20">
                                    Disponible
                                </Badge>
                            {/if}
                        </div>

                        <!-- Información de Ubicación Física -->
                        <div class="pt-2 border-t border-border/40 flex items-center justify-between text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5 font-medium text-foreground">
                                <Building2 class="size-3.5 text-primary shrink-0" />
                                <span class="truncate">{item.warehouse_name}</span>
                            </div>
                            {#if item.aisle_location}
                                <div class="flex items-center gap-1 text-[11px] bg-muted/60 px-2 py-0.5 rounded-md font-mono">
                                    <MapPin class="size-3 text-muted-foreground" />
                                    <span>{item.aisle_location}</span>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Pie de la Card: Stock en este depósito y Stock consolidado con mismo tamaño de fuente -->
                    <div class="pt-3 border-t border-border/50 flex items-center justify-between bg-muted/20 -mx-5 -mb-5 px-5 py-3 rounded-b-2xl">
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

        <!-- Paginación -->
        {#if items.last_page > 1}
            <div class="pt-4 flex items-center justify-between">
                <p class="text-xs text-muted-foreground">
                    Mostrando del <span class="font-medium text-foreground tabular-nums">{items.from}</span> al <span class="font-medium text-foreground tabular-nums">{items.to}</span> de <span class="font-medium text-foreground tabular-nums">{items.total}</span> existencias
                </p>
                <AppPagination links={items.links} />
            </div>
        {/if}
    {/if}
</div>
