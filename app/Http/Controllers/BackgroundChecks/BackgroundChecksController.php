<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackgroundChecks;

use App\Enums\BackgroundCheckStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\BackgroundChecks\StoreBackgroundCheckRequest;
use App\Http\Requests\BackgroundChecks\UpdateBackgroundCheckRequest;
use App\Models\BackgroundCheck;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class BackgroundChecksController extends Controller
{
    public function index(CurrentTenant $tenant): Response
    {
        $this->authorize('viewAny', BackgroundCheck::class);

        $org = $tenant->get();

        $members = User::query()
            ->whereHas('organizations', function (Builder $q) use ($org): void {
                $q->where('organization_id', $org->id)
                    ->whereIn('role', [
                        OrganizationRole::Owner->value,
                        OrganizationRole::Admin->value,
                        OrganizationRole::Coach->value,
                    ]);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $checks = BackgroundCheck::query()
            ->where('organization_id', $org->id)
            ->get()
            ->keyBy('user_id');

        $rows = $members->map(function (User $member) use ($checks): array {
            $check = $checks->get($member->id);

            return [
                'user' => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                ],
                'check' => $check === null ? null : [
                    'id' => $check->id,
                    'provider' => $check->provider,
                    'status' => $check->status->value,
                    'status_label' => $check->status->label(),
                    'cleared_through' => $check->cleared_through?->toDateString(),
                    'is_current' => $check->isCurrent(),
                    'notes' => $check->notes,
                ],
            ];
        })->all();

        $statusOptions = array_map(
            static fn (BackgroundCheckStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            BackgroundCheckStatus::cases(),
        );

        return Inertia::render('background-checks/Index', [
            'rows' => $rows,
            'statusOptions' => $statusOptions,
        ]);
    }

    public function store(StoreBackgroundCheckRequest $request, AuditLogger $audit, CurrentTenant $tenant): RedirectResponse
    {
        $check = BackgroundCheck::create([
            'user_id' => $request->integer('user_id'),
            'provider' => $request->string('provider')->toString(),
            'status' => $request->string('status')->toString(),
            'cleared_through' => $request->input('cleared_through'),
            'notes' => $request->input('notes'),
        ]);

        $audit->log(
            organizationId: $tenant->id(),
            action: 'background_check.created',
            subject: $check,
            payload: ['user_id' => $check->user_id, 'status' => $check->status->value],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Background check recorded.')]);

        return to_route('background-checks.index');
    }

    public function update(UpdateBackgroundCheckRequest $request, BackgroundCheck $backgroundCheck, AuditLogger $audit): RedirectResponse
    {
        $backgroundCheck->update([
            'provider' => $request->string('provider')->toString(),
            'status' => $request->string('status')->toString(),
            'cleared_through' => $request->input('cleared_through'),
            'notes' => $request->input('notes'),
        ]);

        $audit->log(
            organizationId: $backgroundCheck->organization_id,
            action: 'background_check.updated',
            subject: $backgroundCheck,
            payload: ['user_id' => $backgroundCheck->user_id, 'status' => $backgroundCheck->status->value],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Background check updated.')]);

        return to_route('background-checks.index');
    }

    public function destroy(BackgroundCheck $backgroundCheck, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $backgroundCheck);

        $userId = $backgroundCheck->user_id;
        $orgId = $backgroundCheck->organization_id;
        $backgroundCheck->delete();

        $audit->log(
            organizationId: $orgId,
            action: 'background_check.deleted',
            payload: ['user_id' => $userId],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Background check removed.')]);

        return to_route('background-checks.index');
    }
}
