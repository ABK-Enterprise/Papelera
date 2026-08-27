<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Papelera Centro Test',
        'cuit_tax_id' => '30-99887766-4',
        'is_active' => true,
    ]);
    $this->tenant->modules()->syncWithoutDetaching(Module::pluck('id'));

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'admin@papelera.test',
        'is_superadmin' => false,
    ]);
    $this->user->assignRole($this->adminRole);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papeles a Granel',
        'slug' => 'papeles-granel',
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Distribuidora Mayorista SRL',
        'is_active' => true,
    ]);
});

test('1. StockIncomeController preserva fracciones decimales en ingresos de stock', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Papel Kraft 120g en Bobina',
        'sale_type' => 'weight',
        'stock' => 10.750,
        'min_stock' => 2.500,
        'price' => 5000.00,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.stock.incomes.store'), [
            'product_id' => $product->id,
            'quantity' => 2.500,
            'reason' => 'ingreso_manual',
            'notes' => 'Ingreso de bobina fraccional 2.5kg',
        ]);

    $response->assertStatus(201);

    $product->refresh();
    expect((float) $product->stock)->toBe(13.25);

    $movement = StockMovement::where('product_id', $product->id)->latest()->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(2.5)
        ->and((float) $movement->previous_stock)->toBe(10.75)
        ->and((float) $movement->current_stock)->toBe(13.25);
});

test('2. StockOutcomeController preserva fracciones decimales en egresos de stock', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Film Stretch por Kg',
        'sale_type' => 'weight',
        'stock' => 5.500,
        'min_stock' => 1.000,
        'price' => 3200.00,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.stock.outcomes.store'), [
            'product_id' => $product->id,
            'quantity' => 0.800,
            'reason' => 'devolucion_cliente',
            'notes' => 'Egreso fraccional de 800 gramos',
        ]);

    $response->assertStatus(201);

    $product->refresh();
    expect((float) $product->stock)->toBe(4.7);

    $movement = StockMovement::where('product_id', $product->id)->latest()->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(0.8)
        ->and((float) $movement->previous_stock)->toBe(5.5)
        ->and((float) $movement->current_stock)->toBe(4.7);
});

test('3. SupplierOrder admite cantidades fraccionales y actualiza stock decimal al recepcionarse', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Bolsas de Polietileno a Granel',
        'sale_type' => 'weight',
        'stock' => 3.250,
        'min_stock' => 1.000,
        'price' => 4500.00,
        'is_active' => true,
    ]);

    // Crear orden de compra con 15.5 Kg
    $createResponse = $this->actingAs($this->user)
        ->postJson(route('tenant.suppliers.orders.store'), [
            'supplier_id' => $this->supplier->id,
            'notes' => 'Compra fraccional de materia prima',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => 15.500,
                    'unit_cost' => 2000.00,
                ],
            ],
        ]);

    $createResponse->assertStatus(201);

    $order = SupplierOrder::where('supplier_id', $this->supplier->id)->latest()->first();
    expect($order)->not->toBeNull();

    $item = $order->items->first();
    expect((float) $item->quantity)->toBe(15.5)
        ->and((float) $item->subtotal)->toBe(31000.0);

    // Recepcionar la orden de compra
    $receiveResponse = $this->actingAs($this->user)
        ->patchJson(route('tenant.suppliers.orders.receive', $order->id));

    $receiveResponse->assertStatus(200);

    $product->refresh();
    // 3.250 + 15.500 = 18.750
    expect((float) $product->stock)->toBe(18.75);

    $movement = StockMovement::where('product_id', $product->id)->latest()->first();
    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(15.5)
        ->and((float) $movement->previous_stock)->toBe(3.25)
        ->and((float) $movement->current_stock)->toBe(18.75);
});
