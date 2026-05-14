<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireOrganization;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetCurrentTenant::class,
        ]);

        // SetCurrentTenant must run before route model binding so the
        // BelongsToOrganization global scope has a tenant to apply.
        $middleware->priority([
            Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            Illuminate\Cookie\Middleware\EncryptCookies::class,
            Illuminate\Session\Middleware\StartSession::class,
            Illuminate\View\Middleware\ShareErrorsFromSession::class,
            Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            SetCurrentTenant::class,
            Illuminate\Routing\Middleware\SubstituteBindings::class,
            Illuminate\Auth\Middleware\Authenticate::class,
            Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            Illuminate\Auth\Middleware\Authorize::class,
        ]);

        $middleware->alias([
            'tenant' => RequireOrganization::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
