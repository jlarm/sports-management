<?php

declare(strict_types=1);

use App\Enums\FormStatus;
use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\Organization;
use App\Models\User;

function formMemberLogin(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('index renders the forms inertia page with the orgs forms', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    Form::factory()->for($this->org)->count(2)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/Index')
            ->has('forms', 2)
        );
});

test('admin can create a draft form which redirects to the builder', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.store'), [
            'title' => 'Spring 2026 Registration',
            'description' => 'For all 8U-14U players',
            'schema' => [
                'fields' => [
                    [
                        'key' => 'first_name',
                        'label' => 'First name',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ])
        ->assertRedirectToRoute(
            'forms.edit',
            Form::query()->withoutGlobalScopes()->firstOrFail(),
        );

    $form = Form::query()->withoutGlobalScopes()->firstOrFail();
    expect($form->title)->toBe('Spring 2026 Registration')
        ->and($form->status)->toBe(FormStatus::Draft)
        ->and($form->schema_version)->toBe(1);
});

test('coach cannot create a form', function () {
    $coach = formMemberLogin($this->org, OrganizationRole::Coach);

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.store'), [
            'title' => 'Nope',
            'schema' => [
                'fields' => [
                    ['key' => 'x', 'label' => 'X', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->assertForbidden();
});

test('edit page renders for a draft form', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.edit', $form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('forms/Edit')
            ->where('form.id', $form->id)
            ->has('fieldTypeOptions', 6)
        );
});

test('store rejects schema with duplicate field keys', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->from(route('forms.index'))
        ->post(route('forms.store'), [
            'title' => 'Bad',
            'schema' => [
                'fields' => [
                    ['key' => 'dupe', 'label' => 'A', 'type' => 'text'],
                    ['key' => 'dupe', 'label' => 'B', 'type' => 'text'],
                ],
            ],
        ])
        ->assertSessionHasErrors('schema');
});

test('updating a draft does not bump schema_version', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('forms.update', $form), [
            'title' => $form->title,
            'schema' => [
                'fields' => [
                    ['key' => 'new_field', 'label' => 'New', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->schema_version)->toBe(1);
});

test('updating a published forms schema bumps schema_version', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->published()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('forms.update', $form), [
            'title' => $form->title,
            'schema' => [
                'fields' => [
                    ['key' => 'new_field', 'label' => 'New', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->schema_version)->toBe(2);
});

test('updating a published form without changing the schema does not bump the version', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->published()->create();
    $originalSchema = $form->schema;

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('forms.update', $form), [
            'title' => 'Renamed',
            'schema' => $originalSchema,
        ])
        ->assertRedirect(route('forms.edit', $form));

    expect($form->fresh()->schema_version)->toBe(1)
        ->and($form->fresh()->title)->toBe('Renamed');
});

test('closed forms reject updates with 403', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->closed()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('forms.update', $form), [
            'title' => 'Renamed',
            'schema' => $form->schema,
        ])
        ->assertForbidden();
});

test('admin can publish a draft', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.publish', $form))
        ->assertRedirect(route('forms.index'));

    expect($form->fresh()->status)->toBe(FormStatus::Published);
});

test('publishing a non-draft form is a no-op', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->published()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.publish', $form))
        ->assertRedirect(route('forms.index'));

    expect($form->fresh()->status)->toBe(FormStatus::Published);
});

test('admin can close a published form', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->published()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.close', $form))
        ->assertRedirect(route('forms.index'));

    expect($form->fresh()->status)->toBe(FormStatus::Closed);
});

test('closing a draft form is a no-op', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.close', $form))
        ->assertRedirect(route('forms.index'));

    expect($form->fresh()->status)->toBe(FormStatus::Draft);
});

test('admin can archive a form via soft delete', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $form = Form::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('forms.destroy', $form))
        ->assertRedirect(route('forms.index'));

    expect(Form::query()->withoutGlobalScopes()->withTrashed()->find($form->id)?->trashed())
        ->toBeTrue();
});

test('forms from another organization 404 via route binding', function () {
    $admin = formMemberLogin($this->org, OrganizationRole::Admin);
    $otherForm = Form::factory()->for(Organization::factory()->create())->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('forms.edit', $otherForm))
        ->assertNotFound();
});
