<?php

declare(strict_types=1);

use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\DeclineInvitationController;
use App\Http\Controllers\Invitations\ShowInvitationController;
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

require __DIR__.'/settings.php';
