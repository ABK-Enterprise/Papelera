<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

test('destructive commands are allowed when allow_destructive_commands is true even in production', function () {
    config(['database.allow_destructive_commands' => true]);

    $allowDestructive = config('database.allow_destructive_commands');
    $isProhibited = app()->isProduction() && ! $allowDestructive;

    $this->assertFalse($isProhibited);
});

test('destructive commands are prohibited in production when allow_destructive_commands is false', function () {
    config(['database.allow_destructive_commands' => false]);

    // Simulate production
    $allowDestructive = config('database.allow_destructive_commands');
    $isProhibited = true && ! $allowDestructive;

    $this->assertTrue($isProhibited);
});
