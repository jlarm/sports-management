<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\BackgroundCheck;
use App\Models\Organization;
use App\Models\User;
use App\Services\BackgroundChecks\BackgroundCheckGate;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
    app(CurrentTenant::class)->set($this->org);
    $this->gate = app(BackgroundCheckGate::class);
});

test('roleRequiresCheck returns true for HeadCoach and AssistantCoach only', function () {
    expect($this->gate->roleRequiresCheck(TeamRole::HeadCoach))->toBeTrue()
        ->and($this->gate->roleRequiresCheck(TeamRole::AssistantCoach))->toBeTrue()
        ->and($this->gate->roleRequiresCheck(TeamRole::TeamAdmin))->toBeFalse();
});

test('hasCurrentClearedCheck returns false when no check exists', function () {
    expect($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeFalse();
});

test('hasCurrentClearedCheck returns true for a cleared check with future expiry', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->user)->cleared()->create();
    expect($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeTrue();
});

test('hasCurrentClearedCheck returns true for a cleared check with no expiry (null)', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->user)->cleared()->create([
        'cleared_through' => null,
    ]);
    expect($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeTrue();
});

test('hasCurrentClearedCheck returns false when cleared_through is in the past', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->user)->expired()->create();
    expect($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeFalse();
});

test('hasCurrentClearedCheck returns false for a non-cleared status', function () {
    BackgroundCheck::factory()->for($this->org)->for($this->user)->pending()->create();
    expect($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeFalse();
});

test('hasCurrentClearedCheck is scoped to the given organization', function () {
    $other = Organization::factory()->create();
    BackgroundCheck::factory()->for($other)->for($this->user)->cleared()->create();
    expect($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeFalse();
});

test('BackgroundCheck::isCurrent matches the gate result', function () {
    $check = BackgroundCheck::factory()->for($this->org)->for($this->user)->cleared()->create();
    expect($check->isCurrent())->toBeTrue();

    $expired = BackgroundCheck::factory()->for($this->org)->for(User::factory()->create())->expired()->create();
    expect($expired->isCurrent())->toBeFalse();
});

test('BackgroundCheck::isCurrent returns false for non-cleared statuses', function () {
    $pending = BackgroundCheck::factory()->for($this->org)->for(User::factory()->create())->pending()->create();
    expect($pending->isCurrent())->toBeFalse();

    $flagged = BackgroundCheck::factory()->for($this->org)->for(User::factory()->create())->flagged()->create();
    expect($flagged->isCurrent())->toBeFalse();
});

test('BackgroundCheck::isCurrent returns true when cleared_through is null', function () {
    $check = BackgroundCheck::factory()->for($this->org)->for($this->user)->cleared()->create([
        'cleared_through' => null,
    ]);
    expect($check->isCurrent())->toBeTrue();
});

test('a check cleared through today is still current', function () {
    $check = BackgroundCheck::factory()->for($this->org)->for($this->user)->cleared()->create([
        'cleared_through' => now()->toDateString(),
    ]);
    expect($check->isCurrent())->toBeTrue()
        ->and($this->gate->hasCurrentClearedCheck($this->org->id, $this->user->id))->toBeTrue();
});
