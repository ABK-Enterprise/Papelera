<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('logout regenerates session and issues a fresh token', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->forTenant($tenant)->create([
        'password' => bcrypt('password123#'),
    ]);

    $this->actingAs($user);

    $oldSessionId = session()->getId();

    $response = $this->post(route('logout'));

    $response->assertRedirect();
    expect(session()->getId())->not->toBe($oldSessionId);
});

test('login with valid credentials authenticates successfully', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'test_login@papelera.com',
        'password' => bcrypt('Secret123#'),
    ]);

    $response = $this->post(route('login'), [
        'email' => 'test_login@papelera.com',
        'password' => 'Secret123#',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});
