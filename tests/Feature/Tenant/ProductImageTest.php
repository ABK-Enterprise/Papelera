<?php

use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Test',
        'is_active' => true,
    ]);

    $this->module = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Productos', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->module->id]);

    setPermissionsTeamId($this->tenant->id);

    // Permissions
    Permission::firstOrCreate(['name' => 'productos.ver', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'productos.editar', 'guard_name' => 'web']);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->adminRole->givePermissionTo(['productos.ver', 'productos.editar']);

    $this->viewerRole = Role::firstOrCreate([
        'name' => 'lector',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->viewerRole->givePermissionTo(['productos.ver']);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    $this->viewerUser = User::factory()->forTenant($this->tenant)->create();
    $this->viewerUser->assignRole($this->viewerRole);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Caja de Cartón 20x20',
        'price' => 1500.00,
    ]);
});

test('unauthenticated user cannot update product image', function () {
    $response = $this->postJson(route('tenant.products.update-image', $this->product), [
        'image' => UploadedFile::fake()->image('box.jpg', 600, 400),
    ]);

    $response->assertUnauthorized();
});

test('user from another tenant cannot update product image (tenant boundary protection)', function () {
    $otherTenant = Tenant::factory()->create(['name' => 'Otro Comercio']);
    $otherTenant->modules()->syncWithoutDetaching([$this->module->id]);
    setPermissionsTeamId($otherTenant->id);
    $otherRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $otherTenant->id]);
    $otherRole->givePermissionTo(['productos.ver', 'productos.editar']);
    $otherUser = User::factory()->forTenant($otherTenant)->create();
    $otherUser->assignRole($otherRole);

    $response = $this->actingAs($otherUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => UploadedFile::fake()->image('box.jpg', 600, 400),
        ]);

    $response->assertNotFound();
});

test('user without productos.editar permission is forbidden', function () {
    $response = $this->actingAs($this->viewerUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => UploadedFile::fake()->image('box.jpg', 600, 400),
        ]);

    $response->assertForbidden();
});

test('uploading new image with position stores full image and derived thumbnail', function () {
    $file = UploadedFile::fake()->image('producto_prueba.jpg', 800, 600);

    $response = $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => $file,
            'image_position' => [
                'zoom' => 1.5,
                'x' => 10.0,
                'y' => -5.0,
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true);

    $this->product->refresh();
    expect($this->product->image_url)->not->toBeNull()
        ->and($this->product->thumbnail_url)->not->toBeNull()
        ->and($this->product->image_position)->toEqual([
            'zoom' => 1.5,
            'x' => 10.0,
            'y' => -5.0,
        ]);
});

test('re-framing existing image without new file updates thumbnail and image_position', function () {
    // First upload an initial image
    $file = UploadedFile::fake()->image('producto_original.png', 1000, 800);
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => $file,
            'image_position' => ['zoom' => 1.0, 'x' => 0.0, 'y' => 0.0],
        ]);

    $this->product->refresh();
    $initialImageUrl = $this->product->image_url;
    $initialThumbnailUrl = $this->product->thumbnail_url;

    // Now re-frame without uploading a file
    $response = $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image_position' => [
                'zoom' => 2.0,
                'x' => -15.0,
                'y' => 20.0,
            ],
        ]);

    $response->assertOk()->assertJsonPath('success', true);

    $this->product->refresh();
    // image_url remains intact
    expect($this->product->image_url)->toBe($initialImageUrl)
        // thumbnail_url changed to newly generated thumbnail
        ->and($this->product->thumbnail_url)->not->toBe($initialThumbnailUrl)
        ->and($this->product->image_position)->toEqual([
            'zoom' => 2.0,
            'x' => -15.0,
            'y' => 20.0,
        ]);
});

test('invalid file mime type returns 422', function () {
    $file = UploadedFile::fake()->create('script.exe', 500, 'application/x-msdownload');

    $response = $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => $file,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);
});

test('remove_image removes files and clears database fields', function () {
    // Setup with image
    $file = UploadedFile::fake()->image('item.jpg', 600, 600);
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => $file,
            'image_position' => ['zoom' => 1.2, 'x' => 0, 'y' => 0],
        ]);

    $this->product->refresh();
    expect($this->product->image_url)->not->toBeNull();

    // Call remove_image
    $response = $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'remove_image' => true,
        ]);

    $response->assertOk()->assertJsonPath('success', true);

    $this->product->refresh();
    expect($this->product->image_url)->toBeNull()
        ->and($this->product->thumbnail_url)->toBeNull()
        ->and($this->product->image_position)->toBeNull();
});

test('destroy image endpoint deletes files and clears fields', function () {
    $file = UploadedFile::fake()->image('item.jpg', 600, 600);
    $this->actingAs($this->adminUser)
        ->postJson(route('tenant.products.update-image', $this->product), [
            'image' => $file,
        ]);

    $this->product->refresh();
    expect($this->product->image_url)->not->toBeNull();

    $response = $this->actingAs($this->adminUser)
        ->deleteJson(route('tenant.products.destroy-image', $this->product));

    $response->assertOk()->assertJsonPath('success', true);

    $this->product->refresh();
    expect($this->product->image_url)->toBeNull()
        ->and($this->product->thumbnail_url)->toBeNull()
        ->and($this->product->image_position)->toBeNull();
});
