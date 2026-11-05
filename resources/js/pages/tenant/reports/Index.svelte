<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { ModuleHeader } from '@/components/common';
    import ReportControlBar from './components/ReportControlBar.svelte';
    import OverviewTab from './desktop/OverviewTab.svelte';
    import OverviewMobile from './mobile/OverviewMobile.svelte';
    import type { StatScreenId } from '@/components/svelte-bits/GlideSelect.svelte';

    let {
        view = 'inicial',
        overview_data,
        orders_data,
        customers_data,
        suppliers_data,
        products_data,
        warehouses = [],
        filters,
        permissions,
    }: {
        view?: StatScreenId;
        overview_data: any;
        orders_data?: any;
        customers_data?: any;
        suppliers_data?: any;
        products_data?: any;
        warehouses: Array<{ id: number; name: string; code: string }>;
        filters: {
            view: StatScreenId;
            date_from: string;
            date_to: string;
            warehouse_id: number | null;
            year: number;
            buyers_sort?: string;
            defaulters_sort?: string;
            defaulters_page?: number;
            suppliers_sort?: string;
            suppliers_page?: number;
        };
        permissions: { can_view_margins: boolean; can_export: boolean };
    } = $props();

    let activeView = $state<StatScreenId>(view);

    $effect(() => {
        activeView = view;
    });
</script>

<AppHead title="Reportes y Estadísticas del Comercio" />

<!-- Main Shell: 15% expanded container on desktop (max-w-[1536px]), strict bilateral symmetry -->
<div class="flex flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8 w-full max-w-7xl 2xl:max-w-[1536px] mx-auto transition-all">
    <!-- Header Operativo Estandarizado -->
    <ModuleHeader title="Reportes y Estadísticas" />

    <!-- Control Bar Operativa (Atajos, Datepickers, Depósito y Glide Select) -->
    <ReportControlBar
        view={activeView}
        dateFrom={filters.date_from}
        dateTo={filters.date_to}
        warehouseId={filters.warehouse_id}
        {warehouses}
        onViewChange={(v) => (activeView = v)}
    />

    <!-- Desktop View (Screens >= lg) -->
    <div class="hidden lg:block w-full">
        {#if activeView === 'inicial'}
            <OverviewTab
                overviewData={overview_data}
                canViewMargins={permissions.can_view_margins}
            />
        {:else if activeView === 'pedidos' && orders_data}
            <!-- OrdersTab (Fase 4) -->
            <div id="desktop-orders-placeholder"></div>
        {:else if activeView === 'clientes' && customers_data}
            <!-- CustomersTab (Fase 4) -->
            <div id="desktop-customers-placeholder"></div>
        {:else if activeView === 'proveedores' && suppliers_data}
            <!-- SuppliersTab (Fase 4) -->
            <div id="desktop-suppliers-placeholder"></div>
        {:else if activeView === 'productos' && products_data}
            <!-- ProductsTab (Fase 4) -->
            <div id="desktop-products-placeholder"></div>
        {/if}
    </div>

    <!-- Mobile View (Screens < lg) -->
    <div class="block lg:hidden w-full">
        {#if activeView === 'inicial'}
            <OverviewMobile
                overviewData={overview_data}
                canViewMargins={permissions.can_view_margins}
            />
        {:else if activeView === 'pedidos' && orders_data}
            <!-- OrdersMobile (Fase 4) -->
            <div id="mobile-orders-placeholder"></div>
        {:else if activeView === 'clientes' && customers_data}
            <!-- CustomersMobile (Fase 4) -->
            <div id="mobile-customers-placeholder"></div>
        {:else if activeView === 'proveedores' && suppliers_data}
            <!-- SuppliersMobile (Fase 4) -->
            <div id="mobile-suppliers-placeholder"></div>
        {:else if activeView === 'productos' && products_data}
            <!-- ProductsMobile (Fase 4) -->
            <div id="mobile-products-placeholder"></div>
        {/if}
    </div>
</div>
