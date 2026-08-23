<?php

use App\Models\Module;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->proveedoresModule = Module::firstOrCreate(
        ['code' => 'proveedores'],
        ['name' => 'Proveedores', 'is_active' => true]
    );

    $this->tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'is_active' => true]);
    $this->tenant1->modules()->syncWithoutDetaching([$this->proveedoresModule->id]);
    $this->user1 = User::factory()->forTenant($this->tenant1)->create();

    $this->tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'is_active' => true]);
    $this->tenant2->modules()->syncWithoutDetaching([$this->proveedoresModule->id]);
    $this->user2 = User::factory()->forTenant($this->tenant2)->create();
});

test('tenant 1 cannot access, edit, update or delete tenant 2 supplier', function () {
    $supplierT2 = Supplier::factory()->forTenant($this->tenant2)->create([
        'name' => 'Proveedor Confidencial T2',
        'contact_number' => '1199999999',
    ]);

    // Update attempt
    $this->actingAs($this->user1)
        ->putJson("/tenant/suppliers/{$supplierT2->id}", [
            'name' => 'Intento Hackeo',
            'contact_number' => '00000000',
        ])
        ->assertNotFound();

    // Delete attempt
    $this->actingAs($this->user1)
        ->deleteJson("/tenant/suppliers/{$supplierT2->id}")
        ->assertNotFound();

    expect(Supplier::withoutGlobalScopes()->find($supplierT2->id)?->name)->toBe('Proveedor Confidencial T2');
});

test('supplier listings and stats are strictly isolated between tenants', function () {
    Supplier::factory()->forTenant($this->tenant1)->count(2)->create(['is_active' => true]);
    Supplier::factory()->forTenant($this->tenant2)->count(4)->create(['is_active' => true]);

    $resT1 = $this->actingAs($this->user1)
        ->getJson('/tenant/suppliers')
        ->assertSuccessful();

    expect($resT1->json()['suppliers'])->toHaveCount(2)
        ->and($resT1->json()['stats']['total'])->toBe(2)
        ->and($resT1->json()['stats']['active'])->toBe(2);

    $resT2 = $this->actingAs($this->user2)
        ->getJson('/tenant/suppliers')
        ->assertSuccessful();

    expect($resT2->json()['suppliers'])->toHaveCount(4)
        ->and($resT2->json()['stats']['total'])->toBe(4)
        ->and($resT2->json()['stats']['active'])->toBe(4);
});
