<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

test('two-factor rate limiter applies a per-minute limit by login id', function () {
    $limiter = RateLimiter::limiter('two-factor');

    expect($limiter)->not->toBeNull();

    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('login.id', 42);

    $limit = $limiter($request);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(5)
        ->and($limit->key)->toBe(42);
});
