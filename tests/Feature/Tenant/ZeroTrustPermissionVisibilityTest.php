<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['is_active' => true]);

    $this->productosModule = Module::create([
        'code' => 'productos',
        'name' => 'Catálogo de Productos',
        'is_active' => true,
    ]);

    $this->stockModule = Module::create([
        'code' => 'stock',
        'name' => 'Control de Stock',
        'is_active' => true,
    ]);

    $this->permProdVer = Permission::create([
        'name' => 'productos.ver',
        'guard_name' => 'web',
        'module_id' => $this->productosModule->id,
    ]);

    $this->permStockIngresoVer = Permission::create([
        'name' => 'stock_ingreso.ver',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->permStockMinimoVer = Permission::create([
        'name' => 'stock_minimo.ver',
        'guard_name' => 'web',
        'module_id' => $this->stockModule->id,
    ]);

    $this->tenant->modules()->sync([$this->productosModule->id, $this->stockModule->id]);

    // Admin
    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    // Employee
    $this->employeeRole = Role::create([
        'name' => 'empleado_base',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->employeeUser = User::factory()->forTenant($this->tenant)->create();
    $this->employeeUser->assignRole($this->employeeRole);

    $this->category = Category::factory()->forTenant($this->tenant)->create();
    $this->product = Product::factory()->forTenant($this->tenant)->forCategory($this->category)->create([
        'stock' => 2,
        'min_stock' => 5,
    ]);
});

test('employee without productos.ver permission is redirected to access-denied', function () {
    $this->actingAs($this->employeeUser)
        ->get(route('tenant.products.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
});

test('employee with productos.ver permission can access products index', function () {
    $this->employeeRole->givePermissionTo($this->permProdVer);

    $this->actingAs($this->employeeUser)
        ->get(route('tenant.products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenant/products/Index')
            ->has('products')
        );
});

test('employee without stock_minimo.ver receives null lowStockProducts on dashboard', function () {
    $this->actingAs($this->employeeUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('lowStockProducts', null)
        );
});

test('employee with stock_minimo.ver receives low stock products on dashboard', function () {
    $this->employeeRole->givePermissionTo($this->permStockMinimoVer);

    $this->actingAs($this->employeeUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 1)
        );
});

test('employee without admin role cannot access tenant users or roles management and is redirected to access-denied', function () {
    $this->actingAs($this->employeeUser)
        ->get(route('tenant.users.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $this->actingAs($this->employeeUser)
        ->get(route('tenant.roles.index'))
        ->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
});

test('tenant admin can access dashboard low stock alerts and management routes', function () {
    $this->actingAs($this->adminUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 1)
        );

    $this->actingAs($this->adminUser)
        ->get(route('tenant.users.index'))
        ->assertOk();
});
