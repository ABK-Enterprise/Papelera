<?php

use App\Models\User;
use Database\Seeders\BaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(BaseSeeder::class);
    $this->superadmin = User::where('is_superadmin', true)->first();
});

test('superadmin can view system modules and permissions catalog', function () {
    $response = $this->actingAs($this->superadmin)
        ->get(route('platform.modules.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('platform/modules/Index')
        ->has('modules')
        ->where('modules', fn ($modules) => count($modules) >= 6)
    );
});

test('regular tenant user cannot access platform modules catalog', function () {
    $tenantUser = User::where('is_superadmin', false)->first();

    $response = $this->actingAs($tenantUser)
        ->get(route('platform.modules.index'));

    $response->assertForbidden();
});
