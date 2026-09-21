<?php

use App\Events\Tenant\TenantModulesUpdated;
use App\Events\Tenant\TenantPermissionsUpdated;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantModuleSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->moduleStock = Module::create([
        'code' => 'stock',
        'name' => 'Control de Stock',
        'is_active' => true,
    ]);

    $this->moduleArca = Module::create([
        'code' => 'facturacion_arca',
        'name' => 'Facturación ARCA',
        'is_active' => true,
    ]);

    $this->modulePedidos = Module::create([
        'code' => 'pedidos',
        'name' => 'Pedidos',
        'is_active' => true,
    ]);
});

test('synchronizing tenant modules dispatches TenantModulesUpdated with attached_modules', function () {
    Event::fake([TenantModulesUpdated::class]);

    $synchronizer = app(TenantModuleSynchronizer::class);

    // Initial sync attaching stock and pedidos
    $synchronizer->sync($this->tenant, [$this->moduleStock->id, $this->modulePedidos->id]);

    Event::assertDispatched(TenantModulesUpdated::class, function (TenantModulesUpdated $event) {
        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-tenant.'.$this->tenant->id)
            ->and($event->broadcastAs())->toBe('TenantModulesUpdated')
            ->and($event->tenantId)->toBe($this->tenant->id)
            ->and($event->attachedModules)->toContain('stock', 'pedidos')
            ->and($event->detachedModules)->toBeEmpty()
            ->and($event->activeModules)->toContain('stock', 'pedidos');

        $payload = $event->broadcastWith();
        expect($payload)->toHaveKeys(['tenant_id', 'active_modules', 'detached_modules', 'attached_modules'])
            ->and($payload['attached_modules'])->toContain('stock', 'pedidos');

        return true;
    });
});

test('synchronizing tenant modules dispatches TenantModulesUpdated with detached_modules when module is removed', function () {
    // Start with stock and pedidos attached
    $this->tenant->modules()->attach([$this->moduleStock->id, $this->modulePedidos->id]);

    Event::fake([TenantModulesUpdated::class]);

    $synchronizer = app(TenantModuleSynchronizer::class);

    // Sync removing pedidos and attaching arca
    $synchronizer->sync($this->tenant, [$this->moduleStock->id, $this->moduleArca->id]);

    Event::assertDispatched(TenantModulesUpdated::class, function (TenantModulesUpdated $event) {
        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-tenant.'.$this->tenant->id)
            ->and($event->detachedModules)->toBe(['pedidos'])
            ->and($event->attachedModules)->toBe(['facturacion_arca'])
            ->and($event->activeModules)->toContain('stock', 'facturacion_arca')
            ->and($event->activeModules)->not->toContain('pedidos');

        $payload = $event->broadcastWith();
        expect($payload['detached_modules'])->toBe(['pedidos'])
            ->and($payload['attached_modules'])->toBe(['facturacion_arca']);

        return true;
    });
});

test('updating a role dispatches TenantPermissionsUpdated event', function () {
    Event::fake([TenantPermissionsUpdated::class]);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($this->adminRole);

    $role = Role::create([
        'name' => 'cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($user)->put(route('tenant.roles.update', $role), [
        'name' => 'cajero_senior',
        'permissions' => [],
    ]);

    $response->assertRedirect(route('tenant.roles.index'));

    Event::assertDispatched(TenantPermissionsUpdated::class, function (TenantPermissionsUpdated $event) {
        $channels = $event->broadcastOn();
        expect($channels)->toHaveCount(1)
            ->and($channels[0]->name)->toBe('private-tenant.'.$this->tenant->id)
            ->and($event->broadcastAs())->toBe('TenantPermissionsUpdated')
            ->and($event->tenantId)->toBe($this->tenant->id)
            ->and($event->broadcastWith())->toBe(['tenant_id' => $this->tenant->id]);

        return true;
    });
});

test('deleting a role dispatches TenantPermissionsUpdated event', function () {
    Event::fake([TenantPermissionsUpdated::class]);

    $user = User::factory()->forTenant($this->tenant)->create();
    $user->assignRole($this->adminRole);

    $role = Role::create([
        'name' => 'cajero',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($user)->delete(route('tenant.roles.destroy', $role));

    $response->assertRedirect(route('tenant.roles.index'));

    Event::assertDispatched(TenantPermissionsUpdated::class, function (TenantPermissionsUpdated $event) {
        return $event->tenantId === $this->tenant->id;
    });
});
