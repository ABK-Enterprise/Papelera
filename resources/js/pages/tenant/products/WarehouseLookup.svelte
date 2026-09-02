<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card } from '@/components/ui/card';
    import { Badge } from '@/components/ui/badge';
    import { Link, router } from '@inertiajs/svelte';
    import SearchCombobox from '@/components/common/SearchCombobox.svelte';
    import AppPagination from '@/components/common/AppPagination.svelte';
    import ArrowLeft from 'lucide-svelte/icons/arrow-left';
    import Box from 'lucide-svelte/icons/box';
    import Building2 from 'lucide-svelte/icons/building-2';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import PackageSearch from 'lucide-svelte/icons/package-search';
    import SlidersHorizontal from 'lucide-svelte/icons/sliders-horizontal';
    import X from 'lucide-svelte/icons/x';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';

    interface WarehouseItem {
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

    interface WarehouseOption {
        id: number;
        name: string;
        code: string;
        is_default: boolean;
    }

    interface CategoryOption {
        id: number;
        name: string;
    }

    interface Props {
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
        filters: {
            search?: string | null;
            warehouse_id?: string | number | null;
            category_id?: string | number | null;
            stock_status?: string | null;
        };
    }

    let { items, warehouses, categories, filters }: Props = $props();

    let searchTerm = $state(filters.search || '');
    let selectedWarehouseId = $state<string | number | null>(filters.warehouse_id || '');
    let selectedCategoryId = $state<string | number | null>(filters.category_id || '');
    let selectedStockStatus = $state<string | null>(filters.stock_status || '');

    function applyFilters() {
        const params: Record<string, any> = {};

        if (searchTerm && searchTerm.trim() !== '') {
            params.search = searchTerm.trim();
        }
        if (selectedWarehouseId) {
            params.warehouse_id = selectedWarehouseId;
        }
        if (selectedCategoryId) {
            params.category_id = selectedCategoryId;
        }
        if (selectedStockStatus) {
            params.stock_status = selectedStockStatus;
        }

        router.get('/tenant/products/buscar-deposito', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    }

    function resetFilters() {
        searchTerm = '';
        selectedWarehouseId = '';
        selectedCategoryId = '';
        selectedStockStatus = '';
        router.get('/tenant/products/buscar-deposito', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    const hasActiveFilters = $derived(
        Boolean(searchTerm || selectedWarehouseId || selectedCategoryId || selectedStockStatus)
    );

    function formatNumber(value: number | string, isWeight: boolean): string {
        const num = Number(value) || 0;
        return isWeight ? num.toFixed(2) : Math.round(num).toString();
    }
</script>

<AppHead title="Buscar en Depósito - Control de Stock Físico" />

<div class="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Encabezado de Página -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <Link
                    href="/tenant/products"
                    class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft class="size-3.5" />
                    Volver a Catálogo
                </Link>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight leading-tight text-foreground flex items-center gap-2.5">
                <WarehouseIcon class="size-7 text-primary" />
                Buscar en Depósito
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5 max-w-prose">
                Localiza rápidamente existencias físicas distribuidas por depósito, pasillo y estante.
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <Link href="/tenant/products">
                <Button variant="outline" class="gap-1.5 font-medium shadow-2xs">
                    <Box class="size-4 text-muted-foreground" />
                    Catálogo Completo
                </Button>
            </Link>
        </div>
    </div>


    <!-- Barra de Búsqueda y Filtros de Depósito -->
    <Card class="rounded-xl p-4 shadow-[0px_10px_40px_rgba(0,0,0,0.03)] border border-border/60 space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Buscador -->
            <div class="w-full lg:w-[420px]">
                <SearchCombobox
                    bind:value={searchTerm}
                    placeholder="Buscar producto, SKU, depósito o estante..."
                    onsearch={(q) => {
                        searchTerm = q;
                        applyFilters();
                    }}
                    onclear={resetFilters}
                />
            </div>

            <!-- Controles de Filtro -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Filtro de Depósito -->
                <select
                    bind:value={selectedWarehouseId}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[150px]"
                >
                    <option value="">Todos los Depósitos</option>
                    {#each warehouses as wh}
                        <option value={wh.id}>
                            {wh.name} {wh.is_default ? '(Principal)' : ''}
                        </option>
                    {/each}
                </select>

                <!-- Filtro de Categoría -->
                <select
                    bind:value={selectedCategoryId}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[140px]"
                >
                    <option value="">Todas las Categorías</option>
                    {#each categories as cat}
                        <option value={cat.id}>{cat.name}</option>
                    {/each}
                </select>

                <!-- Filtro de Estado de Stock -->
                <select
                    bind:value={selectedStockStatus}
                    onchange={applyFilters}
                    class="h-10 rounded-lg border border-input bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-primary min-w-[130px]"
                >
                    <option value="">Stock: Todos</option>
                    <option value="in_stock">Con Stock</option>
                    <option value="low_stock">Stock Crítico / Mínimo</option>
                    <option value="out_of_stock">Sin Stock</option>
                </select>

                {#if hasActiveFilters}
                    <Button
                        variant="ghost"
                        size="sm"
                        onclick={resetFilters}
                        class="h-10 px-2.5 text-xs text-muted-foreground hover:text-foreground gap-1.5"
                    >
                        <X class="size-3.5" />
                        Limpiar filtros
                    </Button>
                {/if}
            </div>
        </div>
    </Card>

    <!-- Lista de Resultados de Productos por Depósito -->
    {#if items.data.length > 0}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {#each items.data as item}
                {@const isWeight = item.product_sale_type === 'weight'}
                {@const isLowStock = Number(item.stock) <= Number(item.min_stock) && Number(item.stock) > 0}
                {@const isOutOfStock = Number(item.stock) <= 0}
                
                <Card class="p-4 rounded-xl border border-border/70 hover:border-primary/40 transition-colors shadow-2xs flex flex-col justify-between gap-3 bg-card">
                    <div class="space-y-2">
                        <!-- Cabecera: Producto y SKU -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-sm text-foreground leading-snug truncate" title={item.product_name}>
                                    {item.product_name}
                                </h3>
                                {#if item.product_sku}
                                    <span class="text-xs font-mono text-muted-foreground">SKU: {item.product_sku}</span>
                                {/if}
                            </div>

                            {#if item.category_name}
                                <Badge variant="secondary" class="text-[10px] font-medium shrink-0">
                                    {item.category_name}
                                </Badge>
                            {/if}
                        </div>

                        <!-- Info del Depósito y Ubicación -->
                        <div class="rounded-lg bg-muted/40 p-2.5 space-y-1.5 border border-border/40">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-medium text-foreground flex items-center gap-1.5">
                                    <WarehouseIcon class="size-3.5 text-primary shrink-0" />
                                    {item.warehouse_name}
                                </span>
                                <span class="font-mono text-[11px] text-muted-foreground bg-background px-1.5 py-0.5 rounded border">
                                    {item.warehouse_code}
                                </span>
                            </div>

                            {#if item.aisle_location}
                                <div class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                    <MapPin class="size-3 text-muted-foreground shrink-0" />
                                    <span>Ubicación: <strong class="text-foreground font-medium">{item.aisle_location}</strong></span>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Métricas de Stock Local vs Consolidado -->
                    <div class="border-t border-border/50 pt-3 flex items-center justify-between text-xs">
                        <div class="flex flex-col">
                            <span class="text-[11px] text-muted-foreground">Stock en este depósito:</span>
                            <span class="text-base font-bold font-sans tabular-nums {isOutOfStock ? 'text-destructive' : isLowStock ? 'text-amber-600 dark:text-amber-400' : 'text-foreground'}">
                                {formatNumber(item.stock, isWeight)} {isWeight ? 'kg' : 'u.'}
                            </span>
                        </div>

                        <div class="flex flex-col text-right">
                            <span class="text-[11px] text-muted-foreground">Stock total comercio:</span>
                            <span class="text-xs font-semibold font-sans tabular-nums text-muted-foreground">
                                {formatNumber(item.total_consolidated_stock, isWeight)} {isWeight ? 'kg' : 'u.'}
                            </span>
                        </div>
                    </div>
                </Card>
            {/each}
        </div>

        <!-- Paginación -->
        <AppPagination pagination={items} />
    {:else}
        <!-- Estado Vacío -->
        <Card class="rounded-xl p-12 text-center border-dashed border-border/80 bg-card">
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-muted/60 text-muted-foreground mb-4">
                <PackageSearch class="size-6" />
            </div>
            <h3 class="text-base font-semibold text-foreground">No se encontraron existencias en depósitos</h3>
            <p class="text-xs text-muted-foreground mt-1 max-w-sm mx-auto">
                No hay productos que coincidan con los criterios de búsqueda o filtros seleccionados.
            </p>
            {#if hasActiveFilters}
                <div class="mt-4">
                    <Button variant="outline" size="sm" onclick={resetFilters} class="text-xs font-medium">
                        Restablecer filtros
                    </Button>
                </div>
            {/if}
        </Card>
    {/if}
</div>
