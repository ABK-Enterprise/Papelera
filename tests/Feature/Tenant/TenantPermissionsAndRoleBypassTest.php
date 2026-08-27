<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Papelera Seguridad Test',
        'cuit_tax_id' => '30-11223344-5',
        'is_active' => true,
    ]);
    $this->tenant->modules()->syncWithoutDetaching(Module::pluck('id'));

    setPermissionsTeamId($this->tenant->id);

    // Rol Administrador del Tenant
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    // Rol Vendedor (solo ver productos)
    $this->viewerRole = Role::create([
        'name' => 'Vendedor Lector',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->viewerRole->syncPermissions(['productos.ver']);

    // Rol Editor de Productos
    $this->editorRole = Role::create([
        'name' => 'Editor Catalogo',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->editorRole->syncPermissions(['productos.ver', 'productos.editar', 'productos.eliminar']);

    $this->adminUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'admin@seguridad.test',
        'is_superadmin' => false,
    ]);
    $this->adminUser->assignRole($this->adminRole);

    $this->viewerUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'viewer@seguridad.test',
        'is_superadmin' => false,
    ]);
    $this->viewerUser->assignRole($this->viewerRole);

    $this->editorUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'editor@seguridad.test',
        'is_superadmin' => false,
    ]);
    $this->editorUser->assignRole($this->editorRole);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cajas de Cartón',
        'slug' => 'cajas-carton',
        'is_active' => true,
    ]);
});

test('1. Usuario con solo permiso productos.ver es bloqueado al intentar actualizar o eliminar categorias', function () {
    // Intentar PUT /tenant/categories/{id} con rol lector
    $updateResponse = $this->actingAs($this->viewerUser)
        ->putJson(route('tenant.categories.update', $this->category->id), [
            'name' => 'Cajas Hackeadas',
            'slug' => 'cajas-hackeadas',
        ]);

    $updateResponse->assertStatus(403);

    // Intentar DELETE /tenant/categories/{id} con rol lector
    $deleteResponse = $this->actingAs($this->viewerUser)
        ->deleteJson(route('tenant.categories.destroy', $this->category->id));

    $deleteResponse->assertStatus(403);
});

test('2. Usuario con productos.editar y productos.eliminar puede gestionar categorias', function () {
    $updateResponse = $this->actingAs($this->editorUser)
        ->putJson(route('tenant.categories.update', $this->category->id), [
            'name' => 'Cajas Corrugadas Especiales',
            'slug' => 'cajas-corrugadas',
        ]);

    $updateResponse->assertStatus(200);
    expect($this->category->refresh()->name)->toBe('Cajas Corrugadas Especiales');

    $deleteResponse = $this->actingAs($this->editorUser)
        ->deleteJson(route('tenant.categories.destroy', $this->category->id));

    $deleteResponse->assertStatus(200);
    expect(Category::find($this->category->id))->toBeNull();
});

test('3. No se puede eliminar una categoria que tiene productos asociados', function () {
    Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Caja de Archivo Definitivo',
        'price' => 1500.00,
        'is_active' => true,
    ]);

    $deleteResponse = $this->actingAs($this->editorUser)
        ->deleteJson(route('tenant.categories.destroy', $this->category->id));

    $deleteResponse->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'No es posible eliminar la categoría porque tiene productos asociados.',
        ]);

    expect(Category::find($this->category->id))->not->toBeNull();
});

test('4. StoreUserRequest y UpdateUserRequest rechazan asignacion directa del rol admin', function () {
    // Intentar crear un usuario asignándole el rol 'admin' vía API
    $createResponse = $this->actingAs($this->adminUser)
        ->postJson(route('tenant.users.store'), [
            'name' => 'Empleado Impostor',
            'email' => 'impostor@seguridad.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

    $createResponse->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    // Crear un empleado legítimo con rol lector
    $legitUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'legit@seguridad.test',
    ]);
    $legitUser->assignRole($this->viewerRole);

    // Intentar escalar a 'admin' vía PUT
    $updateResponse = $this->actingAs($this->adminUser)
        ->putJson(route('tenant.users.update', $legitUser->id), [
            'name' => 'Empleado Escalado',
            'email' => 'legit@seguridad.test',
            'role' => 'admin',
        ]);

    $updateResponse->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});
