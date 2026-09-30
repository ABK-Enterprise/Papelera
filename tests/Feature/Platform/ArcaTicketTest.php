<?php

use App\Models\ArcaAccessTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('superadmin can access platform arca tickets page and view active ticket and history', function () {
    $superadmin = User::factory()->create([
        'is_superadmin' => true,
        'tenant_id' => null,
    ]);

    $activeTicket = ArcaAccessTicket::create([
        'cuit' => '20426602831',
        'unique_id' => '1728912384',
        'token_preview' => 'ab49ef12...',
        'service' => 'wsfe',
        'environment' => 'testing',
        'generated_at' => now()->subHours(2),
        'expires_at' => now()->addHours(9),
        'status' => 'active',
    ]);

    $oldTicket = ArcaAccessTicket::create([
        'cuit' => '20426602831',
        'unique_id' => '1728800000',
        'token_preview' => '99887766...',
        'service' => 'wsfe',
        'environment' => 'testing',
        'generated_at' => now()->subHours(24),
        'expires_at' => now()->subHours(12),
        'status' => 'expired',
    ]);

    $response = $this->actingAs($superadmin)->get(route('platform.arca-tickets.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/arca-tickets/Index')
            ->where('activeTicket.unique_id', '1728912384')
            ->where('activeTicket.is_valid', true)
            ->has('historicalTickets.data', 2)
        );
});

test('regular tenant user cannot access platform arca tickets page', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create([
        'is_superadmin' => false,
    ]);

    $response = $this->actingAs($user)->get(route('platform.arca-tickets.index'));

    $response->assertForbidden();
});

test('arca access ticket model identifies expired status properly', function () {
    $expiredTicket = ArcaAccessTicket::create([
        'cuit' => '20426602831',
        'unique_id' => '1728800000',
        'service' => 'wsfe',
        'environment' => 'testing',
        'generated_at' => now()->subHours(24),
        'expires_at' => now()->subHours(12),
        'status' => 'expired',
    ]);

    expect($expiredTicket->isExpired())->toBeTrue();

    $futureTicket = ArcaAccessTicket::create([
        'cuit' => '20426602831',
        'unique_id' => '1728900000',
        'service' => 'wsfe',
        'environment' => 'testing',
        'generated_at' => now(),
        'expires_at' => now()->addHours(10),
        'status' => 'active',
    ]);

    expect($futureTicket->isExpired())->toBeFalse();
    expect(ArcaAccessTicket::active()->count())->toBe(1);
});
