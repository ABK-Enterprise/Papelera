<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\Reports\BusinessReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BusinessReportService::class);
    $this->tenant = Tenant::factory()->create(['name' => 'Papelera Test Analytics']);
    $this->warehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Central',
        'code' => 'DEP-01',
        'is_active' => true,
        'is_default' => true,
    ]);
});

test('getOverviewData calculates revenues including both cobrado and facturado orders while excluding cancelled orders and expenses', function () {
    $customer1 = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente Mayorista']);
    $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente Mostrador']);

    $now = CarbonImmutable::now();
    $from = $now->startOfMonth();
    $to = $now->endOfMonth();

    // 1. Order Cobrada (not invoiced)
    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer1->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-01',
        'customer_name' => $customer1->name,
        'status' => 'entregado',
        'payment_status' => 'cobrado',
        'fiscal_status' => 'no_facturado',
        'subtotal_amount' => 5000,
        'total_amount' => 5000,
        'created_at' => $now,
    ]);

    // 2. Order Facturada (pending collection)
    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer2->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-02',
        'customer_name' => $customer2->name,
        'status' => 'entregado',
        'payment_status' => 'pendiente',
        'fiscal_status' => 'facturado',
        'subtotal_amount' => 8000,
        'total_amount' => 8000,
        'created_at' => $now,
    ]);

    // 3. Cancelled Order (should be excluded)
    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer1->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-03',
        'customer_name' => $customer1->name,
        'status' => 'cancelado',
        'payment_status' => 'cobrado',
        'fiscal_status' => 'facturado',
        'subtotal_amount' => 20000,
        'total_amount' => 20000,
        'created_at' => $now,
    ]);

    // 4. Supplier Order valid
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Proveedor Kraft S.A.',
        'is_active' => true,
    ]);
    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SOC-01',
        'status' => 'recibido',
        'total_cost' => 4000,
        'created_at' => $now,
    ]);

    // 5. Supplier Order cancelled (should be excluded)
    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SOC-02',
        'status' => 'cancelado',
        'total_cost' => 15000,
        'created_at' => $now,
    ]);

    $data = $this->service->getOverviewData($this->tenant->id, $from, $to, $this->warehouse->id);

    expect($data)->toHaveKeys(['kpis', 'sales_trend', 'monthly_comparison', 'receipts_detail', 'top_products', 'top_buyers']);
    expect($data['kpis']['total_revenues'])->toEqual(13000.0); // 5000 + 8000
    expect($data['kpis']['total_expenses'])->toEqual(4000.0);
    expect($data['kpis']['operating_margin'])->toEqual(9000.0); // 13000 - 4000
    expect($data['top_buyers'])->toHaveCount(2);
    expect($data['top_buyers'][0]['name'])->toEqual('Cliente Mostrador');
    expect($data['top_buyers'][0]['total_revenue'])->toEqual(8000.0);
});

test('getOrdersData aggregates delivered, cancelled, collected and total orders with temporal trend', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $now = CarbonImmutable::now();
    $from = $now->startOfMonth();
    $to = $now->endOfMonth();

    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-10',
        'customer_name' => $customer->name,
        'status' => 'entregado',
        'payment_status' => 'cobrado',
        'subtotal_amount' => 1000,
        'total_amount' => 1000,
        'created_at' => $now,
    ]);

    CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-11',
        'customer_name' => $customer->name,
        'status' => 'cancelado',
        'payment_status' => 'pendiente',
        'subtotal_amount' => 1000,
        'total_amount' => 1000,
        'created_at' => $now,
    ]);

    $data = $this->service->getOrdersData($this->tenant->id, $from, $to, $this->warehouse->id);

    expect($data)->toHaveKeys(['kpis', 'orders_trend']);
    expect($data['kpis']['total_orders'])->toBe(2);
    expect($data['kpis']['delivered_orders'])->toBe(1);
    expect($data['kpis']['cancelled_orders'])->toBe(1);
    expect($data['kpis']['collected_orders'])->toBe(1);
    expect($data['kpis']['total_revenues'])->toEqual(1000.0);
});

test('getCustomersData reports active debtors and paginated defaulters', function () {
    $now = CarbonImmutable::now();
    $from = $now->startOfMonth();
    $to = $now->endOfMonth();

    // Debtor customer
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Deudor 1',
        'current_balance' => 15000.00,
        'is_active' => true,
    ]);

    // Clean customer
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Al Día',
        'current_balance' => 0.00,
        'is_active' => true,
    ]);

    $data = $this->service->getCustomersData($this->tenant->id, $from, $to, $this->warehouse->id);

    expect($data)->toHaveKeys(['kpis', 'top_buyers', 'defaulters']);
    expect($data['kpis']['total_customers'])->toBe(2);
    expect($data['kpis']['defaulter_customers'])->toBe(1);
    expect($data['defaulters']['data'])->toHaveCount(1);
    expect($data['defaulters']['data'][0]['name'])->toBe('Deudor 1');
});

test('getSuppliersData counts purchases and sorts suppliers by orders_count', function () {
    $now = CarbonImmutable::now();
    $from = $now->startOfMonth();
    $to = $now->endOfMonth();

    $sup1 = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Proveedor A', 'is_active' => true]);
    $sup2 = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Proveedor B', 'is_active' => true]);

    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $sup1->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SOC-A1',
        'status' => 'recibido',
        'total_cost' => 500,
        'created_at' => $now,
    ]);
    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $sup1->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SOC-A2',
        'status' => 'recibido',
        'total_cost' => 500,
        'created_at' => $now,
    ]);
    SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $sup2->id,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'SOC-B1',
        'status' => 'recibido',
        'total_cost' => 10000,
        'created_at' => $now,
    ]);

    $data = $this->service->getSuppliersData($this->tenant->id, $from, $to, $this->warehouse->id, 'desc', 1, 10);

    expect($data)->toHaveKeys(['kpis', 'suppliers']);
    expect($data['kpis']['total_suppliers'])->toBe(2);
    expect($data['kpis']['total_expenses'])->toEqual(11000.0);
    // Ordered by orders_count desc: Sup1 has 2 orders, Sup2 has 1 order
    expect($data['suppliers']['data'][0]['name'])->toBe('Proveedor A');
    expect($data['suppliers']['data'][0]['orders_count'])->toBe(2);
});

test('getProductsData tallies catalog stock movements and units sold', function () {
    $now = CarbonImmutable::now();
    $from = $now->startOfMonth();
    $to = $now->endOfMonth();

    $prod = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papel Obra 80g',
        'sku' => 'OBRA-80',
    ]);

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $prod->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'income',
        'quantity' => 100,
        'previous_stock' => 0,
        'current_stock' => 100,
        'created_at' => $now,
    ]);

    StockMovement::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $prod->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'outcome',
        'quantity' => 25,
        'previous_stock' => 100,
        'current_stock' => 75,
        'created_at' => $now,
    ]);

    $data = $this->service->getProductsData($this->tenant->id, $from, $to, $this->warehouse->id);

    expect($data)->toHaveKeys(['kpis', 'products_sold_trend', 'top_products']);
    expect($data['kpis']['catalog_products_count'])->toBe(1);
    expect($data['kpis']['stock_inflow'])->toEqual(100.0);
    expect($data['kpis']['stock_outflow'])->toEqual(25.0);
});

test('getProductsData supports sorting by mas vendidos and menos vendidos', function () {
    $now = CarbonImmutable::now();
    $from = $now->startOfMonth();
    $to = $now->endOfMonth();

    $cust = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $p1 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Menos Vendido']);
    $p2 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Mas Vendido']);

    $order = CustomerOrder::create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $cust->id,
        'customer_name' => $cust->name,
        'warehouse_id' => $this->warehouse->id,
        'order_number' => 'ORD-SORT-01',
        'status' => 'entregado',
        'payment_status' => 'cobrado',
        'subtotal_amount' => 1500,
        'total_amount' => 1500,
        'created_at' => $now,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $p1->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 2,
        'unit_price' => 100,
        'subtotal' => 200,
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $p2->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 20,
        'unit_price' => 100,
        'subtotal' => 2000,
    ]);

    $desc = $this->service->getProductsData($this->tenant->id, $from, $to, $this->warehouse->id, 'desc');
    expect($desc['top_products'][0]['name'])->toBe('Mas Vendido');

    $asc = $this->service->getProductsData($this->tenant->id, $from, $to, $this->warehouse->id, 'asc');
    expect($asc['top_products'][0]['name'])->toBe('Menos Vendido');
});
