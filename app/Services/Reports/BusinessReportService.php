<?php

namespace App\Services\Reports;

use App\Models\CustomerOrder;
use App\Models\Receipt;
use App\Models\SupplierOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BusinessReportService
{
    /**
     * Get monthly metrics from pre-aggregated SQL views for a specific year.
     * Guarantees all 12 months are returned with deterministic zero-filled defaults.
     *
     * @return array<int, array{
     *     month_key: string,
     *     label: string,
     *     sales: float,
     *     orders_count: int,
     *     average_ticket: float,
     *     revenues: float,
     *     expenses: float,
     *     operating_margin: float
     * }>
     */
    public function getMonthlySummary(int $tenantId, ?int $year = null): array
    {
        $year = $year ?? (int) now()->year;
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $salesByMonth = DB::table('view_tenant_monthly_sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('period_month', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => substr((string) $item->period_month, 0, 7));

        $revenuesByMonth = DB::table('view_tenant_monthly_revenues')
            ->where('tenant_id', $tenantId)
            ->whereBetween('period_month', [$startDate, $endDate])
            ->selectRaw('period_month, SUM(total_collected) as total_revenues')
            ->groupBy('period_month')
            ->get()
            ->keyBy(fn ($item) => substr((string) $item->period_month, 0, 7));

        $expensesByMonth = DB::table('view_tenant_monthly_expenses')
            ->where('tenant_id', $tenantId)
            ->whereBetween('period_month', [$startDate, $endDate])
            ->get()
            ->keyBy(fn ($item) => substr((string) $item->period_month, 0, 7));

        $monthNames = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $summary = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthKey = sprintf('%04d-%02d', $year, $m);
            $salesRow = $salesByMonth->get($monthKey);
            $revenueRow = $revenuesByMonth->get($monthKey);
            $expenseRow = $expensesByMonth->get($monthKey);

            $sales = $salesRow ? (float) $salesRow->total_sales : 0.0;
            $ordersCount = $salesRow ? (int) $salesRow->total_orders : 0;
            $averageTicket = $salesRow ? (float) $salesRow->average_ticket : 0.0;
            $revenues = $revenueRow ? (float) $revenueRow->total_revenues : 0.0;
            $expenses = $expenseRow ? (float) $expenseRow->total_spent : 0.0;
            $margin = round($sales - $expenses, 2);

            $summary[] = [
                'month_key' => $monthKey,
                'label' => $monthNames[$m].' '.$year,
                'sales' => round($sales, 2),
                'orders_count' => $ordersCount,
                'average_ticket' => round($averageTicket, 2),
                'revenues' => round($revenues, 2),
                'expenses' => round($expenses, 2),
                'operating_margin' => $margin,
            ];
        }

        return $summary;
    }

    /**
     * Get real-time KPIs filtered by date range and optional warehouse.
     *
     * @return array{
     *     period: array{from: string, to: string},
     *     sales: array{
     *         total: float,
     *         orders_count: int,
     *         average_ticket: float
     *     },
     *     revenues: array{
     *         total: float,
     *         by_method: array<int, array{method: string, label: string, amount: float, percentage: float, count: int}>
     *     },
     *     expenses: array{
     *         total: float,
     *         purchases_count: int
     *     },
     *     margin: array{
     *         operating_margin: float,
     *         margin_percentage: float
     *     }
     * }
     */
    public function getFilteredMetrics(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        // 1. Sales query (Customer Orders not cancelled)
        $salesQuery = CustomerOrder::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelado')
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $salesStats = $salesQuery->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_sales')
            ->first();

        $totalSales = $salesStats ? (float) $salesStats->total_sales : 0.0;
        $totalOrders = $salesStats ? (int) $salesStats->total_orders : 0;
        $averageTicket = $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0.0;

        // 2. Revenues query (Active receipts)
        $revenuesQuery = Receipt::where('tenant_id', $tenantId)
            ->where('is_cancelled', false)
            ->whereBetween('created_at', [$from, $to]);

        $receiptsByMethod = $revenuesQuery->select('payment_method')
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('payment_method')
            ->get();

        $totalRevenues = (float) $receiptsByMethod->sum('total_amount');

        $methodLabels = [
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'mercadopago' => 'Mercado Pago',
            'tarjeta_debito' => 'Tarjeta Débito',
            'tarjeta_credito' => 'Tarjeta Crédito',
            'cheque' => 'Cheque',
        ];

        $revenuesByMethod = $receiptsByMethod->map(function ($row) use ($totalRevenues, $methodLabels) {
            $amount = (float) $row->total_amount;
            $percentage = $totalRevenues > 0 ? round(($amount / $totalRevenues) * 100, 1) : 0.0;
            $method = (string) $row->payment_method;

            return [
                'method' => $method,
                'label' => $methodLabels[$method] ?? ucfirst(str_replace('_', ' ', $method)),
                'amount' => round($amount, 2),
                'percentage' => $percentage,
                'count' => (int) $row->total_count,
            ];
        })->values()->all();

        // 3. Expenses query (Supplier orders received)
        $expensesQuery = SupplierOrder::where('tenant_id', $tenantId)
            ->where('status', 'recibido')
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $expensesQuery->where('warehouse_id', $warehouseId);
        }

        $expensesStats = $expensesQuery->selectRaw('COUNT(*) as total_purchases, COALESCE(SUM(total_cost), 0) as total_spent')
            ->first();

        $totalExpenses = $expensesStats ? (float) $expensesStats->total_spent : 0.0;
        $totalPurchases = $expensesStats ? (int) $expensesStats->total_purchases : 0;

        // 4. Gross Operating Margin
        $operatingMargin = round($totalSales - $totalExpenses, 2);
        $marginPercentage = $totalSales > 0 ? round(($operatingMargin / $totalSales) * 100, 1) : 0.0;

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'sales' => [
                'total' => round($totalSales, 2),
                'orders_count' => $totalOrders,
                'average_ticket' => round($averageTicket, 2),
            ],
            'revenues' => [
                'total' => round($totalRevenues, 2),
                'by_method' => $revenuesByMethod,
            ],
            'expenses' => [
                'total' => round($totalExpenses, 2),
                'purchases_count' => $totalPurchases,
            ],
            'margin' => [
                'operating_margin' => $operatingMargin,
                'margin_percentage' => $marginPercentage,
            ],
        ];
    }

    /**
     * Get daily sales trend for Chart.js line charts in the selected date range.
     *
     * @return array<int, array{date: string, label: string, sales: float, orders_count: int}>
     */
    public function getDailySalesTrend(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $driver = DB::getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', customer_orders.created_at)"
            : "to_char(customer_orders.created_at, 'YYYY-MM-DD')";

        $query = CustomerOrder::where('customer_orders.tenant_id', $tenantId)
            ->where('customer_orders.status', '!=', 'cancelado')
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $query->where('customer_orders.warehouse_id', $warehouseId);
        }

        $records = $query->selectRaw("{$dateExpr} as order_date, COUNT(*) as orders_count, COALESCE(SUM(total_amount), 0) as total_sales")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'asc')
            ->get()
            ->keyBy('order_date');

        // Fill all days between $from and $to so the line chart is continuous
        $trend = [];
        $cursor = $from->copy()->startOfDay();
        $limit = $to->copy()->startOfDay();

        while ($cursor->lte($limit)) {
            $dateKey = $cursor->format('Y-m-d');
            $row = $records->get($dateKey);

            $trend[] = [
                'date' => $dateKey,
                'label' => $cursor->format('d/m'),
                'sales' => $row ? round((float) $row->total_sales, 2) : 0.0,
                'orders_count' => $row ? (int) $row->orders_count : 0,
            ];

            $cursor = $cursor->addDay();
        }

        return $trend;
    }

    /**
     * Get top selling products in the selected period.
     *
     * @return array<int, array{product_id: int, name: string, sku: string|null, total_quantity: float, total_subtotal: float}>
     */
    public function getTopProducts(int $tenantId, CarbonInterface $from, CarbonInterface $to, int $limit = 10, ?int $warehouseId = null): array
    {
        $query = DB::table('customer_order_items')
            ->join('customer_orders', 'customer_orders.id', '=', 'customer_order_items.customer_order_id')
            ->leftJoin('products', 'products.id', '=', 'customer_order_items.product_id')
            ->where('customer_orders.tenant_id', $tenantId)
            ->where('customer_orders.status', '!=', 'cancelado')
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $query->where('customer_orders.warehouse_id', $warehouseId);
        }

        return $query->select(
            'customer_order_items.product_id',
            DB::raw('COALESCE(products.name, "Producto") as name'),
            'products.sku',
            DB::raw('COALESCE(SUM(customer_order_items.quantity), 0) as total_quantity'),
            DB::raw('COALESCE(SUM(customer_order_items.subtotal), 0) as total_subtotal')
        )
            ->groupBy('customer_order_items.product_id', 'products.name', 'products.sku')
            ->orderByDesc('total_subtotal')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => (string) $row->name,
                'sku' => $row->sku ? (string) $row->sku : null,
                'total_quantity' => round((float) $row->total_quantity, 2),
                'total_subtotal' => round((float) $row->total_subtotal, 2),
            ])
            ->all();
    }
}
