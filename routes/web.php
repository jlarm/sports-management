<?php

declare(strict_types=1);

use App\Http\Controllers\AuditLogs\AuditLogsController;
use App\Http\Controllers\BackgroundChecks\BackgroundChecksController;
use App\Http\Controllers\Forms\FormsController;
use App\Http\Controllers\Forms\PublicFormController;
use App\Http\Controllers\Forms\SubmissionsController;
use App\Http\Controllers\Invitations\AcceptInvitationController;
use App\Http\Controllers\Invitations\DeclineInvitationController;
use App\Http\Controllers\Invitations\ShowInvitationController;
use App\Http\Controllers\Players\PlayersController;
use App\Http\Controllers\Seasons\SeasonRolloverController;
use App\Http\Controllers\Settings\DivisionsController;
use App\Http\Controllers\Settings\InvitationsController;
use App\Http\Controllers\Settings\LocationsController;
use App\Http\Controllers\Settings\SeasonsController;
use App\Http\Controllers\Teams\CoachesController;
use App\Http\Controllers\Teams\RosterController;
use App\Http\Controllers\Teams\TeamsController;
use App\Http\Controllers\Tenancy\SwitchOrganizationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
]))->name('home');

Route::get('f/{form}', [PublicFormController::class, 'show'])
    ->whereNumber('form')
    ->name('public-forms.show');
Route::post('f/{form}', [PublicFormController::class, 'submit'])
    ->whereNumber('form')
    ->middleware('throttle:6,1')
    ->name('public-forms.submit');
Route::get('f/{form}/thanks', [PublicFormController::class, 'thanks'])
    ->whereNumber('form')
    ->name('public-forms.thanks');

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
    ->prefix('players')
    ->name('players.')
    ->group(function () {
        Route::get('/', [PlayersController::class, 'index'])->name('index');
        Route::post('/', [PlayersController::class, 'store'])->name('store');
        Route::patch('{player}', [PlayersController::class, 'update'])->name('update');
        Route::delete('{player}', [PlayersController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('forms')
    ->name('forms.')
    ->group(function () {
        Route::get('/', [FormsController::class, 'index'])->name('index');
        Route::post('/', [FormsController::class, 'store'])->name('store');
        Route::get('{form}/edit', [FormsController::class, 'edit'])->name('edit');
        Route::patch('{form}', [FormsController::class, 'update'])->name('update');
        Route::delete('{form}', [FormsController::class, 'destroy'])->name('destroy');
        Route::post('{form}/publish', [FormsController::class, 'publish'])->name('publish');
        Route::post('{form}/close', [FormsController::class, 'close'])->name('close');

        Route::prefix('{form}/submissions')->name('submissions.')->group(function () {
            Route::get('/', [SubmissionsController::class, 'index'])->name('index');
            Route::get('{submission}', [SubmissionsController::class, 'show'])->name('show');
            Route::get('{submission}/review', [SubmissionsController::class, 'review'])->name('review');
            Route::post('{submission}/process', [SubmissionsController::class, 'process'])->name('process');
            Route::post('{submission}/consents/{consent}/withdraw', [SubmissionsController::class, 'withdrawConsent'])
                ->name('consents.withdraw');
        });
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('teams')
    ->name('teams.')
    ->group(function () {
        Route::get('/', [TeamsController::class, 'index'])->name('index');
        Route::post('/', [TeamsController::class, 'store'])->name('store');
        Route::patch('{team}', [TeamsController::class, 'update'])->name('update');
        Route::delete('{team}', [TeamsController::class, 'destroy'])->name('destroy');

        Route::prefix('{team}/roster')->name('roster.')->group(function () {
            Route::get('/', [RosterController::class, 'show'])->name('show');
            Route::post('/', [RosterController::class, 'store'])->name('store');
            Route::patch('{rosterEntry}', [RosterController::class, 'update'])->name('update');
            Route::delete('{rosterEntry}', [RosterController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('{team}/coaches')->name('coaches.')->group(function () {
            Route::post('/', [CoachesController::class, 'store'])->name('store');
            Route::patch('{coach}', [CoachesController::class, 'update'])->name('update');
            Route::delete('{coach}', [CoachesController::class, 'destroy'])->name('destroy');
        });
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
        Route::get('{season}/rollover', [SeasonRolloverController::class, 'show'])->name('rollover.show');
        Route::post('{season}/rollover', [SeasonRolloverController::class, 'store'])->name('rollover.store');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('divisions')
    ->name('divisions.')
    ->group(function () {
        Route::get('/', [DivisionsController::class, 'index'])->name('index');
        Route::post('/', [DivisionsController::class, 'store'])->name('store');
        Route::post('reorder', [DivisionsController::class, 'reorder'])->name('reorder');
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
        Route::post('{invitation}/resend', [InvitationsController::class, 'resend'])->name('resend');
        Route::delete('{invitation}', [InvitationsController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('audit-logs')
    ->name('audit-logs.')
    ->group(function () {
        Route::get('/', [AuditLogsController::class, 'index'])->name('index');
    });

Route::middleware(['auth', 'verified', 'tenant'])
    ->prefix('background-checks')
    ->name('background-checks.')
    ->group(function () {
        Route::get('/', [BackgroundChecksController::class, 'index'])->name('index');
        Route::post('/', [BackgroundChecksController::class, 'store'])->name('store');
        Route::patch('{background_check}', [BackgroundChecksController::class, 'update'])->name('update');
        Route::delete('{background_check}', [BackgroundChecksController::class, 'destroy'])->name('destroy');
    });

require __DIR__.'/settings.php';
