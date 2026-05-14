<?php

declare(strict_types=1);

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->tenant = app(CurrentTenant::class);
});

test('global scope filters forms across organizations', function () {
    Form::factory()->for($this->orgA)->create(['title' => 'A1']);
    Form::factory()->for($this->orgA)->create(['title' => 'A2']);
    Form::factory()->for($this->orgB)->create(['title' => 'B1']);

    $this->tenant->set($this->orgA);
    expect(Form::query()->pluck('title')->all())->toContain('A1')->toContain('A2');
    expect(Form::query()->pluck('title')->all())->not->toContain('B1');

    $this->tenant->set($this->orgB);
    expect(Form::query()->pluck('title')->all())->toBe(['B1']);
});

test('querying forms without a tenant fails closed', function () {
    Form::factory()->for($this->orgA)->create();
    $this->tenant->clear();

    expect(fn () => Form::query()->count())->toThrow(TenantNotResolvedException::class);
});

test('creating a form auto-fills organization_id from the current tenant', function () {
    $this->tenant->set($this->orgA);

    $form = Form::create([
        'title' => 'Auto',
        'status' => FormStatus::Draft->value,
        'schema' => ['fields' => [
            ['key' => 'first_name', 'label' => 'First', 'type' => 'text', 'required' => true],
        ]],
        'schema_version' => 1,
    ])->fresh();

    expect($form->organization_id)->toBe($this->orgA->id);
});

test('status casts and isDraft/isPublished/isClosed helpers work', function () {
    $this->tenant->set($this->orgA);
    $draft = Form::factory()->for($this->orgA)->create()->fresh();
    $published = Form::factory()->for($this->orgA)->published()->create()->fresh();
    $closed = Form::factory()->for($this->orgA)->closed()->create()->fresh();

    expect($draft->isDraft())->toBeTrue()
        ->and($draft->isPublished())->toBeFalse()
        ->and($draft->isClosed())->toBeFalse()
        ->and($published->isPublished())->toBeTrue()
        ->and($closed->isClosed())->toBeTrue()
        ->and($draft->status)->toBe(FormStatus::Draft);
});
