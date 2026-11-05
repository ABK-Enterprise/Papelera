<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\Reports\BusinessReportService;
use App\Services\TenantLogoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class ReportController extends Controller
{
    /**
     * Display commercial reports, KPIs, interactive charts, and top products.
     */
    public function index(Request $request, BusinessReportService $reportService): Response
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            abort(403, 'Comercio no válido.');
        }

        $view = $request->input('view', 'inicial');
        $validViews = ['inicial', 'pedidos', 'clientes', 'proveedores', 'productos'];
        if (! in_array($view, $validViews, true)) {
            $view = 'inicial';
        }

        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $year = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;

        $dateFrom = $dateFromInput ? Carbon::parse($dateFromInput)->startOfDay() : now()->startOfMonth();
        $dateTo = $dateToInput ? Carbon::parse($dateToInput)->endOfDay() : now()->endOfDay();

        // Security & permissions check for costs/margins
        $canViewMargins = $request->user()->can('reportes.ver_costos_margenes');
        $canExport = $request->user()->can('reportes.exportar');

        // Always retrieve overview data for overview tab & backward compatibility
        $overviewData = $reportService->getOverviewData($tenant->id, $dateFrom, $dateTo, $warehouseId, $year);
        $ordersData = null;
        $customersData = null;
        $suppliersData = null;
        $productsData = null;

        if ($view === 'pedidos') {
            $ordersData = $reportService->getOrdersData($tenant->id, $dateFrom, $dateTo, $warehouseId);
        } elseif ($view === 'clientes') {
            $buyersSort = (string) $request->input('buyers_sort', 'desc');
            $defaultersSort = (string) $request->input('defaulters_sort', 'desc');
            $defaultersPage = (int) $request->input('defaulters_page', 1);
            $customersData = $reportService->getCustomersData(
                $tenant->id, $dateFrom, $dateTo, $warehouseId, $buyersSort, $defaultersSort, $defaultersPage
            );
        } elseif ($view === 'proveedores') {
            $suppliersSort = (string) $request->input('suppliers_sort', 'desc');
            $suppliersPage = (int) $request->input('suppliers_page', 1);
            $suppliersData = $reportService->getSuppliersData(
                $tenant->id, $dateFrom, $dateTo, $warehouseId, $suppliersSort, $suppliersPage
            );
        } elseif ($view === 'productos') {
            $productsSort = (string) $request->input('products_sort', 'desc');
            $productsData = $reportService->getProductsData($tenant->id, $dateFrom, $dateTo, $warehouseId, $productsSort);
        }

        // Mask sensitive cost & margin metrics if unauthorized
        if (! $canViewMargins) {
            $overviewData['kpis']['total_expenses'] = null;
            $overviewData['kpis']['operating_margin'] = null;

            $overviewData['monthly_comparison'] = array_map(function ($row) {
                $row['expenses'] = null;
                $row['operating_margin'] = null;

                return $row;
            }, $overviewData['monthly_comparison']);

            if ($suppliersData !== null) {
                $suppliersData['kpis']['expenses_current_month'] = null;
                $suppliersData['kpis']['total_expenses'] = null;
                $suppliersData['suppliers']['data'] = array_map(function ($row) {
                    $row['total_spent'] = null;

                    return $row;
                }, $suppliersData['suppliers']['data']);
            }
        }

        $warehouses = Warehouse::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        // Legacy metrics adapter for smooth migration & existing tests
        $legacyMetrics = [
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'sales' => [
                'total' => $overviewData['kpis']['total_revenues'],
                'orders_count' => count($overviewData['top_buyers']),
                'average_ticket' => 0.0,
            ],
            'revenues' => [
                'total' => $overviewData['kpis']['total_revenues'],
                'by_method' => [],
            ],
            'expenses' => [
                'total' => $overviewData['kpis']['total_expenses'],
                'purchases_count' => 0,
            ],
            'margin' => [
                'operating_margin' => $overviewData['kpis']['operating_margin'],
                'margin_percentage' => 0.0,
            ],
        ];

        return Inertia::render('tenant/reports/Index', [
            'view' => $view,
            'overview_data' => $overviewData,
            'orders_data' => $ordersData,
            'customers_data' => $customersData,
            'suppliers_data' => $suppliersData,
            'products_data' => $productsData,
            // Legacy props
            'metrics' => $legacyMetrics,
            'daily_trend' => $overviewData['sales_trend'],
            'top_products' => $overviewData['top_products'],
            'monthly_summary' => $overviewData['monthly_comparison'],
            'warehouses' => $warehouses,
            'filters' => [
                'view' => $view,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'warehouse_id' => $warehouseId,
                'year' => $year,
                'buyers_sort' => $request->input('buyers_sort', 'desc'),
                'defaulters_sort' => $request->input('defaulters_sort', 'desc'),
                'defaulters_page' => (int) $request->input('defaulters_page', 1),
                'suppliers_sort' => $request->input('suppliers_sort', 'desc'),
                'suppliers_page' => (int) $request->input('suppliers_page', 1),
                'products_sort' => $request->input('products_sort', 'desc'),
            ],
            'permissions' => [
                'can_view_margins' => $canViewMargins,
                'can_export' => $canExport,
            ],
        ]);
    }

    /**
     * Export commercial analytics report as an A4 Landscape PDF.
     */
    public function exportPdf(Request $request, BusinessReportService $reportService, TenantLogoService $logoService)
    {
        $tenant = $request->user()->tenant;
        if (! $tenant) {
            abort(403, 'Comercio no válido.');
        }

        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;

        $dateFrom = $dateFromInput ? Carbon::parse($dateFromInput)->startOfDay() : now()->startOfMonth();
        $dateTo = $dateToInput ? Carbon::parse($dateToInput)->endOfDay() : now()->endOfDay();

        $canViewMargins = $request->user()->can('reportes.ver_costos_margenes');

        $metrics = $reportService->getFilteredMetrics($tenant->id, $dateFrom, $dateTo, $warehouseId);
        $topProducts = $reportService->getTopProducts($tenant->id, $dateFrom, $dateTo, 10, $warehouseId);
        $selectedWarehouse = $warehouseId ? Warehouse::where('tenant_id', $tenant->id)->find($warehouseId) : null;

        if (! $canViewMargins) {
            $metrics['expenses']['total'] = null;
            $metrics['expenses']['purchases_count'] = null;
            $metrics['margin']['operating_margin'] = null;
            $metrics['margin']['margin_percentage'] = null;
        }

        $logoBase64 = $logoService->getBase64Logo($tenant);

        return Pdf::view('pdf.business-report', [
            'tenant' => $tenant,
            'logoBase64' => $logoBase64,
            'metrics' => $metrics,
            'topProducts' => $topProducts,
            'warehouse' => $selectedWarehouse,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'canViewMargins' => $canViewMargins,
        ])
            ->format('a4')
            ->landscape()
            ->meta(
                title: "Reporte Comercial - {$tenant->name}",
                author: $tenant->name,
                creator: config('app.name', 'Papelera')
            )
            ->headers([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ])
            ->name("reporte-comercial-{$dateFrom->format('Ymd')}-{$dateTo->format('Ymd')}.pdf")
            ->download()
            ->toResponse($request);
    }
}
