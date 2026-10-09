<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::factory()->create([
        'is_active' => true,
        'name' => 'Papelera Test',
    ]);

    $allModules = Module::all();
    $this->tenant->modules()->sync($allModules->pluck('id'));

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $adminRole->syncPermissions(Permission::all());
    $this->user->assignRole($adminRole);
});

/*
|--------------------------------------------------------------------------
| Clientes: Restricción CUIT / DNI y Límite de Crédito
|--------------------------------------------------------------------------
*/

test('customer creation rejects tax_id with more than 11 digits', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/clientes', [
            'name' => 'Cliente Con Cuit Gigante',
            'tax_id' => '123456789012', // 12 dígitos
            'tax_condition' => 'consumidor_final',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_id']);
});

test('customer creation accepts valid CUIT and DNI formats', function () {
    // DNI de 8 dígitos
    $respDni = $this->actingAs($this->user)
        ->postJson('/tenant/clientes', [
            'name' => 'Cliente DNI',
            'tax_id' => '30000001',
            'tax_condition' => 'consumidor_final',
        ]);
    $respDni->assertRedirect();
    expect(Customer::where('tax_id', '30000001')->exists())->toBeTrue();

    // CUIT con guiones (13 chars, 11 dígitos)
    $respCuit = $this->actingAs($this->user)
        ->postJson('/tenant/clientes', [
            'name' => 'Cliente CUIT Con Guiones',
            'tax_id' => '20-12345678-9',
            'tax_condition' => 'responsable_inscripto',
        ]);
    $respCuit->assertRedirect();
    expect(Customer::where('tax_id', '20-12345678-9')->exists())->toBeTrue();
});

test('customer creation rejects credit limit over 100 million', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/clientes', [
            'name' => 'Cliente Millonario',
            'tax_condition' => 'consumidor_final',
            'credit_limit' => 100000001, // 100M + 1
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_limit']);
});

test('customer update enforces 11 digits and 100 million limits', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'tax_id' => '20300000019',
        'credit_limit' => 50000,
    ]);

    // Reject > 11 digits
    $this->actingAs($this->user)
        ->putJson("/tenant/clientes/{$customer->id}", [
            'name' => $customer->name,
            'tax_id' => '9999999999999',
            'tax_condition' => 'responsable_inscripto',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_id']);

    // Reject > 100M
    $this->actingAs($this->user)
        ->putJson("/tenant/clientes/{$customer->id}", [
            'name' => $customer->name,
            'tax_condition' => 'responsable_inscripto',
            'credit_limit' => 150000000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_limit']);
});

/*
|--------------------------------------------------------------------------
| Proveedores: Restricción CUIT (máx 11 dígitos)
|--------------------------------------------------------------------------
*/

test('supplier creation rejects tax_id with more than 11 digits', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/proveedores', [
            'name' => 'Proveedor Ilegal',
            'tax_id' => '999999999999', // 12 dígitos
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_id']);
});

test('supplier creation accepts valid CUIT', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/tenant/proveedores', [
            'name' => 'Proveedor Legítimo S.A.',
            'tax_id' => '30-71234567-9',
        ]);

    $response->assertSuccessful();
    expect(Supplier::where('tax_id', '30-71234567-9')->exists())->toBeTrue();
});

test('supplier update enforces CUIT limit', function () {
    $supplier = Supplier::factory()->forTenant($this->tenant)->create([
        'tax_id' => '30111111119',
    ]);

    $this->actingAs($this->user)
        ->putJson("/tenant/proveedores/{$supplier->id}", [
            'name' => $supplier->name,
            'tax_id' => '123456789012345',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_id']);
});

/*
|--------------------------------------------------------------------------
| Comercio: Restricción CUIT en Configuración del Tenant
|--------------------------------------------------------------------------
*/

test('company settings rejects CUIT with more than 11 digits', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/tenant/configuracion/comercio', [
            'name' => 'Mi Comercio Actualizado',
            'cuit_tax_id' => '123456789012', // 12 dígitos
            'tax_condition' => 'responsable_inscripto',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['cuit_tax_id']);
});

test('company settings accepts valid CUIT', function () {
    $response = $this->actingAs($this->user)
        ->putJson('/tenant/configuracion/comercio', [
            'name' => 'Mi Comercio Actualizado',
            'cuit_tax_id' => '30-71234567-8',
            'tax_condition' => 'responsable_inscripto',
        ]);

    $response->assertSuccessful();
    $this->tenant->refresh();
    expect($this->tenant->cuit_tax_id)->toBe('30-71234567-8');
});

/*
|--------------------------------------------------------------------------
| Cuentas Corrientes y Cobros: Límite Seguro de $100.000.000
|--------------------------------------------------------------------------
*/

test('recording customer payment rejects amounts over 100 million', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'current_balance' => 5000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/tenant/clientes/{$customer->id}/cuenta-corriente/cobro", [
            'amount' => 100000001,
            'payment_method' => 'efectivo',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('recording customer adjustment rejects amounts over 100 million', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'current_balance' => 5000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/tenant/clientes/{$customer->id}/cuenta-corriente/ajuste", [
            'type' => 'credit',
            'amount' => 100000001,
            'description' => 'Ajuste excesivo',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('collecting customer order rejects amounts over 100 million', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create();
    $order = CustomerOrder::factory()->forTenant($this->tenant)->create([
        'customer_id' => $customer->id,
        'total_amount' => 10000,
        'status' => 'en_preparacion',
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/tenant/pedidos/{$order->id}/cobrar", [
            'payment_method' => 'efectivo',
            'amount' => 100000001,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

/*
|--------------------------------------------------------------------------
| Pedidos: Prohibición Estricta de Signos Especiales en Saldo y Deuda
|--------------------------------------------------------------------------
*/

test('order creation rejects special characters and negative numbers in previous_debt_collected', function ($invalidValue) {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'current_balance' => 5000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'payment_type' => 'contado',
            'payment_method' => 'efectivo',
            'previous_debt_collected' => $invalidValue,
            'items' => [
                [
                    'product_name' => 'Item Test',
                    'unit_price' => 1000,
                    'quantity' => 1,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['previous_debt_collected']);
})->with([
    'double minus' => '--',
    'negative' => '-500',
    'plus sign' => '+500',
    'hash symbol' => '10#5',
    'slash symbol' => '10/5',
]);

test('order creation rejects special characters and negative numbers in credit_balance_applied', function ($invalidValue) {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'current_balance' => -5000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'payment_type' => 'cuenta_corriente',
            'credit_balance_applied' => $invalidValue,
            'items' => [
                [
                    'product_name' => 'Item Test',
                    'unit_price' => 1000,
                    'quantity' => 1,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['credit_balance_applied']);
})->with([
    'double minus' => '--',
    'negative' => '-500',
    'plus sign' => '+500',
    'hash symbol' => '10#5',
    'slash symbol' => '10/5',
]);

test('order creation and receipt visibility modal persist show_debt_collected_in_receipt correctly', function () {
    $customer = Customer::factory()->forTenant($this->tenant)->create([
        'current_balance' => 5000,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/pedidos', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'payment_type' => 'contado',
            'payment_method' => 'efectivo',
            'previous_debt_collected' => 2000,
            'show_debt_collected_in_receipt' => true,
            'items' => [
                [
                    'product_name' => 'Item Test',
                    'unit_price' => 1000,
                    'quantity' => 1,
                    'packaging_type' => 'unidad',
                ],
            ],
        ]);

    $response->assertStatus(201);
    $order = CustomerOrder::where('tenant_id', $this->tenant->id)->first();
    expect($order->show_debt_collected_in_receipt)->toBeTrue();

    // Actualizar visibilidad a false a través del endpoint de remito
    $patchResponse = $this->actingAs($this->user)
        ->patchJson("/tenant/pedidos/{$order->id}/visibilidad-remito", [
            'show_debt_collected_in_receipt' => false,
        ]);

    $patchResponse->assertOk();
    expect($order->fresh()->show_debt_collected_in_receipt)->toBeFalse();
});
