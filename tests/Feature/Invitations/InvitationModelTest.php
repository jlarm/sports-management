<?php

declare(strict_types=1);

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Organization;

test('mintToken returns matching raw and hashed pair', function () {
    $minted = Invitation::mintToken();

    expect($minted['raw'])->toBeString()->toHaveLength(64)
        ->and($minted['hash'])->toBe(hash('sha256', $minted['raw']));
});

test('hashToken is deterministic and matches mintToken hash', function () {
    $raw = str_repeat('a', 64);

    expect(Invitation::hashToken($raw))->toBe(hash('sha256', $raw));
});

test('matchesToken returns true only for the original raw token', function () {
    $org = Organization::factory()->create();
    $minted = Invitation::mintToken();
    $invitation = Invitation::factory()->for($org)->create(['token_hash' => $minted['hash']]);

    expect($invitation->matchesToken($minted['raw']))->toBeTrue()
        ->and($invitation->matchesToken('wrong-token'))->toBeFalse();
});

test('status reflects timestamps and expiry', function () {
    $org = Organization::factory()->create();

    $pending = Invitation::factory()->for($org)->create();
    $expired = Invitation::factory()->for($org)->expired()->create();
    $accepted = Invitation::factory()->for($org)->accepted()->create();
    $declined = Invitation::factory()->for($org)->declined()->create();
    $revoked = Invitation::factory()->for($org)->revoked()->create();

    expect($pending->status())->toBe(InvitationStatus::Pending)
        ->and($pending->isPending())->toBeTrue()
        ->and($expired->status())->toBe(InvitationStatus::Expired)
        ->and($accepted->status())->toBe(InvitationStatus::Accepted)
        ->and($declined->status())->toBe(InvitationStatus::Declined)
        ->and($revoked->status())->toBe(InvitationStatus::Revoked);
});

test('pending scope filters out accepted, declined, revoked, and expired', function () {
    $org = Organization::factory()->create();
    Invitation::factory()->for($org)->create(['email' => 'p@example.com']);
    Invitation::factory()->for($org)->accepted()->create(['email' => 'a@example.com']);
    Invitation::factory()->for($org)->declined()->create(['email' => 'd@example.com']);
    Invitation::factory()->for($org)->revoked()->create(['email' => 'r@example.com']);
    Invitation::factory()->for($org)->expired()->create(['email' => 'e@example.com']);

    $emails = Invitation::query()->withoutGlobalScopes()->pending()->pluck('email')->all();

    expect($emails)->toBe(['p@example.com']);
});
