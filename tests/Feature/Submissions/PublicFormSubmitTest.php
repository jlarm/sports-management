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
            ->has('form.schema.fields', 5)
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

test('public submission accepts and stores checkboxes values within the allowed options', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'title' => 'Activities sign-up',
        'schema' => [
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'activities', 'label' => 'Activities', 'type' => FieldType::Checkboxes->value, 'required' => true, 'options' => ['Soccer', 'Basketball', 'Swim']],
            ],
        ],
    ]);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => [
            'name' => 'Sky',
            'activities' => ['Soccer', 'Swim'],
        ],
    ])->assertRedirect(route('public-forms.thanks', $form->id));

    expect(Submission::query()->withoutGlobalScopes()->where('form_id', $form->id)->first()?->data['activities'])
        ->toEqual(['Soccer', 'Swim']);
});

test('public form show includes custom consents in the payload', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'custom_consents' => [
            ['key' => 'parking', 'label' => 'Parking lot rules', 'text' => 'I will not park in the bus loop.'],
        ],
    ]);

    $this->get(route('public-forms.show', $form->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/Respond')
            ->where('form.consents.0.key', 'parking')
            ->where('form.consents.0.label', 'Parking lot rules')
            ->where('form.consents.0.type', 'custom')
        );
});

test('public submission requires acceptance of custom consents', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => ['fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'name', 'required' => true]]],
        'custom_consents' => [
            ['key' => 'parking', 'label' => 'Parking rules', 'text' => 'I agree.'],
        ],
    ]);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => ['name' => 'Sky'],
            'consents' => ['parking' => false],
        ])
        ->assertSessionHasErrors('consents.parking');
});

test('accepted custom consent creates a Consent record with the form-defined label', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => ['fields' => [['key' => 'name', 'label' => 'Name', 'type' => 'name', 'required' => true]]],
        'custom_consents' => [
            ['key' => 'parking', 'label' => 'Parking rules', 'text' => 'I will not park in the bus loop.'],
        ],
    ]);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => ['name' => 'Sky'],
        'consents' => ['parking' => true],
    ])->assertRedirect(route('public-forms.thanks', $form->id));

    $consent = App\Models\Consent::query()->withoutGlobalScopes()->where('form_id', null)->first()
        ?? App\Models\Consent::query()->withoutGlobalScopes()->latest('id')->first();

    expect($consent?->consent_type)->toBe(App\Enums\ConsentType::Custom)
        ->and($consent?->consent_label)->toBe('Parking rules')
        ->and($consent?->consent_text_snapshot)->toBe('I will not park in the bus loop.');
});

test('public submission accepts a toggle as a boolean', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => [
            'fields' => [
                ['key' => 'opt_in', 'label' => 'Receive updates', 'type' => FieldType::Toggle->value, 'required' => false],
            ],
        ],
    ]);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => ['opt_in' => true],
    ])->assertRedirect(route('public-forms.thanks', $form->id));
});

test('public submission rejects a non-boolean toggle value', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => [
            'fields' => [
                ['key' => 'opt_in', 'label' => 'Receive updates', 'type' => FieldType::Toggle->value, 'required' => false],
            ],
        ],
    ]);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => ['opt_in' => 'definitely'],
        ])
        ->assertSessionHasErrors('data.opt_in');
});

test('public submission accepts a phone formatted as NNN-NNN-NNNN', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => [
            'fields' => [
                ['key' => 'phone', 'label' => 'Phone', 'type' => FieldType::Phone->value, 'required' => true],
            ],
        ],
    ]);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => ['phone' => '555-123-4567'],
    ])->assertRedirect(route('public-forms.thanks', $form->id));
});

test('public submission rejects a phone in any other format', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => [
            'fields' => [
                ['key' => 'phone', 'label' => 'Phone', 'type' => FieldType::Phone->value, 'required' => true],
            ],
        ],
    ]);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => ['phone' => '(555) 123-4567'],
        ])
        ->assertSessionHasErrors('data.phone');
});

test('public submission rejects an invalid email field value', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => [
            'fields' => [
                ['key' => 'contact', 'label' => 'Email', 'type' => FieldType::Email->value, 'required' => true],
            ],
        ],
    ]);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => ['contact' => 'not-an-email'],
        ])
        ->assertSessionHasErrors('data.contact');
});

test('public submission rejects checkboxes values not in the allowed options', function () {
    $form = Form::factory()->for($this->org)->published()->create([
        'schema' => [
            'fields' => [
                ['key' => 'activities', 'label' => 'Activities', 'type' => FieldType::Checkboxes->value, 'required' => true, 'options' => ['Soccer', 'Basketball']],
            ],
        ],
    ]);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => ['activities' => ['Soccer', 'Lacrosse']],
        ])
        ->assertSessionHasErrors('data.activities.1');
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
