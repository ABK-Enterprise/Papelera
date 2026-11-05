<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BusinessReportService
{
    /**
     * Determine temporal aggregation granularity ('daily', 'weekly', 'monthly')
     * based on the distance between start and end dates.
     */
    public function determineGranularity(CarbonInterface $from, CarbonInterface $to): string
    {
        $days = $from->diffInDays($to);

        if ($days < 30) {
            return 'daily';
        }

        if ($days <= 60) {
            return 'weekly';
        }

        return 'monthly';
    }

    /**
     * Get SQL date expression compatible with SQLite and PostgreSQL without double quotes.
     */
    protected function getDateExpression(string $column, string $granularity): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return match ($granularity) {
                'weekly' => "strftime('%Y-%W', {$column})",
                'monthly' => "strftime('%Y-%m', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            };
        }

        return match ($granularity) {
            'weekly' => "to_char({$column}, 'IYYY-IW')",
            'monthly' => "to_char({$column}, 'YYYY-MM')",
            default => "to_char({$column}, 'YYYY-MM-DD')",
        };
    }

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
     * Pantalla Inicial (Overview / Resumen Ejecutivo)
     * Reúne macroeconomía: ingresos (ventas cobradas + facturadas), gastos en compras válidas y margen operativo.
     */
    public function getOverviewData(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null, ?int $year = null): array
    {
        // 1. Ingresos (Ventas cobradas o facturadas, no canceladas)
        $salesQuery = CustomerOrder::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('payment_status', 'cobrado')
                    ->orWhere('fiscal_status', 'facturado');
            })
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $totalRevenues = (float) $salesQuery->sum('total_amount');

        // 2. Gastos en compras (Órdenes de compra no canceladas)
        $expensesQuery = SupplierOrder::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelado')
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $expensesQuery->where('warehouse_id', $warehouseId);
        }

        $totalExpenses = (float) $expensesQuery->sum('total_cost');

        // 3. Margen operativo
        $operatingMargin = round($totalRevenues - $totalExpenses, 2);

        // 4. Curva de ventas día a día con granularidad dinámica
        $salesTrend = $this->getSalesTrendSeries($tenantId, $from, $to, $warehouseId);

        // 5. Comparativa mensual (+20px)
        $monthlyComparison = $this->getMonthlySummary($tenantId, $year ?? (int) $from->year);

        // 6. Detalle de cobranzas registradas
        $receiptsDetail = Receipt::where('tenant_id', $tenantId)
            ->where('is_cancelled', false)
            ->whereBetween('created_at', [$from, $to])
            ->latest('id')
            ->limit(10)
            ->get(['id', 'receipt_number', 'payment_method', 'amount', 'created_at'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'payment_method' => $r->payment_method,
                'amount' => (float) $r->amount,
                'date' => $r->created_at->format('d/m/Y H:i'),
            ])
            ->all();

        // 7. Top 10 Productos más vendidos (cobrado o facturado)
        $topProducts = $this->getTopProducts($tenantId, $from, $to, 10, $warehouseId);

        // 8. Top 10 Compradores (Clientes con mayor facturación/cobro)
        $topBuyersQuery = DB::table('customer_orders')
            ->leftJoin('customers', 'customers.id', '=', 'customer_orders.customer_id')
            ->where('customer_orders.tenant_id', $tenantId)
            ->where('customer_orders.status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('customer_orders.payment_status', 'cobrado')
                    ->orWhere('customer_orders.fiscal_status', 'facturado');
            })
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $topBuyersQuery->where('customer_orders.warehouse_id', $warehouseId);
        }

        $topBuyers = $topBuyersQuery->select(
            'customer_orders.customer_id',
            'customers.name',
            DB::raw('COUNT(customer_orders.id) as orders_count'),
            DB::raw('COALESCE(SUM(customer_orders.total_amount), 0) as total_revenue')
        )
            ->groupBy('customer_orders.customer_id', 'customers.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'customer_id' => (int) $r->customer_id,
                'name' => (string) ($r->name ?? 'Cliente Mostrador'),
                'orders_count' => (int) $r->orders_count,
                'total_revenue' => round((float) $r->total_revenue, 2),
            ])
            ->all();

        return [
            'kpis' => [
                'total_expenses' => round($totalExpenses, 2),
                'total_revenues' => round($totalRevenues, 2),
                'operating_margin' => $operatingMargin,
            ],
            'sales_trend' => $salesTrend,
            'monthly_comparison' => $monthlyComparison,
            'receipts_detail' => $receiptsDetail,
            'top_products' => $topProducts,
            'top_buyers' => $topBuyers,
        ];
    }

    /**
     * Pantalla de Pedidos (Estadísticas Comerciales)
     */
    public function getOrdersData(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $baseQuery = CustomerOrder::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $baseQuery->where('warehouse_id', $warehouseId);
        }

        $allOrders = (clone $baseQuery)->get(['id', 'status', 'payment_status', 'fiscal_status', 'total_amount']);

        $totalOrders = $allOrders->count();
        $deliveredOrders = $allOrders->where('status', 'entregado')->count();
        $cancelledOrders = $allOrders->where('status', 'cancelado')->count();
        $collectedOrders = $allOrders->filter(fn ($o) => $o->payment_status === 'cobrado' || $o->fiscal_status === 'facturado')->count();
        $totalRevenues = (float) $allOrders->filter(fn ($o) => $o->status !== 'cancelado' && ($o->payment_status === 'cobrado' || $o->fiscal_status === 'facturado'))->sum('total_amount');

        // Curva temporal de pedidos creados
        $ordersTrend = $this->getOrdersTrendSeries($tenantId, $from, $to, $warehouseId);

        return [
            'kpis' => [
                'total_orders' => $totalOrders,
                'delivered_orders' => $deliveredOrders,
                'cancelled_orders' => $cancelledOrders,
                'collected_orders' => $collectedOrders,
                'total_revenues' => round($totalRevenues, 2),
            ],
            'orders_trend' => $ordersTrend,
        ];
    }

    /**
     * Pantalla de Clientes (Cuentas Corrientes y Cartera)
     */
    public function getCustomersData(
        int $tenantId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $warehouseId = null,
        string $buyersSort = 'desc',
        string $defaultersSort = 'desc',
        int $defaultersPage = 1,
        int $perPage = 10
    ): array {
        // KPIs
        $totalCustomers = Customer::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $defaulterCustomers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_defaulter', true)
                    ->orWhere('current_balance', '>', 0);
            })
            ->count();

        // Columna Izquierda: Top 10 Clientes compradores
        $buyersQuery = DB::table('customer_orders')
            ->join('customers', 'customers.id', '=', 'customer_orders.customer_id')
            ->where('customer_orders.tenant_id', $tenantId)
            ->where('customer_orders.status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('customer_orders.payment_status', 'cobrado')
                    ->orWhere('customer_orders.fiscal_status', 'facturado');
            })
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $buyersQuery->where('customer_orders.warehouse_id', $warehouseId);
        }

        $buyersQuery->select(
            'customer_orders.customer_id',
            'customers.name',
            DB::raw('COUNT(customer_orders.id) as orders_count'),
            DB::raw('COALESCE(SUM(customer_orders.total_amount), 0) as total_spent')
        )->groupBy('customer_orders.customer_id', 'customers.name');

        if ($buyersSort === 'asc') {
            $buyersQuery->orderBy('orders_count', 'asc')->orderBy('total_spent', 'asc');
        } else {
            $buyersQuery->orderByDesc('orders_count')->orderByDesc('total_spent');
        }

        $topBuyers = $buyersQuery->limit(10)->get()->map(fn ($r) => [
            'customer_id' => (int) $r->customer_id,
            'name' => (string) ($r->name ?? 'Cliente Mostrador'),
            'orders_count' => (int) $r->orders_count,
            'total_spent' => round((float) $r->total_spent, 2),
        ])->all();

        // Columna Derecha: Clientes Morosos (Paginada de a 10)
        $defaultersQuery = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_defaulter', true)
                    ->orWhere('current_balance', '>', 0);
            });

        if ($defaultersSort === 'asc') {
            $defaultersQuery->orderBy('current_balance', 'asc');
        } else {
            $defaultersQuery->orderByDesc('current_balance');
        }

        $paginatedDefaulters = $defaultersQuery->paginate($perPage, ['id', 'name', 'phone', 'current_balance'], 'page', $defaultersPage);

        return [
            'kpis' => [
                'total_customers' => $totalCustomers,
                'defaulter_customers' => $defaulterCustomers,
            ],
            'top_buyers' => $topBuyers,
            'defaulters' => [
                'data' => collect($paginatedDefaulters->items())->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'current_balance' => (float) $c->current_balance,
                ])->all(),
                'current_page' => $paginatedDefaulters->currentPage(),
                'last_page' => $paginatedDefaulters->lastPage(),
                'total' => $paginatedDefaulters->total(),
                'per_page' => $paginatedDefaulters->perPage(),
            ],
        ];
    }

    /**
     * Pantalla de Proveedores (Compras y Abastecimiento)
     */
    public function getSuppliersData(
        int $tenantId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $warehouseId = null,
        string $sort = 'desc',
        int $page = 1,
        int $perPage = 10
    ): array {
        // KPIs
        $totalSuppliers = Supplier::where('tenant_id', $tenantId)->where('is_active', true)->count();

        // Gastos mes actual
        $currentMonthExpenses = (float) SupplierOrder::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelado')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfDay()])
            ->sum('total_cost');

        // Gastos totales en el rango seleccionado
        $expensesQuery = SupplierOrder::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelado')
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $expensesQuery->where('warehouse_id', $warehouseId);
        }

        $totalExpenses = (float) $expensesQuery->sum('total_cost');

        // Listado wide-cards paginado de a 10 ordenado por orders_count
        $suppliersQuery = DB::table('supplier_orders')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_orders.supplier_id')
            ->where('supplier_orders.tenant_id', $tenantId)
            ->where('supplier_orders.status', '!=', 'cancelado')
            ->whereBetween('supplier_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $suppliersQuery->where('supplier_orders.warehouse_id', $warehouseId);
        }

        $suppliersQuery->select(
            'supplier_orders.supplier_id',
            'suppliers.name',
            DB::raw('COUNT(supplier_orders.id) as orders_count'),
            DB::raw('COALESCE(SUM(supplier_orders.total_cost), 0) as total_spent')
        )->groupBy('supplier_orders.supplier_id', 'suppliers.name');

        if ($sort === 'asc') {
            $suppliersQuery->orderBy('orders_count', 'asc')->orderBy('total_spent', 'asc');
        } else {
            $suppliersQuery->orderByDesc('orders_count')->orderByDesc('total_spent');
        }

        $paginated = $suppliersQuery->paginate($perPage, ['*'], 'page', $page);

        return [
            'kpis' => [
                'total_suppliers' => $totalSuppliers,
                'expenses_current_month' => round($currentMonthExpenses, 2),
                'total_expenses' => round($totalExpenses, 2),
            ],
            'suppliers' => [
                'data' => collect($paginated->items())->map(fn ($r) => [
                    'supplier_id' => (int) $r->supplier_id,
                    'name' => (string) $r->name,
                    'orders_count' => (int) $r->orders_count,
                    'total_spent' => round((float) $r->total_spent, 2),
                ])->all(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ],
        ];
    }

    /**
     * Pantalla de Productos (Stock e Inventario)
     */
    public function getProductsData(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        // 1. Catálogo total
        $catalogProductsCount = Product::where('tenant_id', $tenantId)->where('is_active', true)->count();

        // 2. Ingresos y egresos de stock
        $inflowQuery = StockMovement::where('tenant_id', $tenantId)
            ->where('type', 'income')
            ->whereBetween('created_at', [$from, $to]);

        $outflowQuery = StockMovement::where('tenant_id', $tenantId)
            ->where('type', 'outcome')
            ->whereBetween('created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $inflowQuery->where('warehouse_id', $warehouseId);
            $outflowQuery->where('warehouse_id', $warehouseId);
        }

        $stockInflow = (float) $inflowQuery->sum('quantity');
        $stockOutflow = (float) $outflowQuery->sum('quantity');

        // 3. Gráfico temporal: Cantidad de productos vendidos
        $productsSoldTrend = $this->getProductsSoldTrendSeries($tenantId, $from, $to, $warehouseId);

        // 4. Top 10 Productos más vendidos
        $topProducts = $this->getTopProducts($tenantId, $from, $to, 10, $warehouseId);

        return [
            'kpis' => [
                'catalog_products_count' => $catalogProductsCount,
                'stock_inflow' => round($stockInflow, 2),
                'stock_outflow' => round($stockOutflow, 2),
            ],
            'products_sold_trend' => $productsSoldTrend,
            'top_products' => $topProducts,
        ];
    }

    /**
     * Helper for sales trend series with dynamic granularity and zero-filling.
     */
    protected function getSalesTrendSeries(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $granularity = $this->determineGranularity($from, $to);
        $dateExpr = $this->getDateExpression('customer_orders.created_at', $granularity);

        $query = CustomerOrder::where('customer_orders.tenant_id', $tenantId)
            ->where('customer_orders.status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('customer_orders.payment_status', 'cobrado')
                    ->orWhere('customer_orders.fiscal_status', 'facturado');
            })
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $query->where('customer_orders.warehouse_id', $warehouseId);
        }

        $records = $query->selectRaw("{$dateExpr} as period_key, COUNT(*) as orders_count, COALESCE(SUM(total_amount), 0) as total_sales")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'asc')
            ->get()
            ->keyBy('period_key');

        return $this->fillTrendSeries($from, $to, $granularity, $records, 'total_sales');
    }

    /**
     * Helper for orders trend series with dynamic granularity and zero-filling.
     */
    protected function getOrdersTrendSeries(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $granularity = $this->determineGranularity($from, $to);
        $dateExpr = $this->getDateExpression('customer_orders.created_at', $granularity);

        $query = CustomerOrder::where('customer_orders.tenant_id', $tenantId)
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $query->where('customer_orders.warehouse_id', $warehouseId);
        }

        $records = $query->selectRaw("{$dateExpr} as period_key, COUNT(*) as orders_count")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'asc')
            ->get()
            ->keyBy('period_key');

        return $this->fillTrendSeries($from, $to, $granularity, $records, 'orders_count');
    }

    /**
     * Helper for units of products sold trend series with dynamic granularity and zero-filling.
     */
    protected function getProductsSoldTrendSeries(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $granularity = $this->determineGranularity($from, $to);
        $dateExpr = $this->getDateExpression('customer_orders.created_at', $granularity);

        $query = DB::table('customer_order_items')
            ->join('customer_orders', 'customer_orders.id', '=', 'customer_order_items.customer_order_id')
            ->where('customer_orders.tenant_id', $tenantId)
            ->where('customer_orders.status', '!=', 'cancelado')
            ->where(function ($q) {
                $q->where('customer_orders.payment_status', 'cobrado')
                    ->orWhere('customer_orders.fiscal_status', 'facturado');
            })
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $query->where('customer_orders.warehouse_id', $warehouseId);
        }

        $records = $query->selectRaw("{$dateExpr} as period_key, COALESCE(SUM(customer_order_items.quantity), 0) as total_units")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'asc')
            ->get()
            ->keyBy('period_key');

        return $this->fillTrendSeries($from, $to, $granularity, $records, 'total_units');
    }

    /**
     * Deterministic zero-filled series generator.
     */
    protected function fillTrendSeries(CarbonInterface $from, CarbonInterface $to, string $granularity, $records, string $metricField): array
    {
        $trend = [];
        $cursor = $from->copy();
        $limit = $to->copy();

        if ($granularity === 'daily') {
            $cursor = $cursor->startOfDay();
            $limit = $limit->startOfDay();
            while ($cursor->lte($limit)) {
                $key = $cursor->format('Y-m-d');
                $row = $records->get($key);
                $val = $row ? (float) $row->{$metricField} : 0.0;
                $trend[] = [
                    'key' => $key,
                    'label' => $cursor->format('d/m'),
                    'value' => round($val, 2),
                ];
                $cursor = $cursor->addDay();
            }
        } elseif ($granularity === 'weekly') {
            $cursor = $cursor->startOfWeek();
            $limit = $limit->endOfWeek();
            while ($cursor->lte($limit)) {
                $driver = DB::getDriverName();
                $key = $driver === 'sqlite' ? $cursor->format('Y-W') : sprintf('%d-%02d', $cursor->isoWeekYear(), $cursor->isoWeek());
                $row = $records->get($key);
                $val = $row ? (float) $row->{$metricField} : 0.0;
                $trend[] = [
                    'key' => $key,
                    'label' => 'Sem '.$cursor->isoWeek(),
                    'value' => round($val, 2),
                ];
                $cursor = $cursor->addWeek();
            }
        } else {
            $cursor = $cursor->startOfMonth();
            $limit = $limit->endOfMonth();
            $monthNames = [
                1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
            ];
            while ($cursor->lte($limit)) {
                $key = $cursor->format('Y-m');
                $row = $records->get($key);
                $val = $row ? (float) $row->{$metricField} : 0.0;
                $trend[] = [
                    'key' => $key,
                    'label' => ($monthNames[$cursor->month] ?? $cursor->format('M')).' '.$cursor->format('y'),
                    'value' => round($val, 2),
                ];
                $cursor = $cursor->addMonth();
            }
        }

        return $trend;
    }

    /**
     * Get top selling products in the selected period (cobrado or facturado).
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
            ->where(function ($q) {
                $q->where('customer_orders.payment_status', 'cobrado')
                    ->orWhere('customer_orders.fiscal_status', 'facturado');
            })
            ->whereBetween('customer_orders.created_at', [$from, $to]);

        if ($warehouseId !== null) {
            $query->where('customer_orders.warehouse_id', $warehouseId);
        }

        return $query->select(
            'customer_order_items.product_id',
            'products.name',
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
                'name' => (string) ($row->name ?? 'Producto'),
                'sku' => $row->sku ? (string) $row->sku : null,
                'total_quantity' => round((float) $row->total_quantity, 2),
                'total_subtotal' => round((float) $row->total_subtotal, 2),
            ])
            ->all();
    }

    /**
     * Legacy adapter for PDF export and backward compatibility.
     */
    public function getFilteredMetrics(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        $overview = $this->getOverviewData($tenantId, $from, $to, $warehouseId);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'sales' => [
                'total' => $overview['kpis']['total_revenues'],
                'orders_count' => count($overview['top_buyers']),
                'average_ticket' => 0.0,
            ],
            'revenues' => [
                'total' => $overview['kpis']['total_revenues'],
                'by_method' => [],
            ],
            'expenses' => [
                'total' => $overview['kpis']['total_expenses'],
                'purchases_count' => 0,
            ],
            'margin' => [
                'operating_margin' => $overview['kpis']['operating_margin'],
                'margin_percentage' => 0.0,
            ],
        ];
    }

    /**
     * Legacy adapter for daily sales trend.
     */
    public function getDailySalesTrend(int $tenantId, CarbonInterface $from, CarbonInterface $to, ?int $warehouseId = null): array
    {
        return $this->getSalesTrendSeries($tenantId, $from, $to, $warehouseId);
    }
}
