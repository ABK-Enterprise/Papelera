<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->module = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos y Ventas',
        'is_active' => true,
    ]);

    $this->permVer = Permission::create([
        'name' => 'pedidos.ver',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);
    $this->permCrear = Permission::create([
        'name' => 'pedidos.crear',
        'guard_name' => 'web',
        'module_id' => $this->module->id,
    ]);

    $this->tenant->modules()->attach($this->module->id);

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->forTenant($this->tenant)->create();
    $this->user->assignRole($this->adminRole);

    $this->warehouse = Warehouse::where('tenant_id', $this->tenant->id)->first() ?? Warehouse::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Depósito Principal',
        'code' => 'DEP-01',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Bolsas y Empaques',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Bolsas Kraft 20x30',
        'sku' => 'KRAFT-2030',
        'sale_type' => 'unit',
        'price' => 100.00,
        'cost' => 50.00,
        'stock' => 50,
        'units_per_package' => 1,
        'is_active' => true,
    ]);

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Mayorista SRL',
        'current_balance' => 0.00,
    ]);
});

it('creates order with mp_qr_checkout for contado without premature receipts, leaving debt active and status pendiente', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_type' => 'contado',
            'payment_method' => 'mercadopago',
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 10,
                    'unit_price' => 100.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertRedirect(route('tenant.orders.index'));
    $response->assertSessionHas('created_order');
    $response->assertSessionHas('target_qr_amount', 1000.00);

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toBe(1000.00)
        ->and((float) $order->paid_amount)->toBe(0.00)
        ->and((float) $order->debt_amount)->toBe(1000.00)
        ->and($order->payment_status)->toBe('pendiente');

    // Sin recibos preliminares en BD
    expect(Receipt::where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and(CustomerAccountMovement::where('tenant_id', $this->tenant->id)->where('type', 'payment')->count())->toBe(0);
});

it('creates order with mp_qr_checkout for parcial with target_qr_amount equal to paid_amount', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_type' => 'parcial',
            'payment_method' => 'mercadopago',
            'paid_amount' => 400.00,
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 10,
                    'unit_price' => 100.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertRedirect(route('tenant.orders.index'));
    $response->assertSessionHas('created_order');
    $response->assertSessionHas('target_qr_amount', 400.00);

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((float) $order->total_amount)->toBe(1000.00)
        ->and((float) $order->paid_amount)->toBe(0.00)
        ->and((float) $order->debt_amount)->toBe(1000.00)
        ->and($order->payment_status)->toBe('pendiente');

    expect(Receipt::where('tenant_id', $this->tenant->id)->count())->toBe(0);
});

it('returns target_qr_amount in JSON when requested via API', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_type' => 'contado',
            'payment_method' => 'mercadopago',
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 5,
                    'unit_price' => 100.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'order' => ['id', 'order_number', 'total_amount', 'debt_amount'],
        'target_qr_amount',
    ]);
    expect((float) $response->json('target_qr_amount'))->toBe(500.00);
});

it('validates that mp_qr_checkout requires payment_method mercadopago', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'payment_type' => 'contado',
            'payment_method' => 'efectivo',
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['payment_method']);
});

it('validates that mp_qr_checkout requires payment_type contado or parcial', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'payment_type' => 'cuenta_corriente',
            'payment_method' => 'mercadopago',
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['payment_type']);
});

it('validates that mp_qr_checkout with parcial requires paid_amount greater than zero', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'payment_type' => 'parcial',
            'payment_method' => 'mercadopago',
            'paid_amount' => 0,
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['paid_amount']);
});

it('allows generating QR for an order created via mp_qr_checkout', function () {
    $response = $this->actingAs($this->user)
        ->post(route('tenant.orders.store'), [
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_type' => 'contado',
            'payment_method' => 'mercadopago',
            'mp_qr_checkout' => true,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'packaging_type' => 'unidad',
                    'quantity' => 2,
                    'unit_price' => 100.00,
                    'apply_customer_discount' => false,
                ],
            ],
        ]);

    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();

    $mockMpService = Mockery::mock(\App\Services\MercadoPago\MercadoPagoService::class);
    $mockMpService->shouldReceive('createOrder')
        ->once()
        ->with(Mockery::on(fn ($o) => $o->id === $order->id), 200.0, null)
        ->andReturn([
            'success' => true,
            'order_id' => 'mp-order-12345',
            'qr_data' => '00020101021243650016COM.MERCADOLIBRE...',
            'amount' => 200.0,
            'status' => 'opened',
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
        ]);
    $this->app->instance(\App\Services\MercadoPago\MercadoPagoService::class, $mockMpService);

    $qrResponse = $this->actingAs($this->user)
        ->postJson(route('tenant.orders.generate-qr', $order), [
            'amount' => 200.0,
        ]);

    $qrResponse->assertOk();
    $qrResponse->assertJson([
        'success' => true,
        'order_id' => 'mp-order-12345',
        'qr_data' => '00020101021243650016COM.MERCADOLIBRE...',
        'amount' => 200.0,
    ]);

    expect($order->fresh()->mp_order_id)->toBe('mp-order-12345');
});
