<?php

declare(strict_types=1);

use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('global scope filters submissions across organizations', function () {
    $formA = Form::factory()->for($this->orgA)->create();
    $formB = Form::factory()->for($this->orgB)->create();

    Submission::factory()->for($this->orgA)->for($formA)->create();
    Submission::factory()->for($this->orgA)->for($formA)->create();
    Submission::factory()->for($this->orgB)->for($formB)->create();

    $this->tenant->set($this->orgA);
    expect(Submission::query()->count())->toBe(2);

    $this->tenant->set($this->orgB);
    expect(Submission::query()->count())->toBe(1);
});

test('querying submissions without a tenant fails closed', function () {
    $this->tenant->clear();

    expect(fn () => Submission::query()->count())
        ->toThrow(TenantNotResolvedException::class);
});

test('Form::submissions() returns the submissions tied to a form', function () {
    $form = Form::factory()->for($this->orgA)->create();
    Submission::factory()->for($this->orgA)->for($form)->count(3)->create();
    Submission::factory()->for($this->orgA)->for(
        Form::factory()->for($this->orgA)->create()
    )->create();

    $this->tenant->set($this->orgA);
    expect($form->submissions()->count())->toBe(3);
});

test('Submission belongs to form and to optional submitter', function () {
    $form = Form::factory()->for($this->orgA)->create();
    $submission = Submission::factory()->for($this->orgA)->for($form)->create();

    $this->tenant->set($this->orgA);
    expect($submission->fresh()?->form?->id)->toBe($form->id)
        ->and($submission->fresh()?->submittedBy)->toBeNull();
});

test('submission stores the form schema snapshot and version at submit time', function () {
    $form = Form::factory()->for($this->orgA)->create();
    $submission = Submission::factory()->for($this->orgA)->for($form)->create([
        'schema_snapshot' => ['fields' => [['key' => 'k', 'label' => 'L', 'type' => 'text']]],
        'schema_version' => 3,
        'data' => ['k' => 'value'],
    ])->fresh();

    expect($submission?->schema_version)->toBe(3)
        ->and($submission?->schema_snapshot)->toBe(['fields' => [['key' => 'k', 'label' => 'L', 'type' => 'text']]])
        ->and($submission?->data)->toBe(['k' => 'value']);
});
