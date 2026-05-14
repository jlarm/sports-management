<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\DivisionsController;
use App\Http\Controllers\Settings\InvitationsController;
use App\Http\Controllers\Settings\LocationsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SeasonsController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance.edit');
});

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('settings/seasons')
    ->name('seasons.')
    ->group(function () {
        Route::get('/', [SeasonsController::class, 'index'])->name('index');
        Route::post('/', [SeasonsController::class, 'store'])->name('store');
        Route::patch('{season}', [SeasonsController::class, 'update'])->name('update');
        Route::delete('{season}', [SeasonsController::class, 'destroy'])->name('destroy');
        Route::post('{season}/activate', [SeasonsController::class, 'activate'])->name('activate');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('settings/divisions')
    ->name('divisions.')
    ->group(function () {
        Route::get('/', [DivisionsController::class, 'index'])->name('index');
        Route::post('/', [DivisionsController::class, 'store'])->name('store');
        Route::patch('{division}', [DivisionsController::class, 'update'])->name('update');
        Route::delete('{division}', [DivisionsController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('settings/locations')
    ->name('locations.')
    ->group(function () {
        Route::get('/', [LocationsController::class, 'index'])->name('index');
        Route::post('/', [LocationsController::class, 'store'])->name('store');
        Route::patch('{location}', [LocationsController::class, 'update'])->name('update');
        Route::delete('{location}', [LocationsController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('settings/invitations')
    ->name('invitations.')
    ->group(function () {
        Route::get('/', [InvitationsController::class, 'index'])->name('index');
        Route::post('/', [InvitationsController::class, 'store'])->name('store');
        Route::delete('{invitation}', [InvitationsController::class, 'destroy'])->name('destroy');
    });
