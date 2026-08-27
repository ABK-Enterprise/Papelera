<?php

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
use App\Models\Module;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

beforeEach(function () {
    Route::get('/test-superadmin-route', function () {
        return response()->json(['message' => 'welcome superadmin']);
    })->middleware(['web', 'auth', 'superadmin']);

    Route::get('/test-module-stock', function () {
        return response()->json(['message' => 'welcome stock module']);
    })->middleware(['web', 'auth', 'module:inventario_stock']);
});

test('superadmin can access superadmin protected route', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/test-superadmin-route')
        ->assertSuccessful()
        ->assertJson(['message' => 'welcome superadmin']);
});

test('tenant user is forbidden from superadmin protected route with 403', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get('/test-superadmin-route')
        ->assertForbidden();
});

test('guest is redirected or forbidden from superadmin protected route', function () {
    $this->get('/test-superadmin-route')
        ->assertRedirect('/login');
});

test('tenant context middleware sets spatie permissions team id', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)->get('/');

    expect(getPermissionsTeamId())->toBe($tenant->id);
});

test('tenant user can access route when tenant has the module assigned', function () {
    $tenant = Tenant::factory()->create();
    $module = Module::factory()->create(['code' => 'inventario_stock', 'is_active' => true]);
    $tenant->modules()->attach($module->id);

    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get('/test-module-stock')
        ->assertSuccessful()
        ->assertJson(['message' => 'welcome stock module']);
});

test('tenant user is redirected to access-denied from module route when module is not assigned', function () {
    $tenant = Tenant::factory()->create();
    // Do not attach module
    $user = User::factory()->forTenant($tenant)->create();

    $this->actingAs($user)
        ->get('/test-module-stock')
        ->assertRedirect(route('auth.access-denied', ['reason' => 'module_not_assigned']));
});
