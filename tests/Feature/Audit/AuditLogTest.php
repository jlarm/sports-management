<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('AuditLogger writes a row scoped to the given organization with request context', function () {
    $user = User::factory()->create();
    /** @var Request $request */
    $request = app(Request::class);
    $request->setUserResolver(fn () => $user);
    $request->server->set('REMOTE_ADDR', '198.51.100.7');
    $request->headers->set('User-Agent', 'pest-agent/1.0');

    $form = Form::factory()->for($this->org)->create();
    $submission = Submission::factory()->for($this->org)->for($form)->create();

    $logger = new AuditLogger($request);
    $entry = $logger->log(
        organizationId: $this->org->id,
        action: 'test.event',
        subject: $submission,
        payload: ['note' => 'hello'],
    );

    $this->tenant->set($this->org);
    expect(AuditLog::query()->whereKey($entry->id)->exists())->toBeTrue();

    $fresh = $entry->fresh();
    expect($fresh?->action)->toBe('test.event')
        ->and($fresh?->actor_user_id)->toBe($user->id)
        ->and($fresh?->subject_type)->toBe($submission->getMorphClass())
        ->and($fresh?->subject_id)->toBe($submission->id)
        ->and($fresh?->payload)->toBe(['note' => 'hello'])
        ->and($fresh?->ip_address)->toBe('198.51.100.7')
        ->and($fresh?->user_agent)->toBe('pest-agent/1.0');
});

test('AuditLogger drops an empty payload to null', function () {
    /** @var Request $request */
    $request = app(Request::class);

    (new AuditLogger($request))->log(
        organizationId: $this->org->id,
        action: 'test.empty',
    );

    $this->tenant->set($this->org);
    $row = AuditLog::query()->where('action', 'test.empty')->firstOrFail();
    expect($row->payload)->toBeNull()
        ->and($row->subject_type)->toBeNull()
        ->and($row->subject_id)->toBeNull();
});

test('AuditLog::actor returns the linked user when present', function () {
    $user = User::factory()->create();
    $log = AuditLog::factory()->for($this->org)->create(['actor_user_id' => $user->id]);

    $this->tenant->set($this->org);
    expect($log->fresh()?->actor?->id)->toBe($user->id);
});

test('AuditLog::subject morph resolves to the original subject', function () {
    $form = Form::factory()->for($this->org)->create();
    $submission = Submission::factory()->for($this->org)->for($form)->create();
    $log = AuditLog::factory()->for($this->org)->create([
        'subject_type' => $submission->getMorphClass(),
        'subject_id' => $submission->id,
    ]);

    $this->tenant->set($this->org);
    expect($log->fresh()?->subject?->getKey())->toBe($submission->id);
});

test('AuditLog is tenant-scoped and fails closed without a tenant', function () {
    $other = Organization::factory()->create();
    AuditLog::factory()->for($this->org)->create();
    AuditLog::factory()->for($this->org)->create();
    AuditLog::factory()->for($other)->create();

    $this->tenant->set($this->org);
    expect(AuditLog::query()->count())->toBe(2);

    $this->tenant->clear();
    expect(fn () => AuditLog::query()->count())->toThrow(TenantNotResolvedException::class);
});
