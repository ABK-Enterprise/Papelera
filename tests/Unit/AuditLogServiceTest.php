<?php

use App\Models\SystemAuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('audit log service excludes blacklisted sensitive attributes from old and new values', function () {
    $service = new AuditLogService();

    $tenant = Tenant::factory()->create([
        'name' => 'Comercio Original',
        'cuit_tax_id' => '20111111112',
    ]);

    $superadmin = User::factory()->create([
        'name' => 'Super Admin',
        'is_superadmin' => true,
    ]);

    // Simulating updates including sensitive keys
    $tenant->name = 'Comercio Modificado';
    $tenant->syncChanges();

    $log = $service->recordCustomAction(
        action: 'Modificación de prueba',
        auditable: $tenant,
        oldValues: [
            'name' => 'Comercio Original',
            'password' => 'secret123',
            'access_token' => 'mp_token_xyz',
            'private_key' => '-----BEGIN RSA PRIVATE KEY-----',
            'cuit_tax_id' => '20111111112',
        ],
        newValues: [
            'name' => 'Comercio Modificado',
            'password' => 'new_secret_456',
            'access_token' => 'mp_token_new',
            'private_key' => '-----BEGIN RSA PRIVATE KEY NEW-----',
            'cuit_tax_id' => '20222222223',
        ],
        entityLabel: 'Comercio #1',
        actor: $superadmin
    );

    expect($log)->toBeInstanceOf(SystemAuditLog::class)
        ->and($log->action)->toBe('Modificación de prueba')
        ->and($log->old_values)->toHaveKeys(['name', 'cuit_tax_id'])
        ->and($log->old_values)->not->toHaveKey('password')
        ->and($log->old_values)->not->toHaveKey('access_token')
        ->and($log->old_values)->not->toHaveKey('private_key')
        ->and($log->new_values)->toHaveKeys(['name', 'cuit_tax_id'])
        ->and($log->new_values)->not->toHaveKey('password')
        ->and($log->new_values)->not->toHaveKey('access_token')
        ->and($log->new_values)->not->toHaveKey('private_key')
        ->and($log->user_role)->toBe('superadmin');
});

test('audit log service does not create log entry if no non-blacklisted attributes changed', function () {
    $service = new AuditLogService();

    $user = User::factory()->create([
        'password' => 'initial_password',
    ]);

    $user->password = 'changed_password';
    $user->syncChanges();

    $log = $service->recordModelUpdate($user, 'Cambio de clave', 'Usuario #1', $user);

    expect($log)->toBeNull();
    expect(SystemAuditLog::count())->toBe(0);
});
