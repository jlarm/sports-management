<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BackgroundCheckStatus;
use App\Enums\SubmissionStatus;
use App\Enums\TeamRole;
use App\Http\Resources\Dashboard\ActiveSeasonResource;
use App\Http\Resources\Dashboard\BlockedCoachResource;
use App\Http\Resources\Dashboard\PendingInvitationResource;
use App\Http\Resources\Dashboard\PendingSubmissionResource;
use App\Http\Resources\Dashboard\RecentAuditResource;
use App\Models\AuditLog;
use App\Models\BackgroundCheck;
use App\Models\Division;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Player;
use App\Models\Season;
use App\Models\Submission;
use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user !== null
            && $this->tenant->isResolved()
            && ($user->roleIn($this->tenant->get())?->canManageOrganization() ?? false);

        $activeSeason = Season::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        return Inertia::render('Dashboard', [
            'can_manage' => $canManage,
            'active_season' => $activeSeason !== null
                ? ActiveSeasonResource::make($activeSeason)->toArray($request)
                : null,
            'counts' => $this->counts(),
            'pending_submissions' => $canManage ? $this->pendingSubmissions($request) : null,
            'blocked_coaches' => $canManage ? $this->blockedCoaches($request) : null,
            'pending_invitations' => $canManage ? $this->pendingInvitations($request) : null,
            'recent_audit' => $canManage ? $this->recentAudit($request) : null,
        ]);
    }

    /**
     * @return array{teams: int, players: int, divisions: int, locations: int}
     */
    private function counts(): array
    {
        return [
            'teams' => Team::query()->count(),
            'players' => Player::query()->count(),
            'divisions' => Division::query()->count(),
            'locations' => Location::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingSubmissions(Request $request): array
    {
        $base = Submission::query()->where('status', SubmissionStatus::Pending->value);

        $recent = (clone $base)
            ->with('form:id,title')
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get();

        return [
            'total' => $base->count(),
            'recent' => PendingSubmissionResource::collection($recent)->toArray($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockedCoaches(Request $request): array
    {
        $coachUserIds = TeamUser::query()
            ->whereIn('team_id', Team::query()->select('id'))
            ->whereIn('role', [TeamRole::HeadCoach->value, TeamRole::AssistantCoach->value])
            ->distinct()
            ->pluck('user_id');

        if ($coachUserIds->isEmpty()) {
            return ['total' => 0, 'coaches' => []];
        }

        $today = CarbonImmutable::today();

        $clearedUserIds = BackgroundCheck::query()
            ->whereIn('user_id', $coachUserIds)
            ->where('status', BackgroundCheckStatus::Cleared->value)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('cleared_through')
                    ->orWhere('cleared_through', '>=', $today->toDateString());
            })
            ->distinct()
            ->pluck('user_id');

        $blockedIds = $coachUserIds->diff($clearedUserIds);

        if ($blockedIds->isEmpty()) {
            return ['total' => 0, 'coaches' => []];
        }

        $latestStatusByUser = BackgroundCheck::query()
            ->whereIn('user_id', $blockedIds)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('user_id');

        $coaches = User::query()
            ->whereIn('id', $blockedIds)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->each(function (User $coach) use ($latestStatusByUser): void {
                $check = $latestStatusByUser->get($coach->id)?->first();
                $coach->setAttribute(
                    'latest_check_status',
                    $check instanceof BackgroundCheck ? $check->status->value : null,
                );
            });

        return [
            'total' => $blockedIds->count(),
            'coaches' => BlockedCoachResource::collection($coaches)->toArray($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingInvitations(Request $request): array
    {
        $base = Invitation::query()->pending();

        $recent = (clone $base)
            ->orderBy('expires_at')
            ->limit(5)
            ->get();

        return [
            'total' => $base->count(),
            'recent' => PendingInvitationResource::collection($recent)->toArray($request),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function recentAudit(Request $request): array
    {
        $entries = AuditLog::query()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        /** @var array<int, mixed> $rows */
        $rows = RecentAuditResource::collection($entries)->toArray($request);

        return $rows;
    }
}
