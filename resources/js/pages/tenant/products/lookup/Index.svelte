<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { router } from '@inertiajs/svelte';
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
    <!-- Desktop View -->
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

    <!-- Mobile View -->
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

