<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TestSeeder;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->tenantA = Tenant::first();
    $this->userA = User::where('tenant_id', $this->tenantA->id)->first();

    $this->tenantB = Tenant::factory()->create(['name' => 'Comercio B']);
    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'is_superadmin' => false,
    ]);
});

test('tenant cannot see customers belonging to another tenant', function () {
    Customer::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Cliente Exclusivo Tenant A',
    ]);

    Customer::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Cliente Exclusivo Tenant B',
    ]);

    $response = $this->actingAs($this->userA)
        ->get(route('tenant.customers.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('customers.data', 1)
        ->where('customers.data.0.name', 'Cliente Exclusivo Tenant A')
    );
});

test('tenant cannot update or delete customers belonging to another tenant', function () {
    $customerB = Customer::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Cliente de B',
    ]);

    $response = $this->actingAs($this->userA)
        ->put(route('tenant.customers.update', $customerB), [
            'name' => 'Intento de Hackeo',
            'tax_condition' => 'consumidor_final',
        ]);

    $response->assertNotFound();

    $deleteResponse = $this->actingAs($this->userA)
        ->delete(route('tenant.customers.destroy', $customerB));

    $deleteResponse->assertNotFound();

    $this->assertDatabaseHas('customers', [
        'id' => $customerB->id,
        'name' => 'Cliente de B',
    ]);
});
