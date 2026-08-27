<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);
test('root redirects to dashboard', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
