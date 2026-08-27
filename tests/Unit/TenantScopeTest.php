<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class TestProduct extends Model
{
    use BelongsToTenant;

    protected $table = 'test_products';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('test_products', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_products');
});

test('tenant scope automatically filters records by authenticated user tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $user1 = User::factory()->forTenant($tenant1)->create();
    $user2 = User::factory()->forTenant($tenant2)->create();

    // Create products for each tenant directly
    TestProduct::withoutGlobalScopes()->create([
        'tenant_id' => $tenant1->id,
        'name' => 'Papel Kraft 80g T1',
    ]);
    TestProduct::withoutGlobalScopes()->create([
        'tenant_id' => $tenant2->id,
        'name' => 'Caja Cartón T2',
    ]);

    // Acting as User 1
    Auth::login($user1);
    $productsT1 = TestProduct::all();

    expect($productsT1)->toHaveCount(1)
        ->first()->name->toBe('Papel Kraft 80g T1');

    // Acting as User 2
    Auth::login($user2);
    $productsT2 = TestProduct::all();

    expect($productsT2)->toHaveCount(1)
        ->first()->name->toBe('Caja Cartón T2');
});

test('belongs to tenant automatically injects authenticated tenant id on creation', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    Auth::login($user);

    $product = TestProduct::create([
        'name' => 'Resma A4',
    ]);

    expect($product->tenant_id)->toBe($tenant->id)
        ->and(TestProduct::count())->toBe(1);
});

test('unauthenticated or superadmin without tenant sees all records', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    TestProduct::withoutGlobalScopes()->create([
        'tenant_id' => $tenant1->id,
        'name' => 'Item 1',
    ]);
    TestProduct::withoutGlobalScopes()->create([
        'tenant_id' => $tenant2->id,
        'name' => 'Item 2',
    ]);

    Auth::logout();
    expect(TestProduct::count())->toBe(2);

    $superadmin = User::factory()->superadmin()->create();
    Auth::login($superadmin);

    expect(TestProduct::count())->toBe(2);
});
