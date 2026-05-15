<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Guardian;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Submission;
use App\Services\Submissions\SubmissionMatcher;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
    $this->tenant->set($this->org);
    $this->form = Form::factory()->for($this->org)->create();
    $this->matcher = app(SubmissionMatcher::class);
});

test('matcher extracts player and guardian fields from submission data', function () {
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => [
            'first_name' => '  Diego ',
            'last_name' => 'Lopez',
            'dob' => '2014-04-12',
            'jersey_size' => 'YM',
            'allergies' => 'peanuts',
            'parent_first_name' => 'Maria',
            'parent_last_name' => 'Lopez',
            'parent_email' => '  MARIA@example.COM ',
            'parent_phone' => '555-1212',
        ],
    ]);

    $result = $this->matcher->match($submission);

    expect($result->extractedPlayer['first_name'])->toBe('Diego')
        ->and($result->extractedPlayer['last_name'])->toBe('Lopez')
        ->and($result->extractedPlayer['dob'])->toBe('2014-04-12')
        ->and($result->extractedPlayer['jersey_size'])->toBe('YM')
        ->and($result->extractedPlayer['medical_notes'])->toBe('peanuts')
        ->and($result->extractedGuardian['email'])->toBe('maria@example.com')
        ->and($result->extractedGuardian['phone'])->toBe('555-1212')
        ->and($result->canMatchPlayer())->toBeTrue()
        ->and($result->canMatchGuardian())->toBeTrue();
});

test('matcher returns no player candidates when last_name or dob is missing', function () {
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['first_name' => 'Diego'],
    ]);

    $result = $this->matcher->match($submission);

    expect($result->playerCandidates)->toHaveCount(0)
        ->and($result->canMatchPlayer())->toBeFalse()
        ->and($result->canMatchGuardian())->toBeFalse();
});

test('matcher finds player candidates by case-insensitive last_name and dob', function () {
    Player::factory()->for($this->org)->create([
        'first_name' => 'Diego',
        'last_name' => 'Lopez',
        'dob' => '2014-04-12',
    ]);
    Player::factory()->for($this->org)->create([
        'first_name' => 'Maria',
        'last_name' => 'lopez',
        'dob' => '2014-04-12',
    ]);
    Player::factory()->for($this->org)->create([
        'first_name' => 'Other',
        'last_name' => 'Lopez',
        'dob' => '2015-01-01',
    ]);

    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['last_name' => 'LOPEZ', 'dob' => '2014-04-12'],
    ]);

    $result = $this->matcher->match($submission);

    expect($result->playerCandidates)->toHaveCount(2);
});

test('matcher does not bleed across organizations', function () {
    $other = Organization::factory()->create();
    Player::factory()->for($other)->create([
        'last_name' => 'Lopez',
        'dob' => '2014-04-12',
    ]);

    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['last_name' => 'Lopez', 'dob' => '2014-04-12'],
    ]);

    expect($this->matcher->match($submission)->playerCandidates)->toHaveCount(0);
});

test('matcher finds guardian candidates by case-insensitive email', function () {
    Guardian::factory()->for($this->org)->create(['email' => 'parent@example.com']);
    Guardian::factory()->for($this->org)->create(['email' => 'PARENT@EXAMPLE.com']);

    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['parent_email' => 'Parent@Example.Com'],
    ]);

    expect($this->matcher->match($submission)->guardianCandidates)->toHaveCount(2);
});

test('matcher skips date that cannot be parsed', function () {
    $submission = Submission::factory()->for($this->org)->for($this->form)->create([
        'data' => ['last_name' => 'Lopez', 'dob' => 'not-a-date'],
    ]);

    expect($this->matcher->match($submission)->extractedPlayer['dob'])->toBeNull();
});
