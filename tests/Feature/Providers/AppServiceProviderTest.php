<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\Password;

test('password defaults use strict rules in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $rule = Password::default();

    expect($rule)->toBeInstanceOf(Password::class);
});
