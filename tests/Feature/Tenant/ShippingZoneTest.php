<?php

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    $this->category = \App\Models\Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cajas',
        'slug' => 'cajas',
    ]);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Caja de Cartón 30x30',
        'sku' => 'CAJ-30-30',
        'price' => 1000.00,
        'cost_price' => 600.00,
        'stock' => 100,
        'units_per_package' => 1,
        'sale_type' => 'unit',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Test',
        'tax_id' => '20123456789',
        'tax_condition' => 'responsable_inscripto',
        'discount_percentage' => 0,
        'current_balance' => 0,
    ]);
});

test('tenant admin can list shipping zones', function () {
    ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Gran Centro',
        'distance_km' => 15.00,
        'surcharge_percentage' => 5.00,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->adminUser)->get(route('tenant.shipping-zones.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/zones/Index')
        ->has('zones', 1)
    );
});

test('shipping zones are isolated between tenants', function () {
    $otherTenant = Tenant::create([
        'name' => 'Otro Comercio',
        'slug' => 'otro-comercio',
    ]);

    $zoneTenant1 = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zona Papelera',
        'distance_km' => 10.00,
        'surcharge_percentage' => 5.00,
    ]);

    $zoneTenant2 = ShippingZone::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Zona Otro Tenant',
        'distance_km' => 25.00,
        'surcharge_percentage' => 8.00,
    ]);

    $response = $this->actingAs($this->adminUser)->getJson(route('tenant.shipping-zones.index'));

    $response->assertOk();
    $response->assertJsonCount(1, 'zones');
    $response->assertJsonPath('zones.0.name', 'Zona Papelera');

    // Trying to update zone of another tenant returns 404
    $updateResponse = $this->actingAs($this->adminUser)->putJson(
        route('tenant.shipping-zones.update', $zoneTenant2),
        ['name' => 'Intento Hack', 'distance_km' => 10, 'surcharge_percentage' => 5]
    );
    $updateResponse->assertNotFound();
});

test('tenant admin can create shipping zone with valid data', function () {
    $response = $this->actingAs($this->adminUser)->post(route('tenant.shipping-zones.store'), [
        'name' => 'Zona Norte - 25km',
        'distance_km' => 25.50,
        'surcharge_percentage' => 7.50,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shipping_zones', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Zona Norte - 25km',
        'distance_km' => 25.50,
        'surcharge_percentage' => 7.50,
        'is_active' => true,
    ]);
});

test('validation rejects invalid shipping zone parameters', function () {
    $response = $this->actingAs($this->adminUser)->postJson(route('tenant.shipping-zones.store'), [
        'name' => '',
        'distance_km' => -5,
        'surcharge_percentage' => 150, // max 100
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name', 'distance_km', 'surcharge_percentage']);
});

test('tenant admin can update an existing shipping zone', function () {
    $zone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zona Original',
        'distance_km' => 10.00,
        'surcharge_percentage' => 4.00,
    ]);

    $response = $this->actingAs($this->adminUser)->put(route('tenant.shipping-zones.update', $zone), [
        'name' => 'Zona Editada',
        'distance_km' => 12.00,
        'surcharge_percentage' => 6.00,
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($zone->fresh()->name)->toBe('Zona Editada')
        ->and((float) $zone->fresh()->distance_km)->toBe(12.00)
        ->and((float) $zone->fresh()->surcharge_percentage)->toBe(6.00)
        ->and($zone->fresh()->is_active)->toBeFalse();
});

test('tenant admin can delete a shipping zone', function () {
    $zone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zona Para Borrar',
        'distance_km' => 5.00,
        'surcharge_percentage' => 2.00,
    ]);

    $response = $this->actingAs($this->adminUser)->delete(route('tenant.shipping-zones.destroy', $zone));

    $response->assertRedirect();
    $this->assertDatabaseMissing('shipping_zones', ['id' => $zone->id]);
});

test('order creation with shipping zone correctly calculates shipping cost and total', function () {
    $zone = ShippingZone::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Zona Sur',
        'distance_km' => 20.00,
        'surcharge_percentage' => 10.00, // 10%
    ]);

    // Product price is 1000. 3 units = 3000. 10% shipping = 300. Total = 3300.
    $response = $this->actingAs($this->adminUser)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'shipping_zone_id' => $zone->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 3,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $order = CustomerOrder::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and((int) $order->shipping_zone_id)->toBe($zone->id)
        ->and($order->shipping_zone_name)->toBe('Zona Sur')
        ->and((float) $order->subtotal_amount)->toBe(3000.00)
        ->and((float) $order->shipping_cost)->toBe(300.00)
        ->and((float) $order->total_amount)->toBe(3300.00);
});

test('order creation without shipping zone defaults shipping cost to zero', function () {
    // Product price is 1000. 2 units = 2000. No shipping. Total = 2000.
    $response = $this->actingAs($this->adminUser)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'shipping_zone_id' => null,
        'warehouse_id' => $this->defaultWarehouse->id,
        'items' => [
            [
                'product_id' => $this->product->id,
                'packaging_type' => 'unidad',
                'quantity' => 2,
            ],
        ],
    ]);

    $response->assertRedirect();

    $order = CustomerOrder::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->shipping_zone_id)->toBeNull()
        ->and($order->shipping_zone_name)->toBeNull()
        ->and((float) $order->subtotal_amount)->toBe(2000.00)
        ->and((float) $order->shipping_cost)->toBe(0.00)
        ->and((float) $order->total_amount)->toBe(2000.00);
});
