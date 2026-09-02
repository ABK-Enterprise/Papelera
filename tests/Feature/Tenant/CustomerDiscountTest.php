<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->defaultWarehouse = $this->tenant->warehouses()->where('is_default', true)->first();

    // Customer with 10% habitual discount
    $this->customer = Customer::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Central',
        'tax_condition' => 'responsable_inscripto',
        'credit_limit' => 500000,
        'discount_percentage' => 10.00,
        'is_active' => true,
    ]);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería',
        'slug' => 'libreria',
    ]);

    // Unit product ($1000 each)
    $this->unitProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Cuaderno Rivadavia 50 Hojas',
        'sku' => 'CUA-RIV-50',
        'price' => 1000.00,
        'stock' => 100,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    // Bulk product ($500 per unit, 10 units per package = $5000 regular, 10% bulk discount = $4500 per package)
    $this->bulkProduct = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Resma Ledesma A4',
        'sku' => 'RES-LED-A4',
        'price' => 500.00,
        'stock' => 500,
        'min_stock' => 50,
        'units_per_package' => 10,
        'package_quantity' => 10,
        'bulk_discount_type' => 'custom',
        'custom_bulk_discount_percentage' => 10.00,
        'is_active' => true,
    ]);


    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->unitProduct->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'stock' => 100,
    ]);

    ProductWarehouse::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->bulkProduct->id,
        'warehouse_id' => $this->defaultWarehouse->id,
        'stock' => 500,
    ]);
});

test('customer can be created and updated with discount percentage', function () {
    $response = $this->actingAs($this->adminUser)->post(route('tenant.customers.store'), [
        'name' => 'Papelera Rivadavia',
        'tax_condition' => 'consumidor_final',
        'discount_percentage' => 15.50,
        'credit_limit' => 100000,
    ]);

    $response->assertRedirect(route('tenant.customers.index'));
    $this->assertDatabaseHas('customers', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Papelera Rivadavia',
        'discount_percentage' => 15.50,
    ]);
});

test('order calculation applies customer discount to units and excludes bulk package items by default', function () {
    // 5 units of Cuaderno ($1000 * 5 = $5000 -> 10% customer discount = $4500)
    // 2 packages of Resma (2 packages * 10 units = 20 units * $450 = $9000 -> customer discount NOT applied = $9000)
    // Total order = $4500 + $9000 = $13500 (Discount total: $500)
    $response = $this->actingAs($this->adminUser)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'apply_customer_discount' => true,
        'items' => [
            [
                'product_id' => $this->unitProduct->id,
                'packaging_type' => 'units',
                'quantity' => 5,
                'apply_customer_discount' => true,
            ],
            [
                'product_id' => $this->bulkProduct->id,
                'packaging_type' => 'package',
                'quantity' => 2, // 2 packages
                'apply_customer_discount' => false,
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('customer_id', $this->customer->id)->latest()->first();

    expect((float) $order->total_amount)->toBe(13500.00)
        ->and((float) $order->customer_discount_amount)->toBe(500.00)
        ->and((float) $order->customer_discount_percentage)->toBe(10.00);

    $unitItem = $order->items()->where('product_id', $this->unitProduct->id)->first();
    expect((float) $unitItem->subtotal)->toBe(4500.00)
        ->and($unitItem->apply_customer_discount)->toBeTrue()
        ->and((float) $unitItem->discount_amount)->toBe(500.00);

    $bulkItem = $order->items()->where('product_id', $this->bulkProduct->id)->first();
    expect((float) $bulkItem->subtotal)->toBe(9000.00)
        ->and($bulkItem->apply_customer_discount)->toBeFalse()
        ->and((float) $bulkItem->discount_amount)->toBe(0.00);
});

test('order calculation respects disabling customer discount completely', function () {
    // When customer discount is disabled at order level, 5 units of Cuaderno are charged at full price $5000
    $response = $this->actingAs($this->adminUser)->post(route('tenant.orders.store'), [
        'customer_id' => $this->customer->id,
        'apply_customer_discount' => false,
        'items' => [
            [
                'product_id' => $this->unitProduct->id,
                'packaging_type' => 'units',
                'quantity' => 5,
                'apply_customer_discount' => false,
            ],
        ],
    ]);

    $response->assertRedirect(route('tenant.orders.index'));

    $order = CustomerOrder::where('customer_id', $this->customer->id)->latest()->first();

    expect((float) $order->total_amount)->toBe(5000.00)
        ->and((float) $order->customer_discount_amount)->toBe(0.00);
});
