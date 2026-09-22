<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockMovement;
use App\Models\StockTransfer;
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
    $this->user = User::where('tenant_id', $this->tenant->id)->first();
    $this->originWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();
    $this->branch = $this->tenant->branches()->first();

    $this->destWarehouse = Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'name' => 'Sucursal Norte',
        'code' => 'DEP-02',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->category = Category::where('tenant_id', $this->tenant->id)->first() ?? Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papeles',
        'slug' => 'papeles',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Caja Cartón Grande',
        'sku' => 'BOX-01',
        'price' => 500,
        'stock' => 50,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->originWarehouse->id,
        'stock' => 50,
        'min_stock' => 5,
    ]);

    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->destWarehouse->id,
        'stock' => 0,
        'min_stock' => 5,
    ]);
});

it('can transfer stock between two warehouses atomically', function () {
    $response = $this->actingAs($this->user)
        ->post('/tenant/stock/transferencias', [
            'product_id' => $this->product->id,
            'origin_warehouse_id' => $this->originWarehouse->id,
            'destination_warehouse_id' => $this->destWarehouse->id,
            'quantity' => 15,
            'notes' => 'Transferencia de prueba',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // Check ProductWarehouse local balances
    $originStock = ProductWarehouse::where('tenant_id', $this->tenant->id)
        ->where('product_id', $this->product->id)
        ->where('warehouse_id', $this->originWarehouse->id)
        ->value('stock');

    $destStock = ProductWarehouse::where('tenant_id', $this->tenant->id)
        ->where('product_id', $this->product->id)
        ->where('warehouse_id', $this->destWarehouse->id)
        ->value('stock');

    expect((float) $originStock)->toEqual(35.0)
        ->and((float) $destStock)->toEqual(15.0);

    // Consolidated stock must remain 50
    expect((float) $this->product->fresh()->stock)->toEqual(50.0);

    // StockTransfer entity created
    $transfer = StockTransfer::where('tenant_id', $this->tenant->id)->first();
    expect($transfer)->not->toBeNull()
        ->and($transfer->origin_warehouse_id)->toEqual($this->originWarehouse->id)
        ->and($transfer->destination_warehouse_id)->toEqual($this->destWarehouse->id)
        ->and((float) $transfer->quantity)->toEqual(15.0)
        ->and($transfer->status)->toEqual('completada');

    // 2 movements linked to transfer
    $movements = StockMovement::where('stock_transfer_id', $transfer->id)->get();
    expect($movements)->toHaveCount(2);

    $outcome = $movements->firstWhere('type', 'outcome');
    $income = $movements->firstWhere('type', 'income');

    expect($outcome->warehouse_id)->toEqual($this->originWarehouse->id)
        ->and($outcome->reason)->toEqual('transferencia_deposito')
        ->and((float) $outcome->quantity)->toEqual(15.0)
        ->and($income->warehouse_id)->toEqual($this->destWarehouse->id)
        ->and($income->reason)->toEqual('transferencia_deposito')
        ->and((float) $income->quantity)->toEqual(15.0);
});

it('fails when origin and destination are the same warehouse', function () {
    $response = $this->actingAs($this->user)
        ->post('/tenant/stock/transferencias', [
            'product_id' => $this->product->id,
            'origin_warehouse_id' => $this->originWarehouse->id,
            'destination_warehouse_id' => $this->originWarehouse->id,
            'quantity' => 10,
        ]);

    $response->assertSessionHasErrors(['destination_warehouse_id']);
});

it('allows transfer resulting in negative stock at origin when confirmed', function () {
    // Current stock at origin is 50, transfer 70
    $response = $this->actingAs($this->user)
        ->post('/tenant/stock/transferencias', [
            'product_id' => $this->product->id,
            'origin_warehouse_id' => $this->originWarehouse->id,
            'destination_warehouse_id' => $this->destWarehouse->id,
            'quantity' => 70,
            'allow_negative_origin' => true,
        ]);

    $response->assertSessionHasNoErrors();

    $originStock = ProductWarehouse::where('tenant_id', $this->tenant->id)
        ->where('product_id', $this->product->id)
        ->where('warehouse_id', $this->originWarehouse->id)
        ->value('stock');

    $destStock = ProductWarehouse::where('tenant_id', $this->tenant->id)
        ->where('product_id', $this->product->id)
        ->where('warehouse_id', $this->destWarehouse->id)
        ->value('stock');

    expect((float) $originStock)->toEqual(-20.0)
        ->and((float) $destStock)->toEqual(70.0)
        ->and((float) $this->product->fresh()->stock)->toEqual(50.0);
});

it('fails when quantity is zero or negative', function () {
    $response = $this->actingAs($this->user)
        ->post('/tenant/stock/transferencias', [
            'product_id' => $this->product->id,
            'origin_warehouse_id' => $this->originWarehouse->id,
            'destination_warehouse_id' => $this->destWarehouse->id,
            'quantity' => 0,
        ]);

    $response->assertSessionHasErrors(['quantity']);
});
