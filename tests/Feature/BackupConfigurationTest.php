<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

test('r2 filesystem disk is properly configured with s3 driver and path style endpoints', function () {
    $r2Config = config('filesystems.disks.r2');

    expect($r2Config)->toBeArray()
        ->and($r2Config['driver'])->toBe('s3')
        ->and($r2Config['bucket'])->toBe('abksolutions')
        ->and($r2Config['use_path_style_endpoint'])->toBeTrue()
        ->and($r2Config['region'])->toBe('auto');

    $disk = Storage::disk('r2');
    expect($disk)->not->toBeNull();
});

test('backup configuration targets r2 disk with proper pgsql dump options', function () {
    $destinationDisks = config('backup.backup.destination.disks');

    expect($destinationDisks)->toContain('r2');

    $pgsqlDumpConfig = config('database.connections.pgsql.dump');

    expect($pgsqlDumpConfig)->toBeArray()
        ->and($pgsqlDumpConfig['use_single_transaction'])->toBeTrue();
});
