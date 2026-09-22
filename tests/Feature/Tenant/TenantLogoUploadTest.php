<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::factory()->create([
        'name' => 'Papelera Central',
        'is_active' => true,
        'logo_url' => null,
    ]);

    setPermissionsTeamId($this->tenant->id);

    $this->adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->userRole = Role::firstOrCreate([
        'name' => 'vendedor',
        'guard_name' => 'web',
        'tenant_id' => $this->tenant->id,
    ]);

    $this->adminUser = User::factory()->forTenant($this->tenant)->create();
    $this->adminUser->assignRole($this->adminRole);

    $this->regularUser = User::factory()->forTenant($this->tenant)->create();
    $this->regularUser->assignRole($this->userRole);
});

test('tenant admin can upload a valid image and it is stored as webp', function () {
    $file = UploadedFile::fake()->image('logo.png', 400, 400);

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.settings.company.logo.update'), [
            'logo' => $file,
        ]);

    $response->assertRedirect(route('tenant.settings.company'));

    $this->tenant->refresh();
    expect($this->tenant->logo_url)->not->toBeNull();
    expect($this->tenant->logo_url)->toContain("tenants/{$this->tenant->id}/logo_");
    expect($this->tenant->logo_url)->toEndWith('.webp');

    // Verify physical file exists on fake public storage disk
    $path = parse_url($this->tenant->logo_url, PHP_URL_PATH);
    $relativePath = ltrim(preg_replace('#^/?storage/#', '', $path), '/');
    expect(Storage::disk('public')->exists($relativePath))->toBeTrue();
});

test('uploading a non-image file is rejected with validation error', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->adminUser)
        ->post(route('tenant.settings.company.logo.update'), [
            'logo' => $file,
        ]);

    $response->assertSessionHasErrors('logo');
    $this->tenant->refresh();
    expect($this->tenant->logo_url)->toBeNull();
});

test('replacing existing logo deletes the old file from disk', function () {
    $file1 = UploadedFile::fake()->image('logo1.jpg', 300, 300);
    $this->actingAs($this->adminUser)
        ->post(route('tenant.settings.company.logo.update'), ['logo' => $file1]);

    $this->tenant->refresh();
    $oldUrl = $this->tenant->logo_url;
    $oldPath = ltrim(preg_replace('#^/?storage/#', '', parse_url($oldUrl, PHP_URL_PATH)), '/');
    expect(Storage::disk('public')->exists($oldPath))->toBeTrue();

    // Upload replacement
    $file2 = UploadedFile::fake()->image('logo2.png', 500, 500);
    $this->actingAs($this->adminUser)
        ->post(route('tenant.settings.company.logo.update'), ['logo' => $file2]);

    $this->tenant->refresh();
    $newUrl = $this->tenant->logo_url;
    $newPath = ltrim(preg_replace('#^/?storage/#', '', parse_url($newUrl, PHP_URL_PATH)), '/');

    expect($newUrl)->not->toEqual($oldUrl);
    expect(Storage::disk('public')->exists($newPath))->toBeTrue();
    expect(Storage::disk('public')->exists($oldPath))->toBeFalse();
});

test('tenant admin can delete logo removing file from disk and setting logo_url to null', function () {
    $file = UploadedFile::fake()->image('logo.png', 300, 300);
    $this->actingAs($this->adminUser)
        ->post(route('tenant.settings.company.logo.update'), ['logo' => $file]);

    $this->tenant->refresh();
    $logoUrl = $this->tenant->logo_url;
    $path = ltrim(preg_replace('#^/?storage/#', '', parse_url($logoUrl, PHP_URL_PATH)), '/');
    expect(Storage::disk('public')->exists($path))->toBeTrue();

    // Delete logo
    $deleteResponse = $this->actingAs($this->adminUser)
        ->delete(route('tenant.settings.company.logo.delete'));

    $deleteResponse->assertRedirect(route('tenant.settings.company'));

    $this->tenant->refresh();
    expect($this->tenant->logo_url)->toBeNull();
    expect(Storage::disk('public')->exists($path))->toBeFalse();
});

test('regular user without admin role cannot update or delete logo', function () {
    $file = UploadedFile::fake()->image('logo.png', 300, 300);

    $updateResponse = $this->actingAs($this->regularUser)
        ->post(route('tenant.settings.company.logo.update'), ['logo' => $file]);

    $updateResponse->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));

    $deleteResponse = $this->actingAs($this->regularUser)
        ->delete(route('tenant.settings.company.logo.delete'));

    $deleteResponse->assertRedirect(route('auth.access-denied', ['reason' => 'insufficient_permissions']));
});

test('TenantLogoService returns base64 data URI when logo file exists and null when not', function () {
    $service = app(TenantLogoService::class);

    // No logo
    expect($service->getBase64Logo($this->tenant))->toBeNull();

    // Upload logo
    $file = UploadedFile::fake()->image('logo.png', 200, 200);
    $logoUrl = $service->storeAndOptimize($file, (int) $this->tenant->id);
    $this->tenant->update(['logo_url' => $logoUrl]);

    $base64 = $service->getBase64Logo($this->tenant);
    expect($base64)->not->toBeNull();
    expect($base64)->toStartWith('data:image/webp;base64,');
});
