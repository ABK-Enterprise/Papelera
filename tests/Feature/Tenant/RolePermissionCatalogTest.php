<?php

use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('tenant admin can see cuentas_corrientes.ver in roles create and edit panels', function () {
    $this->seed(BaseSeeder::class);

    $tenant = Tenant::factory()->create(['is_active' => true]);
    setPermissionsTeamId($tenant->id);

    $clienteModule = Module::where('code', 'clientes')->first();
    $tenant->modules()->sync([$clienteModule->id]);

    // Admin role for tenant with all module permissions
    $adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);
    $adminRole->givePermissionTo($clienteModule->permissions);

    $adminUser = User::factory()->forTenant($tenant)->create();
    $adminUser->assignRole($adminRole);

    // Verify Create role view has cuentas_corrientes.ver
    $this->actingAs($adminUser)
        ->get(route('tenant.roles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Create')
            ->has('modules', 1)
            ->where('modules.0.code', 'clientes')
            ->where('modules.0.permissions', fn ($perms) => collect($perms)->pluck('name')->contains('cuentas_corrientes.ver'))
        );

    // Verify Edit role view also has cuentas_corrientes.ver
    $customRole = Role::create([
        'name' => 'vendedor',
        'guard_name' => 'web',
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($adminUser)
        ->get(route('tenant.roles.edit', $customRole))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/roles/Edit')
            ->has('modules', 1)
            ->where('modules.0.code', 'clientes')
            ->where('modules.0.permissions', fn ($perms) => collect($perms)->pluck('name')->contains('cuentas_corrientes.ver'))
        );
});
