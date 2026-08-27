<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
beforeEach(function () {
    $this->seed(BaseSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Papelera Hardening Test',
        'cuit_tax_id' => '30-44556677-8',
        'is_active' => true,
    ]);
    $this->tenant->modules()->syncWithoutDetaching(Module::pluck('id'));

    setPermissionsTeamId($this->tenant->id);
    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'admin@hardening.test',
        'is_superadmin' => false,
    ]);
    $this->user->assignRole($this->adminRole);

    $this->category = Category::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cintas Adhesivas',
        'slug' => 'cintas-adhesivas',
        'is_active' => true,
    ]);
});

test('1. ProductController update actualiza min_stock pero no sobrescribe el stock fisico', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Cinta de Embalar 48x100',
        'price' => 1200.00,
        'stock' => 50,
        'min_stock' => 10,
        'is_active' => true,
    ]);

    // Intentar modificar el stock directamente mediante el payload de edición
    $response = $this->actingAs($this->user)
        ->putJson(route('tenant.products.update', $product->id), [
            'name' => 'Cinta de Embalar 48x100 Transparente',
            'category_id' => $this->category->id,
            'price' => 1350.00,
            'stock' => 9999, // payload malicioso o no autorizado
            'min_stock' => 25,
            'is_active' => true,
        ]);

    $response->assertStatus(200);

    $product->refresh();
    // El nombre y min_stock deben haberse actualizado
    expect($product->name)->toBe('Cinta de Embalar 48x100 Transparente')
        ->and((float) $product->min_stock)->toBe(25.0);

    // El stock físico debe permanecer inalterado (50 unidades)
    expect((float) $product->stock)->toBe(50.0);
});

test('2. Ajuste dinamico de min_stock refleja reactivamente la alerta en Dashboard', function () {
    $product = Product::create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Bobina Film Alveolar',
        'price' => 8500.00,
        'stock' => 40,
        'min_stock' => 20,
        'is_active' => true,
    ]);

    // Inicialmente con stock=40 y min_stock=20 NO necesita reposición (40 <= 20 es falso)
    expect(Product::needsRestock()->where('id', $product->id)->exists())->toBeFalse();

    // Usuario sube parámetro de stock mínimo a 45 (40 <= 45 es verdadero)
    $this->actingAs($this->user)
        ->putJson(route('tenant.products.update', $product->id), [
            'name' => $product->name,
            'category_id' => $this->category->id,
            'price' => $product->price,
            'min_stock' => 45,
            'is_active' => true,
        ])
        ->assertStatus(200);

    $product->refresh();
    expect(Product::needsRestock()->where('id', $product->id)->exists())->toBeTrue();

    // Usuario vuelve a bajar parámetro de stock mínimo a 30 (40 <= 30 es falso)
    $this->actingAs($this->user)
        ->putJson(route('tenant.products.update', $product->id), [
            'name' => $product->name,
            'category_id' => $this->category->id,
            'price' => $product->price,
            'min_stock' => 30,
            'is_active' => true,
        ])
        ->assertStatus(200);

    $product->refresh();
    expect(Product::needsRestock()->where('id', $product->id)->exists())->toBeFalse();
});

test('3. Modelo User rechaza asignacion masiva de is_superadmin y tenant_id', function () {
    $user = new User([
        'name' => 'Hacker Test',
        'email' => 'hacker@test.com',
        'password' => 'secret123',
        'is_superadmin' => true,
        'tenant_id' => 9999,
    ]);

    expect($user->isSuperadmin())->toBeFalse()
        ->and($user->tenant_id)->toBeNull();
});

test('4. Ruta publica de registro /register esta deshabilitada', function () {
    $this->get('/register')
        ->assertNotFound();

    $this->post('/register', [
        'name' => 'Public Stranger',
        'email' => 'stranger@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertNotFound();
});
