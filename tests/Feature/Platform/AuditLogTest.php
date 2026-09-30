<?php

use App\Models\CustomerOrder;
use App\Models\FiscalInvoice;
use App\Models\SystemAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('superadmin can access platform audit log and view records', function () {
    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $tenant = Tenant::factory()->create(['name' => 'Papelera Central']);

    SystemAuditLog::create([
        'tenant_id' => $tenant->id,
        'user_id' => $superadmin->id,
        'user_name' => $superadmin->name,
        'user_role' => 'superadmin',
        'action' => 'Actualización de datos comerciales',
        'auditable_type' => Tenant::class,
        'auditable_id' => $tenant->id,
        'entity_label' => "Comercio #{$tenant->id} - Papelera Central",
        'old_values' => ['name' => 'Papelera Vieja'],
        'new_values' => ['name' => 'Papelera Central'],
        'created_at' => now(),
    ]);

    $response = $this->actingAs($superadmin)->get(route('platform.audit.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/audit/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'Actualización de datos comerciales')
            ->where('logs.data.0.entity_label', "Comercio #{$tenant->id} - Papelera Central")
            ->has('tenants', 1)
        );
});

test('regular tenant user cannot access platform audit log', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('platform.audit.index'));

    $response->assertForbidden();
});

test('tenant update automatically triggers audit log record via observer', function () {
    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $this->actingAs($superadmin);

    $tenant = Tenant::factory()->create([
        'name' => 'Nombre Anterior',
        'cuit_tax_id' => '20123456789',
    ]);

    $tenant->update([
        'name' => 'Nombre Nuevo SA',
    ]);

    $log = SystemAuditLog::where('auditable_type', Tenant::class)
        ->where('auditable_id', $tenant->id)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->action)->toBe('Actualización de datos comerciales')
        ->and($log->old_values)->toHaveKey('name', 'Nombre Anterior')
        ->and($log->new_values)->toHaveKey('name', 'Nombre Nuevo SA');
});

test('audit log can be filtered by tenant and entity type', function () {
    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $tenantA = Tenant::factory()->create(['name' => 'Comercio A']);
    $tenantB = Tenant::factory()->create(['name' => 'Comercio B']);

    SystemAuditLog::create([
        'tenant_id' => $tenantA->id,
        'user_name' => 'Operador A',
        'action' => 'Actualización de datos comerciales',
        'auditable_type' => Tenant::class,
        'auditable_id' => $tenantA->id,
        'entity_label' => "Comercio #{$tenantA->id}",
        'created_at' => now(),
    ]);

    SystemAuditLog::create([
        'tenant_id' => $tenantB->id,
        'user_name' => 'Operador B',
        'action' => 'Emisión de factura electrónica',
        'auditable_type' => FiscalInvoice::class,
        'auditable_id' => 999,
        'entity_label' => "Factura 0001-00000001",
        'created_at' => now(),
    ]);

    // Filter by tenantA
    $responseA = $this->actingAs($superadmin)->get(route('platform.audit.index', [
        'tenant_id' => $tenantA->id,
    ]));

    $responseA->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/audit/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.tenant_id', $tenantA->id)
        );

    // Filter by entity_type = facturacion
    $responseB = $this->actingAs($superadmin)->get(route('platform.audit.index', [
        'entity_type' => 'facturacion',
    ]));

    $responseB->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/audit/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.auditable_type', FiscalInvoice::class)
        );
});
