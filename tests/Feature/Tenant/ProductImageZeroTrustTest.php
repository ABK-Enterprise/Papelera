<?php

use App\Models\Category;
use App\Models\Module;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create(['is_active' => true]);
    $this->stockModule = Module::firstOrCreate(
        ['code' => 'productos'],
        ['name' => 'Catálogo de Productos', 'is_active' => true]
    );
    $this->tenant->modules()->syncWithoutDetaching([$this->stockModule->id]);

    $this->user = User::factory()->forTenant($this->tenant)->create();

    setPermissionsTeamId($this->tenant->id);
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);
    $this->user->assignRole($adminRole);

    $this->category = Category::factory()->forTenant($this->tenant)->create();
});

test('zero-trust: rejects svg images to prevent stored xss', function () {
    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("xss")</script></svg>';
    $fakeSvg = UploadedFile::fake()->createWithContent('malicious.svg', $svgContent);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Producto SVG Peligroso',
            'sale_type' => 'unit',
            'category_id' => $this->category->id,
            'price' => 1500,
            'image' => $fakeSvg,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('zero-trust: rejects files exceeding 5MB', function () {
    $largeImage = UploadedFile::fake()->image('huge.png')->size(6000); // 6MB

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Producto Imagen Pesada',
            'sale_type' => 'unit',
            'category_id' => $this->category->id,
            'price' => 1500,
            'image' => $largeImage,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('zero-trust: optimizes valid image to dual webp outputs and stores securely', function () {
    $image = UploadedFile::fake()->image('test_product.jpg', 600, 600);

    $response = $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Producto Optimizado',
            'sale_type' => 'unit',
            'category_id' => $this->category->id,
            'price' => 2500,
            'image' => $image,
        ]);

    $response->assertStatus(201);

    $product = Product::where('name', 'Producto Optimizado')->first();
    expect($product)->not->toBeNull()
        ->and($product->image_url)->not->toBeNull()
        ->and($product->thumbnail_url)->not->toBeNull()
        ->and($product->image_url)->toContain('.webp')
        ->and($product->thumbnail_url)->toContain('thumb_')
        ->and($product->thumbnail_url)->toContain('.webp');
});

test('zero-trust: deletes old image files when updating product with new image', function () {
    $firstImage = UploadedFile::fake()->image('first.png', 400, 400);

    $createResponse = $this->actingAs($this->user)
        ->postJson('/tenant/productos', [
            'name' => 'Producto para Actualizar',
            'sale_type' => 'unit',
            'category_id' => $this->category->id,
            'price' => 2000,
            'image' => $firstImage,
        ]);

    $createResponse->assertStatus(201);
    $product = Product::where('name', 'Producto para Actualizar')->first();
    $oldImageUrl = $product->image_url;
    $oldThumbUrl = $product->thumbnail_url;

    $secondImage = UploadedFile::fake()->image('second.jpg', 500, 500);

    $updateResponse = $this->actingAs($this->user)
        ->putJson("/tenant/productos/{$product->id}", [
            'name' => 'Producto Actualizado',
            'sale_type' => 'unit',
            'category_id' => $this->category->id,
            'price' => 2200,
            'image' => $secondImage,
        ]);

    $updateResponse->assertStatus(200);
    $product->refresh();

    expect($product->image_url)->not->toBe($oldImageUrl)
        ->and($product->thumbnail_url)->not->toBe($oldThumbUrl);
});
