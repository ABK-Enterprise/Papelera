<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import { ModuleHeader } from '@/components/common';
    import { router } from '@inertiajs/svelte';
    import ReportViewDesktop from './desktop/ReportViewDesktop.svelte';
    import ReportViewMobile from './mobile/ReportViewMobile.svelte';

    let {
        metrics,
        daily_trend = [],
        top_products = [],
        monthly_summary = [],
        warehouses = [],
        filters,
        permissions,
    }: {
        metrics: any;
        daily_trend: Array<{ date: string; label: string; sales: number; orders_count: number }>;
        top_products: Array<{ product_id: number; name: string; sku: string | null; total_quantity: number; total_subtotal: number }>;
        monthly_summary: Array<{ month_key: string; label: string; sales: number; orders_count: number; average_ticket: number; revenues: number; expenses: number | null; operating_margin: number | null }>;
        warehouses: Array<{ id: number; name: string; code: string }>;
        filters: { date_from: string; date_to: string; warehouse_id: number | null; year: number };
        permissions: { can_view_margins: boolean; can_export: boolean };
    } = $props();

    function handleApplyFilters(newFilters: { date_from: string; date_to: string; warehouse_id: number | null; year: number }) {
        const cleanParams: Record<string, any> = {
            date_from: newFilters.date_from,
            date_to: newFilters.date_to,
        };

        if (newFilters.warehouse_id) {
            cleanParams.warehouse_id = newFilters.warehouse_id;
        }

        if (newFilters.year) {
            cleanParams.year = newFilters.year;
        }

        router.get('/tenant/reportes', cleanParams, {
            preserveState: true,
            replace: true,
        });
    }

    function handleExportPdf() {
        const params = new URLSearchParams();
        if (filters.date_from) params.set('date_from', filters.date_from);
        if (filters.date_to) params.set('date_to', filters.date_to);
        if (filters.warehouse_id) params.set('warehouse_id', String(filters.warehouse_id));

        const queryStr = params.toString() ? `?${params.toString()}` : '';
        window.open(`/tenant/reportes/exportar-pdf${queryStr}`, '_blank');
    }
</script>

<AppHead title="Reportes y Estadísticas del Comercio" />

<div class="flex flex-1 flex-col gap-6 p-4 md:p-8 max-w-7xl mx-auto w-full">
    <!-- Header Operativo Estandarizado -->
    <ModuleHeader title="Reportes y Estadísticas" />

    <!-- Desktop View (Large screens >= lg) -->
    <div class="hidden lg:block">
        <ReportViewDesktop
            {metrics}
            dailyTrend={daily_trend}
            topProducts={top_products}
            monthlySummary={monthly_summary}
            {warehouses}
            {filters}
            {permissions}
            onApplyFilters={handleApplyFilters}
            onExportPdf={handleExportPdf}
        />
    </div>

    <!-- Mobile View (< lg) -->
    <div class="block lg:hidden">
        <ReportViewMobile
            {metrics}
            dailyTrend={daily_trend}
            topProducts={top_products}
            monthlySummary={monthly_summary}
            {warehouses}
            {filters}
            {permissions}
            onApplyFilters={handleApplyFilters}
            onExportPdf={handleExportPdf}
        />
    </div>
</div>
