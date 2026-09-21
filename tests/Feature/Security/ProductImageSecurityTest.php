<?php

use App\Models\Tenant;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('product image service restricts image deletion strictly to tenant directory', function () {
    Storage::fake('public');

    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    // Create image belonging to tenant 2
    $tenant2ImagePath = "products/{$tenant2->id}/legitimate-image.webp";
    Storage::disk('public')->put($tenant2ImagePath, 'image-content');
    expect(Storage::disk('public')->exists($tenant2ImagePath))->toBeTrue();

    // Tenant 1 attempts to delete Tenant 2's image via IDOR
    $service = app(ProductImageService::class);
    $service->deleteImages(
        imageUrl: Storage::disk('public')->url($tenant2ImagePath),
        thumbnailUrl: null,
        tenantId: $tenant1->id
    );

    // Tenant 2's image must still exist (not deleted!)
    expect(Storage::disk('public')->exists($tenant2ImagePath))->toBeTrue();

    // When tenant 2 deletes its own image, it is deleted
    $service->deleteImages(
        imageUrl: Storage::disk('public')->url($tenant2ImagePath),
        thumbnailUrl: null,
        tenantId: $tenant2->id
    );

    expect(Storage::disk('public')->exists($tenant2ImagePath))->toBeFalse();
});

test('storing product image creates optimized webp files and thumbnail', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create();
    $service = app(ProductImageService::class);

    $fakeFile = UploadedFile::fake()->image('producto-test.jpg', 600, 400);

    $result = $service->storeAndOptimize($fakeFile, $tenant->id);

    expect($result)->toHaveKeys(['image_url', 'thumbnail_url']);
    expect($result['image_url'])->toContain('.webp');
    expect($result['thumbnail_url'])->toContain('.webp');

    $imageRelPath = parse_url($result['image_url'], PHP_URL_PATH);
    $imagePath = str_replace('/storage/', '', $imageRelPath);

    expect(Storage::disk('public')->exists($imagePath))->toBeTrue();
});
