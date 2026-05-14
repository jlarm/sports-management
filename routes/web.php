<?php

declare(strict_types=1);

use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\DeclineInvitationController;
use App\Http\Controllers\Invitations\ShowInvitationController;
use App\Http\Controllers\Settings\DivisionsController;
use App\Http\Controllers\Settings\InvitationsController;
use App\Http\Controllers\Settings\LocationsController;
use App\Http\Controllers\Settings\SeasonsController;
use App\Http\Controllers\Tenancy\SwitchOrganizationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::post('organizations/{organization}/switch', SwitchOrganizationController::class)
        ->name('organizations.switch');

    Route::get('invitations/{token}', ShowInvitationController::class)
        ->name('invitations.show');
    Route::post('invitations/{token}/accept', AcceptInvitationController::class)
        ->name('invitations.accept');
    Route::post('invitations/{token}/decline', DeclineInvitationController::class)
        ->name('invitations.decline');
});

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('seasons')
    ->name('seasons.')
    ->group(function () {
        Route::get('/', [SeasonsController::class, 'index'])->name('index');
        Route::post('/', [SeasonsController::class, 'store'])->name('store');
        Route::patch('{season}', [SeasonsController::class, 'update'])->name('update');
        Route::delete('{season}', [SeasonsController::class, 'destroy'])->name('destroy');
        Route::post('{season}/activate', [SeasonsController::class, 'activate'])->name('activate');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('divisions')
    ->name('divisions.')
    ->group(function () {
        Route::get('/', [DivisionsController::class, 'index'])->name('index');
        Route::post('/', [DivisionsController::class, 'store'])->name('store');
        Route::patch('{division}', [DivisionsController::class, 'update'])->name('update');
        Route::delete('{division}', [DivisionsController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('locations')
    ->name('locations.')
    ->group(function () {
        Route::get('/', [LocationsController::class, 'index'])->name('index');
        Route::post('/', [LocationsController::class, 'store'])->name('store');
        Route::patch('{location}', [LocationsController::class, 'update'])->name('update');
        Route::delete('{location}', [LocationsController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('invitations')
    ->name('invitations.')
    ->group(function () {
        Route::get('/', [InvitationsController::class, 'index'])->name('index');
        Route::post('/', [InvitationsController::class, 'store'])->name('store');
        Route::delete('{invitation}', [InvitationsController::class, 'destroy'])->name('destroy');
    });

require __DIR__.'/settings.php';
