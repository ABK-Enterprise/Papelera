<?php

use App\Enums\SystemModule;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantPriceAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::firstOrCreate(
        ['code' => SystemModule::RecargosDescuentos->value],
        ['name' => SystemModule::RecargosDescuentos->label(), 'is_active' => true]
    );

    $this->pedidosModule = Module::firstOrCreate(
        ['code' => SystemModule::Pedidos->value],
        ['name' => SystemModule::Pedidos->label(), 'is_active' => true]
    );

    $this->tenant->modules()->sync([$this->module->id, $this->pedidosModule->id]);

    setPermissionsTeamId($this->tenant->id);

    Permission::firstOrCreate(['name' => 'pedidos.editar_estado', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'pedidos.ver', 'guard_name' => 'web']);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(['pedidos.editar_estado', 'pedidos.ver']);

    $this->adminUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->adminUser->assignRole($this->adminRole);
});

test('can atomically apply catalog presets using sync route', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 1000,
    ]);

    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000,
        'total_amount' => 1000,
        'shipping_cost' => 0,
        'price_adjustments' => [],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    $preset = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Efectivo 10%',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes/sincronizar", [
            'preset_ids' => [$preset->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);

    $order->refresh();
    expect($order->price_adjustments)->toHaveCount(1)
        ->and($order->discount_total)->toBe('100.00')
        ->and((float) $order->total_amount)->toBe(900.0);
});

test('unchecking a preset removes it and restores totals', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 1000,
    ]);

    $preset = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Efectivo 10%',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000,
        'total_amount' => 900,
        'discount_total' => 100,
        'shipping_cost' => 0,
        'price_adjustments' => [
            [
                'id' => 'preset-uuid-1',
                'source' => 'preset',
                'source_id' => $preset->id,
                'type' => 'discount',
                'name' => $preset->name,
                'calculation_type' => 'percentage',
                'value' => 10,
                'applied_amount' => 100,
                'reason' => null,
                'show_in_receipt' => true,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    // Sync with empty preset_ids (user deselected the discount)
    $response = $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes/sincronizar", [
            'preset_ids' => [],
        ]);

    $response->assertOk();

    $order->refresh();
    expect($order->price_adjustments)->toBeEmpty()
        ->and((float) $order->discount_total)->toBe(0.0)
        ->and((float) $order->total_amount)->toBe(1000.0);
});

test('sync preserves ad_hoc adjustments intact', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 1000,
    ]);

    $preset = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Nuevo',
        'type' => 'discount',
        'calculation_type' => 'fixed',
        'value' => 150,
        'is_active' => true,
    ]);

    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000,
        'total_amount' => 1050,
        'surcharge_total' => 50,
        'shipping_cost' => 0,
        'price_adjustments' => [
            [
                'id' => 'adhoc-uuid-1',
                'source' => 'ad_hoc',
                'source_id' => null,
                'type' => 'surcharge',
                'name' => 'Embalaje especial',
                'calculation_type' => 'fixed',
                'value' => 50,
                'applied_amount' => 50,
                'reason' => 'Caja reforzada',
                'show_in_receipt' => true,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes/sincronizar", [
            'preset_ids' => [$preset->id],
        ])
        ->assertOk();

    $order->refresh();
    expect($order->price_adjustments)->toHaveCount(2);

    $adHoc = collect($order->price_adjustments)->firstWhere('source', 'ad_hoc');
    expect($adHoc)->not->toBeNull()
        ->and($adHoc['name'])->toBe('Embalaje especial');

    $syncedPreset = collect($order->price_adjustments)->firstWhere('source', 'preset');
    expect($syncedPreset)->not->toBeNull()
        ->and($syncedPreset['name'])->toBe('Descuento Nuevo');

    // Total = 1000 + 50 (surcharge) - 150 (discount) = 900
    expect((float) $order->total_amount)->toBe(900.0)
        ->and((float) $order->surcharge_total)->toBe(50.0)
        ->and((float) $order->discount_total)->toBe(150.0);
});

test('sync records consolidated customer account movement when balance changes', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_balance' => 1000,
    ]);

    $preset = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento 100',
        'type' => 'discount',
        'calculation_type' => 'fixed',
        'value' => 100,
        'is_active' => true,
    ]);

    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000,
        'total_amount' => 1000,
        'debt_amount' => 1000,
        'shipping_cost' => 0,
        'price_adjustments' => [],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => Product::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 1000])->id,
        'product_name' => 'Producto Test',
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes/sincronizar", [
            'preset_ids' => [$preset->id],
        ])
        ->assertOk();

    $order->refresh();
    $customer->refresh();

    expect((float) $order->total_amount)->toBe(900.0)
        ->and((float) $order->debt_amount)->toBe(900.0)
        ->and((float) $customer->current_balance)->toBe(900.0);

    $movement = CustomerAccountMovement::where('customer_order_id', $order->id)->latest()->first();
    expect($movement)->not->toBeNull()
        ->and($movement->reference_number)->toContain('SYNC-ADJ-')
        ->and((float) $movement->amount)->toBe(100.0);
});

test('cannot sync adjustments on invoiced or terminal order', function () {
    $invoicedOrder = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'facturado',
    ]);

    $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$invoicedOrder->id}/ajustes/sincronizar", [
            'preset_ids' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    $deliveredOrder = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'entregado',
        'fiscal_status' => 'sin_facturar',
    ]);

    $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$deliveredOrder->id}/ajustes/sincronizar", [
            'preset_ids' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('store rejects adding a preset that already exists on the order', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 1000,
    ]);

    $preset = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Efectivo',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000,
        'total_amount' => 900,
        'discount_total' => 100,
        'shipping_cost' => 0,
        'price_adjustments' => [
            [
                'id' => 'preset-uuid-1',
                'source' => 'preset',
                'source_id' => $preset->id,
                'type' => 'discount',
                'name' => $preset->name,
                'calculation_type' => 'percentage',
                'value' => 10,
                'applied_amount' => 100,
                'reason' => null,
                'show_in_receipt' => true,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes", [
            'source' => 'preset',
            'source_id' => $preset->id,
            'type' => 'discount',
            'name' => $preset->name,
            'calculation_type' => 'percentage',
            'value' => 10,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['adjustments']);
});

test('syncing an order with legacy duplicate presets purges the duplicates and heals totals', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 1000,
    ]);

    $preset = TenantPriceAdjustment::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Descuento Efectivo 10%',
        'type' => 'discount',
        'calculation_type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    // Order with duplicate presets (e.g. legacy dirty state)
    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'sin_facturar',
        'subtotal_amount' => 1000,
        'total_amount' => 800, // corrupted total reflecting 2x discount
        'discount_total' => 200,
        'shipping_cost' => 0,
        'price_adjustments' => [
            [
                'id' => 'preset-uuid-1',
                'source' => 'preset',
                'source_id' => $preset->id,
                'type' => 'discount',
                'name' => $preset->name,
                'calculation_type' => 'percentage',
                'value' => 10,
                'applied_amount' => 100,
                'reason' => null,
                'show_in_receipt' => true,
            ],
            [
                'id' => 'preset-uuid-2',
                'source' => 'preset',
                'source_id' => $preset->id,
                'type' => 'discount',
                'name' => $preset->name,
                'calculation_type' => 'percentage',
                'value' => 10,
                'applied_amount' => 100,
                'reason' => null,
                'show_in_receipt' => true,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    // Syncing with target preset 1
    $this->actingAs($this->adminUser)
        ->postJson("/tenant/pedidos/{$order->id}/ajustes/sincronizar", [
            'preset_ids' => [$preset->id],
        ])
        ->assertOk();

    $order->refresh();

    // Must be purged to exactly 1 preset, keeping original uuid-1
    expect($order->price_adjustments)->toHaveCount(1)
        ->and($order->price_adjustments[0]['id'])->toBe('preset-uuid-1')
        ->and((float) $order->discount_total)->toBe(100.0)
        ->and((float) $order->total_amount)->toBe(900.0);
});

test('can delete an ad-hoc adjustment and recalculate totals', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'price' => 1000,
    ]);

    $order = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'no_facturado',
        'total_amount' => 850,
        'discount_total' => 150,
        'shipping_cost' => 0,
        'price_adjustments' => [
            [
                'id' => 'ad-hoc-uuid-1',
                'source' => 'ad_hoc',
                'source_id' => null,
                'type' => 'discount',
                'name' => 'Descuento Especial',
                'calculation_type' => 'fixed',
                'value' => 150,
                'applied_amount' => 150,
                'reason' => 'Cliente fiel',
                'show_in_receipt' => true,
            ],
        ],
    ]);

    CustomerOrderItem::create([
        'customer_order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
    ]);

    $this->actingAs($this->adminUser)
        ->deleteJson("/tenant/pedidos/{$order->id}/ajustes/ad-hoc-uuid-1")
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $order->refresh();

    expect($order->price_adjustments)->toBeEmpty()
        ->and((float) $order->discount_total)->toBe(0.0)
        ->and((float) $order->total_amount)->toBe(1000.0);
});

test('cannot delete an adjustment if order is terminal or facturado', function () {
    $terminalOrder = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'entregado',
        'fiscal_status' => 'no_facturado',
        'total_amount' => 900,
        'price_adjustments' => [
            [
                'id' => 'ad-hoc-uuid-1',
                'source' => 'ad_hoc',
                'type' => 'discount',
                'name' => 'Desc',
                'calculation_type' => 'fixed',
                'value' => 100,
                'applied_amount' => 100,
            ],
        ],
    ]);

    $this->actingAs($this->adminUser)
        ->deleteJson("/tenant/pedidos/{$terminalOrder->id}/ajustes/ad-hoc-uuid-1")
        ->assertStatus(422);

    $facturadoOrder = CustomerOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'en_preparacion',
        'fiscal_status' => 'facturado',
        'total_amount' => 900,
        'price_adjustments' => [
            [
                'id' => 'ad-hoc-uuid-2',
                'source' => 'ad_hoc',
                'type' => 'discount',
                'name' => 'Desc',
                'calculation_type' => 'fixed',
                'value' => 100,
                'applied_amount' => 100,
            ],
        ],
    ]);

    $this->actingAs($this->adminUser)
        ->deleteJson("/tenant/pedidos/{$facturadoOrder->id}/ajustes/ad-hoc-uuid-2")
        ->assertStatus(422);
});
