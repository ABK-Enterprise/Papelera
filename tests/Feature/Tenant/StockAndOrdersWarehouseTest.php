<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $this->branch = $this->tenant->branches()->first();
    $this->warehouse2 = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'name' => 'Depósito Secundario',
        'code' => 'DEP-02',
        'is_default' => false,
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cinta de Embalar 48x100',
        'sku' => 'CIN-EMB-48',
        'price' => 2000,
        'cost_price' => 1200,
        'stock' => 0,
        'min_stock' => 5,
    ]);
});

test('manual stock income credits to specific warehouse and updates consolidated stock', function () {
    $response = $this->actingAs($this->adminUser)->post(route('tenant.stock.incomes.store'), [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'quantity' => 25,
        'reason' => 'ingreso_manual',
        'notes' => 'Ingreso a depósito secundario',
    ]);

    $response->assertRedirect(route('tenant.stock.incomes.index'));

    expect($this->product->stockInWarehouse($this->warehouse2))->toBe(25.0)
        ->and((float) $this->product->fresh()->stock)->toBe(25.0);

    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'type' => 'income',
        'quantity' => 25,
    ]);
});

test('manual stock outcome deducts from specific warehouse and fails if local warehouse stock is insufficient', function () {
    // Initial stock: 20 in default warehouse, 10 in warehouse2
    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'stock' => 20,
        'min_stock' => 5,
    ]);

    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'stock' => 10,
        'min_stock' => 5,
    ]);

    $this->product->syncConsolidatedStock();

    // Attempt to withdraw 15 from warehouse2 (which only has 10)
    $response = $this->actingAs($this->adminUser)->post(route('tenant.stock.outcomes.store'), [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'quantity' => 15,
        'reason' => 'devolucion_cliente',
    ]);

    $response->assertSessionHasErrors('quantity');

    // Withdraw 5 from warehouse2 (valid)
    $responseValid = $this->actingAs($this->adminUser)->post(route('tenant.stock.outcomes.store'), [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'quantity' => 5,
        'reason' => 'devolucion_cliente',
    ]);

    $responseValid->assertRedirect(route('tenant.stock.outcomes.index'));

    expect($this->product->stockInWarehouse($this->warehouse2))->toBe(5.0)
        ->and($this->product->stockInWarehouse($this->defaultWarehouse))->toBe(20.0)
        ->and((float) $this->product->fresh()->stock)->toBe(25.0);
});

test('supplier order reception credits stock to target warehouse', function () {
    $supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Distribuidora Papelera',
        'is_active' => true,
    ]);

    $order = SupplierOrder::create([
        'tenant_id' => $this->tenant->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouse2->id,
        'order_number' => 'OC-0001',
        'status' => 'solicitado',
        'total_cost' => 50000,
    ]);

    $order->items()->create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'product_name' => $this->product->name,
        'quantity' => 40,
        'unit_cost' => 1250,
        'subtotal' => 50000,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->from(route('tenant.suppliers.orders.index'))
        ->patch(route('tenant.suppliers.orders.receive', $order));

    $response->assertRedirect(route('tenant.suppliers.orders.index'));


    expect($this->product->stockInWarehouse($this->warehouse2))->toBe(40.0)
        ->and((float) $this->product->fresh()->stock)->toBe(40.0);

    $this->assertDatabaseHas('stock_movements', [
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse2->id,
        'type' => 'income',
        'reason' => 'orden_compra_proveedor',
    ]);

});
