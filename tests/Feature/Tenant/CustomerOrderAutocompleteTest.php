<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TestSeeder;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
});

test('customer order index provides available active customers', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.orders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/orders/Index')
        ->has('availableCustomers', 3)
    );
});

test('customer order can be created with registered customer_id', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería Central',
        'phone' => '+54 11 4455-6677',
        'address' => 'Av. Rivadavia 1234',
        'is_defaulter' => false,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'stock' => 50,
        'price' => 1000.00,
        'is_active' => true,
    ]);

    $orderData = [
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'delivery_address' => $customer->address,
        'notes' => 'Pedido con cliente registrado',
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'paquete',
                'quantity' => 5,
            ],
        ],
    ];

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.orders.store'), $orderData);

    $response->assertRedirect(route('tenant.orders.index'));

    $this->assertDatabaseHas('customer_orders', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'customer_name' => 'Librería Central',
        'total_amount' => 5000.00,
    ]);
});

test('customer order can be created without customer_id for unregistered customers', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'stock' => 20,
        'price' => 500.00,
        'is_active' => true,
    ]);

    $orderData = [
        'customer_id' => null,
        'customer_name' => 'Cliente Mostrador Rápido',
        'customer_phone' => '11998877',
        'delivery_address' => null,
        'items' => [
            [
                'product_id' => $product->id,
                'packaging_type' => 'paquete',
                'quantity' => 2,
            ],
        ],
    ];

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.orders.store'), $orderData);

    $response->assertRedirect(route('tenant.orders.index'));

    $this->assertDatabaseHas('customer_orders', [
        'tenant_id' => $this->tenant->id,
        'customer_id' => null,
        'customer_name' => 'Cliente Mostrador Rápido',
        'total_amount' => 1000.00,
    ]);
});
