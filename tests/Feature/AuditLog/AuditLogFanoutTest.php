<?php

declare(strict_types=1);

use App\Enums\BattingHand;
use App\Enums\FormStatus;
use App\Enums\OrganizationRole;
use App\Enums\TeamRole;
use App\Enums\ThrowingHand;
use App\Models\AuditLog;
use App\Models\BackgroundCheck;
use App\Models\Division;
use App\Models\Form;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamUser;
use App\Models\User;

function fanoutAdminLogin(Organization $org): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => OrganizationRole::Admin->value]);

    return $user->fresh();
}

function lastAction(string $action): ?AuditLog
{
    return AuditLog::query()->withoutGlobalScopes()->where('action', $action)->latest('id')->first();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('player.created and player.archived are recorded', function () {
    $admin = fanoutAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('players.store'), [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'bats' => BattingHand::Right->value,
            'throws' => ThrowingHand::Right->value,
        ])->assertRedirect();

    $player = Player::query()->firstOrFail();
    expect(lastAction('player.created')?->subject_id)->toBe($player->id);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('players.destroy', $player))
        ->assertRedirect();

    expect(lastAction('player.archived')?->subject_id)->toBe($player->id);
});

test('roster.added, roster.updated, roster.removed are recorded', function () {
    $admin = fanoutAdminLogin($this->org);
    $season = Season::factory()->for($this->org)->active()->create();
    $division = Division::factory()->for($this->org)->create();
    $team = Team::factory()->for($this->org)->create([
        'season_id' => $season->id,
        'division_id' => $division->id,
    ]);
    $player = Player::factory()->for($this->org)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.roster.store', $team), ['player_id' => $player->id, 'jersey_number' => 7])
        ->assertRedirect();
    expect(lastAction('roster.added'))->not()->toBeNull();

    $entry = TeamPlayer::query()->firstOrFail();
    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.roster.update', [$team, $entry]), ['jersey_number' => 11])
        ->assertRedirect();
    expect(lastAction('roster.updated'))->not()->toBeNull();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('teams.roster.destroy', [$team, $entry]))
        ->assertRedirect();
    expect(lastAction('roster.removed'))->not()->toBeNull();
});

test('team_user assignment, role change, and removal are recorded', function () {
    $admin = fanoutAdminLogin($this->org);
    $season = Season::factory()->for($this->org)->active()->create();
    $division = Division::factory()->for($this->org)->create();
    $team = Team::factory()->for($this->org)->create([
        'season_id' => $season->id,
        'division_id' => $division->id,
    ]);
    $coach = User::factory()->create();
    $coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
    BackgroundCheck::factory()->for($this->org)->for($coach)->cleared()->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('teams.coaches.store', $team), [
            'user_id' => $coach->id,
            'role' => TeamRole::AssistantCoach->value,
        ])->assertRedirect();
    expect(lastAction('team_user.assigned'))->not()->toBeNull();

    $entry = TeamUser::query()->firstOrFail();
    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->patch(route('teams.coaches.update', [$team, $entry]), [
            'role' => TeamRole::HeadCoach->value,
        ])->assertRedirect();
    $roleChange = lastAction('team_user.role_changed');
    expect($roleChange?->payload['to'])->toBe(TeamRole::HeadCoach->value);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('teams.coaches.destroy', [$team, $entry]))
        ->assertRedirect();
    expect(lastAction('team_user.removed'))->not()->toBeNull();
});

test('form.published and form.closed are recorded', function () {
    $admin = fanoutAdminLogin($this->org);
    $form = Form::factory()->for($this->org)->create(['status' => FormStatus::Draft->value]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.publish', $form))
        ->assertRedirect();
    expect(lastAction('form.published')?->subject_id)->toBe($form->id);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('forms.close', $form))
        ->assertRedirect();
    expect(lastAction('form.closed')?->subject_id)->toBe($form->id);
});

test('season.activated is recorded', function () {
    $admin = fanoutAdminLogin($this->org);
    $season = Season::factory()->for($this->org)->create(['is_active' => false]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('seasons.activate', $season))
        ->assertRedirect();

    expect(lastAction('season.activated')?->subject_id)->toBe($season->id);
});

test('invitation.sent and invitation.revoked are recorded', function () {
    Illuminate\Support\Facades\Notification::fake();
    $admin = fanoutAdminLogin($this->org);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->post(route('invitations.store'), [
            'email' => 'new@example.com',
            'role' => OrganizationRole::Coach->value,
        ])->assertRedirect();
    expect(lastAction('invitation.sent'))->not()->toBeNull();

    $invitation = Invitation::query()->where('email', 'new@example.com')->firstOrFail();
    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect();

    expect(lastAction('invitation.revoked')?->subject_id)->toBe($invitation->id);
});

test('revoking a non-pending invitation does not write a duplicate revoked entry', function () {
    $admin = fanoutAdminLogin($this->org);
    $invitation = Invitation::factory()->for($this->org)->create(['revoked_at' => now()->subDay()]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->delete(route('invitations.destroy', $invitation))
        ->assertRedirect();

    expect(lastAction('invitation.revoked'))->toBeNull();
});
