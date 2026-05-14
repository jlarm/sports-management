<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;

function publishedFormFor(Organization $org, int $version = 1): Form
{
    return Form::factory()->for($org)->published()->create([
        'title' => 'Spring Registration',
        'schema' => [
            'fields' => [
                ['key' => 'first_name', 'label' => 'First name', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'last_name', 'label' => 'Last name', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'dob', 'label' => 'Date of birth', 'type' => FieldType::Date->value, 'required' => true],
                ['key' => 'jersey_size', 'label' => 'Jersey size', 'type' => FieldType::Select->value, 'required' => true, 'options' => ['YS', 'YM', 'YL']],
                ['key' => 'allergies', 'label' => 'Allergies', 'type' => FieldType::Textarea->value, 'required' => false],
                ['key' => 'media_release', 'label' => 'Photo consent', 'type' => FieldType::Checkbox->value, 'required' => false],
            ],
        ],
        'schema_version' => $version,
    ]);
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('public form show renders for a published form', function () {
    $form = publishedFormFor($this->org);

    $this->get(route('public-forms.show', $form->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/Respond')
            ->where('form.id', $form->id)
            ->has('form.schema.fields', 6)
        );
});

test('public form show returns 404 for a draft form', function () {
    $form = Form::factory()->for($this->org)->create();

    $this->get(route('public-forms.show', $form->id))->assertNotFound();
});

test('public form show returns 404 for a closed form', function () {
    $form = Form::factory()->for($this->org)->closed()->create();

    $this->get(route('public-forms.show', $form->id))->assertNotFound();
});

test('public submission stores the data with the schema snapshot and version', function () {
    $form = publishedFormFor($this->org, version: 4);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => [
            'first_name' => 'Diego',
            'last_name' => 'Lopez',
            'dob' => '2014-04-12',
            'jersey_size' => 'YM',
            'allergies' => '',
            'media_release' => '1',
        ],
    ])->assertRedirectToRoute('public-forms.thanks', ['form' => $form->id]);

    $submission = Submission::query()->withoutGlobalScopes()->firstOrFail();
    expect($submission->organization_id)->toBe($this->org->id)
        ->and($submission->form_id)->toBe($form->id)
        ->and($submission->schema_version)->toBe(4)
        ->and($submission->submitted_by_user_id)->toBeNull()
        ->and($submission->data['first_name'] ?? null)->toBe('Diego')
        ->and($submission->data['jersey_size'] ?? null)->toBe('YM');

    expect(is_array($submission->schema_snapshot))->toBeTrue();
});

test('logged-in submitter is attached when authenticated', function () {
    $form = publishedFormFor($this->org);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('public-forms.submit', $form->id), [
            'data' => [
                'first_name' => 'Anna',
                'last_name' => 'Patel',
                'dob' => '2014-08-03',
                'jersey_size' => 'YS',
            ],
        ])
        ->assertRedirectToRoute('public-forms.thanks', ['form' => $form->id]);

    expect(Submission::query()->withoutGlobalScopes()->firstOrFail()->submitted_by_user_id)
        ->toBe($user->id);
});

test('public submission rejects missing required fields', function () {
    $form = publishedFormFor($this->org);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => [
                'first_name' => '',
                'last_name' => 'Lopez',
                'dob' => '2014-04-12',
                'jersey_size' => 'YM',
            ],
        ])
        ->assertSessionHasErrors('data.first_name');
});

test('public submission rejects invalid select option', function () {
    $form = publishedFormFor($this->org);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => [
                'first_name' => 'Diego',
                'last_name' => 'Lopez',
                'dob' => '2014-04-12',
                'jersey_size' => 'XXL',
            ],
        ])
        ->assertSessionHasErrors('data.jersey_size');
});

test('public submission rejects malformed date', function () {
    $form = publishedFormFor($this->org);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => [
                'first_name' => 'Diego',
                'last_name' => 'Lopez',
                'dob' => 'not-a-date',
                'jersey_size' => 'YM',
            ],
        ])
        ->assertSessionHasErrors('data.dob');
});

test('public submit on a draft form 404s', function () {
    $form = Form::factory()->for($this->org)->create();

    $this->post(route('public-forms.submit', $form->id), [
        'data' => ['first_name' => 'x'],
    ])->assertNotFound();
});

test('rulesFor skips fields with malformed keys and unknown types without throwing', function () {
    $form = Form::factory()->for($this->org)->published()->create();
    $form->forceFill([
        'schema' => [
            'fields' => [
                ['key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'required' => true],
                ['key' => '', 'label' => 'No key', 'type' => 'text'],
                ['key' => 42, 'label' => 'Non-string key', 'type' => 'text'],
                ['label' => 'Missing key entirely', 'type' => 'text'],
                ['key' => 'mystery', 'label' => 'Unknown type', 'type' => 'cosmic_ray'],
            ],
        ],
    ])->save();

    $this->post(route('public-forms.submit', $form->id), [
        'data' => [
            'first_name' => 'Diego',
            'mystery' => 'anything',
        ],
    ])->assertRedirectToRoute('public-forms.thanks', ['form' => $form->id]);
});

test('thanks page renders for a published form', function () {
    $form = publishedFormFor($this->org);

    $this->get(route('public-forms.thanks', $form->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/Thanks')
            ->where('form.id', $form->id)
        );
});
