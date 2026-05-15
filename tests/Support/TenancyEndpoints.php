<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\BackgroundCheckStatus;
use App\Enums\ConsentType;
use App\Enums\FieldType;
use App\Enums\MatchAction;
use App\Enums\OrganizationRole;
use App\Enums\TeamRole;
use App\Models\BackgroundCheck;
use App\Models\Consent;
use App\Models\Division;
use App\Models\Form;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Season;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamUser;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Closure;

/**
 * Catalog of tenant-scoped routes for the cross-tenant isolation suite.
 *
 * Each entry is a closure that receives the foreign organization, sets up its
 * scoped records, and returns the HTTP verb / URL / payload that the test
 * exercises plus the expected status code. New routes that take a tenant-scoped
 * {model} binding should be appended here so the negative case is hard to
 * forget.
 *
 * @phpstan-type EndpointSpec array{method: string, url: string, payload: array<string, mixed>, expected_status: int}
 */
final class TenancyEndpoints
{
    /**
     * @return array<string, array{0: string, 1: Closure(Organization): array{method: string, url: string, payload: array<string, mixed>, expected_status: int}}>
     */
    public static function cases(): array
    {
        return [
            'forms.edit' => ['forms.edit', fn (Organization $org): array => self::spec('GET', route('forms.edit', self::form($org)))],
            'forms.update' => ['forms.update', fn (Organization $org): array => self::spec('PATCH', route('forms.update', self::form($org)), ['title' => 'x', 'description' => null, 'schema' => ['fields' => []]])],
            'forms.destroy' => ['forms.destroy', fn (Organization $org): array => self::spec('DELETE', route('forms.destroy', self::form($org)))],
            'forms.publish' => ['forms.publish', fn (Organization $org): array => self::spec('POST', route('forms.publish', self::form($org)))],
            'forms.close' => ['forms.close', fn (Organization $org): array => self::spec('POST', route('forms.close', self::form($org)))],
            'forms.submissions.index' => ['forms.submissions.index', fn (Organization $org): array => self::spec('GET', route('forms.submissions.index', self::form($org)))],
            'forms.submissions.show' => ['forms.submissions.show', fn (Organization $org): array => self::spec('GET', self::submissionUrl('forms.submissions.show', $org))],
            'forms.submissions.review' => ['forms.submissions.review', fn (Organization $org): array => self::spec('GET', self::submissionUrl('forms.submissions.review', $org))],
            'forms.submissions.process' => ['forms.submissions.process', fn (Organization $org): array => self::spec('POST', self::submissionUrl('forms.submissions.process', $org), [
                'player_action' => MatchAction::Skipped->value,
                'guardian_action' => MatchAction::Skipped->value,
            ])],
            'forms.submissions.consents.withdraw' => ['forms.submissions.consents.withdraw', fn (Organization $org): array => self::spec('POST', self::consentUrl($org))],
            'players.update' => ['players.update', fn (Organization $org): array => self::spec('PATCH', route('players.update', self::player($org)), ['first_name' => 'x', 'last_name' => 'y', 'dob' => '2014-04-12'])],
            'players.destroy' => ['players.destroy', fn (Organization $org): array => self::spec('DELETE', route('players.destroy', self::player($org)))],
            'teams.update' => ['teams.update', fn (Organization $org): array => self::spec('PATCH', route('teams.update', self::team($org)), ['name' => 'x', 'slug' => 'x', 'division_id' => self::division($org)->id])],
            'teams.destroy' => ['teams.destroy', fn (Organization $org): array => self::spec('DELETE', route('teams.destroy', self::team($org)))],
            'teams.roster.show' => ['teams.roster.show', fn (Organization $org): array => self::spec('GET', route('teams.roster.show', self::team($org)))],
            'teams.roster.store' => ['teams.roster.store', fn (Organization $org): array => self::spec('POST', route('teams.roster.store', self::team($org)), ['player_id' => self::player($org)->id])],
            'teams.roster.update' => ['teams.roster.update', fn (Organization $org): array => self::spec('PATCH', self::rosterUrl('teams.roster.update', $org), ['primary_position' => '1B'])],
            'teams.roster.destroy' => ['teams.roster.destroy', fn (Organization $org): array => self::spec('DELETE', self::rosterUrl('teams.roster.destroy', $org))],
            'teams.coaches.store' => ['teams.coaches.store', fn (Organization $org): array => self::spec('POST', route('teams.coaches.store', self::team($org)), ['user_id' => self::orgUser($org)->id, 'role' => TeamRole::TeamAdmin->value])],
            'teams.coaches.update' => ['teams.coaches.update', fn (Organization $org): array => self::spec('PATCH', self::coachUrl('teams.coaches.update', $org), ['role' => TeamRole::HeadCoach->value])],
            'teams.coaches.destroy' => ['teams.coaches.destroy', fn (Organization $org): array => self::spec('DELETE', self::coachUrl('teams.coaches.destroy', $org))],
            'seasons.update' => ['seasons.update', fn (Organization $org): array => self::spec('PATCH', route('seasons.update', self::season($org)), ['name' => 'x', 'start_date' => '2026-01-01', 'end_date' => '2026-06-01'])],
            'seasons.destroy' => ['seasons.destroy', fn (Organization $org): array => self::spec('DELETE', route('seasons.destroy', self::season($org)))],
            'seasons.activate' => ['seasons.activate', fn (Organization $org): array => self::spec('POST', route('seasons.activate', self::season($org)))],
            'seasons.rollover.show' => ['seasons.rollover.show', fn (Organization $org): array => self::spec('GET', route('seasons.rollover.show', self::season($org)))],
            'seasons.rollover.store' => ['seasons.rollover.store', fn (Organization $org): array => self::spec('POST', route('seasons.rollover.store', self::season($org)), ['name' => 'x', 'start_date' => '2026-08-01', 'end_date' => '2026-12-01', 'clone_teams' => false])],
            'divisions.update' => ['divisions.update', fn (Organization $org): array => self::spec('PATCH', route('divisions.update', self::division($org)), ['name' => 'x', 'short_code' => null, 'description' => null, 'min_birth_year' => null, 'max_birth_year' => null, 'display_order' => 1])],
            'divisions.destroy' => ['divisions.destroy', fn (Organization $org): array => self::spec('DELETE', route('divisions.destroy', self::division($org)))],
            'locations.update' => ['locations.update', fn (Organization $org): array => self::spec('PATCH', route('locations.update', self::location($org)), ['name' => 'x'])],
            'locations.destroy' => ['locations.destroy', fn (Organization $org): array => self::spec('DELETE', route('locations.destroy', self::location($org)))],
            'invitations.resend' => ['invitations.resend', fn (Organization $org): array => self::spec('POST', route('invitations.resend', self::invitation($org)))],
            'invitations.destroy' => ['invitations.destroy', fn (Organization $org): array => self::spec('DELETE', route('invitations.destroy', self::invitation($org)))],
            'background-checks.update' => ['background-checks.update', fn (Organization $org): array => self::spec('PATCH', route('background-checks.update', self::backgroundCheck($org)), ['provider' => 'x', 'status' => BackgroundCheckStatus::Cleared->value])],
            'background-checks.destroy' => ['background-checks.destroy', fn (Organization $org): array => self::spec('DELETE', route('background-checks.destroy', self::backgroundCheck($org)))],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{method: string, url: string, payload: array<string, mixed>, expected_status: int}
     */
    private static function spec(string $method, string $url, array $payload = [], int $expectedStatus = 404): array
    {
        return ['method' => $method, 'url' => $url, 'payload' => $payload, 'expected_status' => $expectedStatus];
    }

    private static function form(Organization $org): Form
    {
        return self::withoutTenant(static fn (): Form => Form::factory()->for($org)->published()->create());
    }

    private static function player(Organization $org): Player
    {
        return self::withoutTenant(static fn (): Player => Player::factory()->for($org)->create());
    }

    private static function team(Organization $org): Team
    {
        return self::withoutTenant(static function () use ($org): Team {
            $season = Season::factory()->for($org)->create();
            $division = Division::factory()->for($org)->create();

            return Team::factory()->for($org)->create([
                'season_id' => $season->id,
                'division_id' => $division->id,
            ]);
        });
    }

    private static function season(Organization $org): Season
    {
        return self::withoutTenant(static fn (): Season => Season::factory()->for($org)->create());
    }

    private static function division(Organization $org): Division
    {
        return self::withoutTenant(static fn (): Division => Division::factory()->for($org)->create());
    }

    private static function location(Organization $org): Location
    {
        return self::withoutTenant(static fn (): Location => Location::factory()->for($org)->create());
    }

    private static function invitation(Organization $org): Invitation
    {
        return self::withoutTenant(static fn (): Invitation => Invitation::factory()->for($org)->create());
    }

    private static function backgroundCheck(Organization $org): BackgroundCheck
    {
        return self::withoutTenant(static fn (): BackgroundCheck => BackgroundCheck::factory()->for($org)->for(self::orgUser($org))->cleared()->create());
    }

    private static function submissionUrl(string $route, Organization $org): string
    {
        return self::withoutTenant(static function () use ($route, $org): string {
            $form = Form::factory()->for($org)->published()->create();
            $submission = Submission::factory()->for($org)->for($form)->create();

            return route($route, [$form, $submission]);
        });
    }

    private static function consentUrl(Organization $org): string
    {
        return self::withoutTenant(static function () use ($org): string {
            $form = Form::factory()->for($org)->published()->create([
                'required_consents' => [ConsentType::Registration->value],
                'schema' => ['fields' => [['key' => 'first_name', 'label' => 'First', 'type' => FieldType::Text->value]]],
            ]);
            $submission = Submission::factory()->for($org)->for($form)->create();
            $consent = Consent::factory()->for($org)->for($submission)->create();

            return route('forms.submissions.consents.withdraw', [$form, $submission, $consent]);
        });
    }

    private static function rosterUrl(string $route, Organization $org): string
    {
        return self::withoutTenant(static function () use ($route, $org): string {
            $team = self::team($org);
            $player = self::player($org);
            $entry = TeamPlayer::query()->create([
                'team_id' => $team->id,
                'player_id' => $player->id,
            ]);

            return route($route, [$team, $entry]);
        });
    }

    private static function coachUrl(string $route, Organization $org): string
    {
        return self::withoutTenant(static function () use ($route, $org): string {
            $team = self::team($org);
            $user = self::orgUser($org);
            $entry = TeamUser::query()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => TeamRole::TeamAdmin->value,
            ]);

            return route($route, [$team, $entry]);
        });
    }

    private static function orgUser(Organization $org): User
    {
        $user = User::factory()->create();
        $user->organizations()->attach($org, ['role' => OrganizationRole::Coach->value]);

        return $user->fresh() ?? $user;
    }

    /**
     * Build org-scoped fixtures without a bound tenant (factories side-step
     * the global scope on insert, but any reads must explicitly opt out).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private static function withoutTenant(Closure $callback): mixed
    {
        $tenant = app(CurrentTenant::class);
        $tenant->clear();

        return $callback();
    }
}
