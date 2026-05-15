<?php

declare(strict_types=1);

use App\Enums\BackgroundCheckStatus;
use App\Enums\OrganizationRole;
use App\Enums\SubmissionStatus;
use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\BackgroundCheck;
use App\Models\Division;
use App\Models\Form;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;

function dashboardMember(Organization $org, OrganizationRole $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->organizations()->attach($org, ['role' => $role->value]);

    return $user->fresh();
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
});

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('verified users without a current organization are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('admin sees the full dashboard with manager-only sections', function () {
    $admin = dashboardMember($this->org, OrganizationRole::Admin);

    Season::factory()->for($this->org)->create([
        'name' => 'Spring 2026',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->addDays(20)->toDateString(),
        'is_active' => true,
    ]);

    Team::factory()->for($this->org)->count(2)->create();
    Player::factory()->for($this->org)->count(3)->create();
    Division::factory()->for($this->org)->count(4)->create();
    Location::factory()->for($this->org)->count(5)->create();

    $form = Form::factory()->for($this->org)->published()->create(['title' => 'Registration']);
    Submission::factory()->for($this->org)->for($form)->count(2)->create([
        'status' => SubmissionStatus::Pending,
    ]);

    Invitation::factory()->for($this->org)->create([
        'expires_at' => now()->addDays(3),
    ]);

    AuditLog::query()->create([
        'organization_id' => $this->org->id,
        'actor_user_id' => $admin->id,
        'action' => 'division.created',
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('can_manage', true)
            ->where('active_season.name', 'Spring 2026')
            ->where('counts.teams', 2)
            ->where('counts.players', 3)
            ->where('counts.divisions', 4)
            ->where('counts.locations', 5)
            ->where('pending_submissions.total', 2)
            ->has('pending_submissions.recent', 2)
            ->where('pending_invitations.total', 1)
            ->has('recent_audit', 1)
        );
});

test('coach sees the dashboard with manager-only sections hidden', function () {
    $coach = dashboardMember($this->org, OrganizationRole::Coach);

    Team::factory()->for($this->org)->create();
    Invitation::factory()->for($this->org)->create();

    $this->actingAs($coach)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('can_manage', false)
            ->where('counts.teams', 1)
            ->where('pending_submissions', null)
            ->where('blocked_coaches', null)
            ->where('pending_invitations', null)
            ->where('recent_audit', null)
        );
});

test('blocked coaches surfaces coaches without a current cleared background check', function () {
    $admin = dashboardMember($this->org, OrganizationRole::Admin);
    $cleared = dashboardMember($this->org, OrganizationRole::Coach);
    $missing = dashboardMember($this->org, OrganizationRole::Coach);
    $expired = dashboardMember($this->org, OrganizationRole::Coach);

    $team = Team::factory()->for($this->org)->create();
    foreach ([$cleared, $missing, $expired] as $user) {
        TeamUser::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamRole::HeadCoach->value,
        ]);
    }

    BackgroundCheck::factory()->for($this->org)->for($cleared)->cleared()->create([
        'cleared_through' => now()->addYear(),
    ]);
    BackgroundCheck::factory()->for($this->org)->for($expired)->create([
        'status' => BackgroundCheckStatus::Expired,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('blocked_coaches.total', 2)
            ->has('blocked_coaches.coaches', 2)
        );
});

test('expired active season reports zero days remaining', function () {
    $admin = dashboardMember($this->org, OrganizationRole::Admin);
    Season::factory()->for($this->org)->create([
        'start_date' => now()->subDays(40)->toDateString(),
        'end_date' => now()->subDays(5)->toDateString(),
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('active_season.days_remaining', 0));
});

test('blocked coaches returns empty when every coach is cleared', function () {
    $admin = dashboardMember($this->org, OrganizationRole::Admin);
    $coach = dashboardMember($this->org, OrganizationRole::Coach);

    $team = Team::factory()->for($this->org)->create();
    TeamUser::query()->create([
        'team_id' => $team->id,
        'user_id' => $coach->id,
        'role' => TeamRole::HeadCoach->value,
    ]);
    BackgroundCheck::factory()->for($this->org)->for($coach)->cleared()->create([
        'cleared_through' => now()->addYear(),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('blocked_coaches.total', 0)
            ->has('blocked_coaches.coaches', 0)
        );
});

test('dashboard counts and lists do not bleed across tenants', function () {
    $admin = dashboardMember($this->org, OrganizationRole::Admin);
    $foreign = Organization::factory()->create();

    Team::factory()->for($this->org)->create();
    Team::factory()->for($foreign)->count(3)->create();
    Invitation::factory()->for($foreign)->create();

    $this->actingAs($admin)
        ->withSession(['current_org_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('counts.teams', 1)
            ->where('pending_invitations.total', 0)
        );
});
