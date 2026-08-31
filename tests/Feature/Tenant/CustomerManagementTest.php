<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::first();
    $this->adminUser = User::where('tenant_id', $this->tenant->id)->first();
});

test('tenant admin with clientes module can view customers index', function () {
    Customer::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.customers.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/customers/Index')
        ->has('customers', 3)
    );
});

test('customer can be registered with valid data', function () {
    $data = [
        'name' => 'Librería El Ateneo',
        'tax_id' => '30-11223344-9',
        'tax_condition' => 'responsable_inscripto',
        'email' => 'compras@elateneo.com',
        'phone' => '+54 11 4321-9988',
        'address' => 'Av. Santa Fe 1860, CABA',
        'credit_limit' => 50000.00,
        'is_defaulter' => false,
        'is_active' => true,
        'notes' => 'Cliente mayorista preferencial',
    ];

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.customers.store'), $data);

    $response->assertRedirect(route('tenant.customers.index'));

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería El Ateneo',
        'tax_id' => '30-11223344-9',
        'tax_condition' => 'responsable_inscripto',
        'is_defaulter' => false,
    ]);
});

test('customer name and tax_id must be unique within same tenant', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Papelera Rivadavia',
        'tax_id' => '20-33445566-7',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.customers.store'), [
            'name' => 'Papelera Rivadavia',
            'tax_id' => '20-33445566-7',
            'tax_condition' => 'consumidor_final',
        ]);

    $response->assertSessionHasErrors(['name', 'tax_id']);
});

test('customer can be updated', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Antiguo Nombre',
        'is_defaulter' => false,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->put(route('tenant.customers.update', $customer), [
            'name' => 'Nuevo Nombre Actualizado',
            'tax_id' => '20-99887766-5',
            'tax_condition' => 'monotributo',
            'email' => 'nuevo@cliente.com',
            'phone' => '11445566',
            'address' => 'Nueva Dirección 123',
            'credit_limit' => 15000.00,
            'is_defaulter' => true,
            'is_active' => true,
            'notes' => 'Registrado con deuda pendiente',
        ]);

    $response->assertRedirect(route('tenant.customers.index'));

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => 'Nuevo Nombre Actualizado',
        'is_defaulter' => true,
        'tax_condition' => 'monotributo',
    ]);
});

test('customer can be deleted', function () {
    $customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->delete(route('tenant.customers.destroy', $customer));

    $response->assertRedirect(route('tenant.customers.index'));
    $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
});

test('customer filtering by status, tax condition and defaulter works', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Moroso',
        'is_defaulter' => true,
        'tax_condition' => 'responsable_inscripto',
        'is_active' => true,
    ]);

    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cliente Al Dia',
        'is_defaulter' => false,
        'tax_condition' => 'monotributo',
        'is_active' => false,
    ]);

    // Filtrar por morosos
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.customers.index', ['is_defaulter' => 'yes']));
    $response->assertInertia(fn ($page) => $page->has('customers', 1));

    // Filtrar por condición fiscal
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.customers.index', ['tax_condition' => 'monotributo']));
    $response->assertInertia(fn ($page) => $page->has('customers', 1));

    // Filtrar por inactivos
    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.customers.index', ['status' => 'inactive']));
    $response->assertInertia(fn ($page) => $page->has('customers', 1));
});

test('customer filtering by search query works', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Librería El Ateneo Central',
    ]);

    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Gráfica Central SRL',
    ]);

    $response = $this->actingAs($this->adminUser)
        ->get(route('tenant.customers.index', ['search' => 'Ateneo']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('tenant/customers/Index')
        ->has('customers', 1)
        ->where('customers.0.name', 'Librería El Ateneo Central')
    );
});

