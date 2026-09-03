<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import Box from 'lucide-svelte/icons/box';
    import WarehouseIcon from 'lucide-svelte/icons/warehouse';
    import WarehouseLookupDesktop, {
        type WarehouseItem,
        type WarehouseOption,
        type CategoryOption,
    } from './desktop/WarehouseLookupDesktop.svelte';
    import WarehouseLookupMobile from './mobile/WarehouseLookupMobile.svelte';

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

    $effect(() => {
        searchTerm = filters.search || '';
        selectedWarehouseId = filters.warehouse_id || '';
        selectedCategoryId = filters.category_id || '';
        selectedStockStatus = filters.stock_status || '';
    });

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

        router.get('/tenant/productos/buscar-deposito', params, {
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
        router.get('/tenant/productos/buscar-deposito', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }
</script>

<AppHead title="Buscar en Depósito - Control de Stock Físico" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Encabezado y Acciones del Módulo (Separado homogéneamente del cuerpo por gap-6) -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                    <WarehouseIcon class="size-7 text-primary" />
                    <span>Buscar en Depósito</span>
                </h1>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium tracking-wide text-primary tabular-nums">
                    {items.total} {items.total === 1 ? 'registro' : 'registros'}
                </span>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                Consulta y audita el stock físico distribuido por depósito, estante y producto.
            </p>
        </div>

        <div class="flex items-center justify-end gap-2.5">
            <Link href="/tenant/productos">
                <Button variant="secondary" class="min-h-[44px] px-4 gap-2 rounded-xl text-sm font-semibold shadow-xs">
                    <Box class="size-4 text-primary" />
                    <span>Catálogo de productos</span>
                </Button>
            </Link>
        </div>
    </div>

    <!-- Contenido del Cuerpo: Desktop View -->
    <div class="hidden md:block">
        <WarehouseLookupDesktop
            {items}
            {warehouses}
            {categories}
            bind:searchTerm
            bind:selectedWarehouseId
            bind:selectedCategoryId
            bind:selectedStockStatus
            onsearch={() => applyFilters()}
            onfilterchange={() => applyFilters()}
            onreset={resetFilters}
        />
    </div>

    <!-- Contenido del Cuerpo: Mobile View -->
    <div class="block md:hidden">
        <WarehouseLookupMobile
            {items}
            {warehouses}
            {categories}
            bind:searchTerm
            bind:selectedWarehouseId
            bind:selectedCategoryId
            bind:selectedStockStatus
            onsearch={() => applyFilters()}
            onfilterchange={() => applyFilters()}
            onreset={resetFilters}
        />
    </div>
</div>


