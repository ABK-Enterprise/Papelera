<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

uses(Tests\TestCase::class);

test('validates password mixed case rule with Spanish translation', function () {
    App::setLocale('es');

    $validator = Validator::make(
        ['password' => 'lowercaseonly123!'],
        ['password' => [Password::min(8)->mixedCase()]]
    );

    expect($validator->fails())->toBeTrue();
    $errors = $validator->errors()->get('password');
    expect($errors[0])->toBe('El campo contraseña debe contener mayúsculas y minúsculas.');
});

test('validates required and email rules with Spanish translation and attribute mapping', function () {
    App::setLocale('es');

    $validator = Validator::make(
        ['email' => 'invalid-email'],
        ['email' => ['required', 'email']]
    );

    expect($validator->fails())->toBeTrue();
    $errors = $validator->errors()->get('email');
    expect($errors[0])->toBe('El campo correo electrónico debe ser un correo electrónico válido.');
});

test('validates password confirmed and current_password rules', function () {
    App::setLocale('es');

    $validator = Validator::make(
        ['password' => 'Secret123!', 'password_confirmation' => 'Different123!'],
        ['password' => ['confirmed']]
    );

    expect($validator->fails())->toBeTrue();
    $errors = $validator->errors()->get('password');
    expect($errors[0])->toBe('La confirmación de contraseña no coincide.');
});
