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

        $dateFromInput = $request->input('date_from');
        $dateToInput = $request->input('date_to');
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $year = $request->filled('year') ? (int) $request->input('year') : (int) now()->year;

        $dateFrom = $dateFromInput ? Carbon::parse($dateFromInput)->startOfDay() : now()->startOfMonth();
        $dateTo = $dateToInput ? Carbon::parse($dateToInput)->endOfDay() : now()->endOfDay();

        // Security & permissions check for costs/margins
        $canViewMargins = $request->user()->can('reportes.ver_costos_margenes');
        $canExport = $request->user()->can('reportes.exportar');

        // Retrieve analytics datasets
        $metrics = $reportService->getFilteredMetrics($tenant->id, $dateFrom, $dateTo, $warehouseId);
        $dailyTrend = $reportService->getDailySalesTrend($tenant->id, $dateFrom, $dateTo, $warehouseId);
        $topProducts = $reportService->getTopProducts($tenant->id, $dateFrom, $dateTo, 10, $warehouseId);
        $monthlySummary = $reportService->getMonthlySummary($tenant->id, $year);

        // Mask sensitive cost & margin metrics if unauthorized
        if (! $canViewMargins) {
            $metrics['expenses']['total'] = null;
            $metrics['expenses']['purchases_count'] = null;
            $metrics['margin']['operating_margin'] = null;
            $metrics['margin']['margin_percentage'] = null;

            $monthlySummary = array_map(function ($row) {
                $row['expenses'] = null;
                $row['operating_margin'] = null;

                return $row;
            }, $monthlySummary);
        }

        $warehouses = Warehouse::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        return Inertia::render('tenant/reports/Index', [
            'metrics' => $metrics,
            'daily_trend' => $dailyTrend,
            'top_products' => $topProducts,
            'monthly_summary' => $monthlySummary,
            'warehouses' => $warehouses,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'warehouse_id' => $warehouseId,
                'year' => $year,
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
