<?php

declare(strict_types=1);

use App\Enums\ConsentType;
use App\Enums\FieldType;
use App\Enums\MatchAction;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\User;

/**
 * @param  array<int, string>  $consents
 */
function formWithConsents(Organization $org, array $consents): Form
{
    return Form::factory()->for($org)->published()->create([
        'title' => 'Spring Registration',
        'schema' => [
            'fields' => [
                ['key' => 'first_name', 'label' => 'First', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'last_name', 'label' => 'Last', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'dob', 'label' => 'DOB', 'type' => FieldType::Date->value, 'required' => true],
            ],
        ],
        'required_consents' => $consents,
    ]);
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('public form show exposes required consent text and version', function () {
    $form = formWithConsents($this->org, [
        ConsentType::Registration->value,
        ConsentType::MediaRelease->value,
    ]);

    $this->get(route('public-forms.show', $form->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('form.consents', 2)
            ->where('form.consents.0.type', ConsentType::Registration->value)
            ->where('form.consents.0.version', 1)
        );
});

test('public submit rejects when a required consent is missing', function () {
    $form = formWithConsents($this->org, [ConsentType::Registration->value]);

    $this->from(route('public-forms.show', $form->id))
        ->post(route('public-forms.submit', $form->id), [
            'data' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
        ])
        ->assertSessionHasErrors('consents.'.ConsentType::Registration->value);

    expect(Submission::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Consent::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('public submit writes consent rows and an audit log entry when accepted', function () {
    $form = formWithConsents($this->org, [
        ConsentType::Registration->value,
        ConsentType::MediaRelease->value,
    ]);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
        'consents' => [
            ConsentType::Registration->value => '1',
            ConsentType::MediaRelease->value => '1',
        ],
    ])->assertRedirectToRoute('public-forms.thanks', ['form' => $form->id]);

    $submission = Submission::query()->withoutGlobalScopes()->firstOrFail();
    $consents = Consent::query()->withoutGlobalScopes()->where('submission_id', $submission->id)->get();

    expect($consents)->toHaveCount(2)
        ->and($consents->pluck('consent_text_version')->unique()->values()->all())->toBe([1])
        ->and($consents->every(fn (Consent $c) => $c->consent_text_snapshot !== ''))->toBeTrue();

    $audits = AuditLog::query()->withoutGlobalScopes()->where('action', 'consent.granted')->get();
    expect($audits)->toHaveCount(2);
});

test('a form with no required consents accepts submissions without a consents key', function () {
    $form = formWithConsents($this->org, []);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
    ])->assertRedirectToRoute('public-forms.thanks', ['form' => $form->id]);

    expect(Consent::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('processed submission links consents to chosen player and guardian', function () {
    $form = formWithConsents($this->org, [ConsentType::Registration->value]);

    $this->post(route('public-forms.submit', $form->id), [
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'parent_email' => 'maria@example.com',
        ],
        'consents' => [ConsentType::Registration->value => '1'],
    ])->assertRedirect();

    $submission = Submission::query()->withoutGlobalScopes()->firstOrFail();

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->organizations()->attach($this->org, ['role' => OrganizationRole::Admin->value]);

    $this->actingAs($admin->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.submissions.process', [$form, $submission]), [
            'player_action' => MatchAction::Created->value,
            'player' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
            'guardian_action' => MatchAction::Created->value,
            'guardian' => ['first_name' => 'Maria', 'last_name' => 'Lopez', 'email' => 'maria@example.com'],
        ])
        ->assertRedirect();

    $consent = Consent::query()->withoutGlobalScopes()->firstOrFail();
    expect($consent->player_id)->not()->toBeNull()
        ->and($consent->guardian_id)->not()->toBeNull();
});

test('the form Edit page persists required consents on update', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->organizations()->attach($this->org, ['role' => OrganizationRole::Admin->value]);
    $form = Form::factory()->for($this->org)->create();

    $this->actingAs($admin->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('forms.update', $form), [
            'title' => $form->title,
            'description' => $form->description,
            'schema' => $form->schema,
            'required_consents' => [
                ConsentType::Registration->value,
                ConsentType::MediaRelease->value,
            ],
        ])
        ->assertRedirect();

    expect($form->fresh()?->required_consents)->toBe([
        ConsentType::Registration->value,
        ConsentType::MediaRelease->value,
    ]);

    $this->actingAs($admin->fresh())
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('forms.update', $form), [
            'title' => $form->title,
            'description' => $form->description,
            'schema' => $form->schema,
            'required_consents' => [],
        ])
        ->assertRedirect();

    expect($form->fresh()?->required_consents)->toBeNull();
});
