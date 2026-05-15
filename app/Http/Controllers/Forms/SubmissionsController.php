<?php

declare(strict_types=1);

namespace App\Http\Controllers\Forms;

use App\Enums\MatchAction;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submissions\ProcessSubmissionRequest;
use App\Models\Consent;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\Player;
use App\Models\Submission;
use App\Models\SubmissionDecision;
use App\Services\Audit\AuditLogger;
use App\Services\Submissions\MatchResult;
use App\Services\Submissions\SubmissionMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SubmissionsController extends Controller
{
    public function index(Form $form): Response
    {
        $this->authorize('viewSubmissions', $form);

        $submissions = Submission::query()
            ->where('form_id', $form->id)
            ->with('submittedBy:id,name,email')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Submission $submission): array => [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'schema_version' => $submission->schema_version,
                'status' => $submission->status->value,
                'status_label' => $submission->status->label(),
                'submitted_by' => $submission->submittedBy !== null
                    ? [
                        'name' => $submission->submittedBy->name,
                        'email' => $submission->submittedBy->email,
                    ]
                    : null,
            ])
            ->all();

        return Inertia::render('forms/submissions/Index', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'schema_version' => $form->schema_version,
            ],
            'submissions' => $submissions,
        ]);
    }

    public function show(Form $form, Submission $submission): Response
    {
        $this->authorize('viewSubmissions', $form);
        $this->authorize('view', $submission);

        abort_unless($submission->form_id === $form->id, 404);

        $submission->loadMissing([
            'submittedBy',
            'decisions.decidedBy',
            'decisions.player',
            'decisions.guardian',
            'consents.withdrawnBy',
        ]);

        return Inertia::render('forms/submissions/Show', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'schema_version' => $form->schema_version,
            ],
            'submission' => [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'schema_version' => $submission->schema_version,
                'is_outdated' => $submission->schema_version !== $form->schema_version,
                'status' => $submission->status->value,
                'status_label' => $submission->status->label(),
                'schema_snapshot' => $submission->schema_snapshot,
                'data' => $submission->data,
                'submitted_by' => $submission->submittedBy !== null
                    ? [
                        'name' => $submission->submittedBy->name,
                        'email' => $submission->submittedBy->email,
                    ]
                    : null,
                'consents' => $this->consentsPayload($submission),
                'decisions' => $submission->decisions
                    ->sortByDesc('decided_at')
                    ->values()
                    ->map(fn (SubmissionDecision $decision): array => [
                        'id' => $decision->id,
                        'decided_at' => $decision->decided_at->toIso8601String(),
                        'player_action' => $decision->player_action->value,
                        'player_action_label' => $decision->player_action->label(),
                        'player' => $decision->player !== null
                            ? ['id' => $decision->player->id, 'first_name' => $decision->player->first_name, 'last_name' => $decision->player->last_name]
                            : null,
                        'guardian_action' => $decision->guardian_action->value,
                        'guardian_action_label' => $decision->guardian_action->label(),
                        'guardian' => $decision->guardian !== null
                            ? ['id' => $decision->guardian->id, 'first_name' => $decision->guardian->first_name, 'last_name' => $decision->guardian->last_name, 'email' => $decision->guardian->email]
                            : null,
                        'notes' => $decision->notes,
                        'decided_by' => $decision->decidedBy !== null
                            ? ['name' => $decision->decidedBy->name]
                            : null,
                    ])
                    ->all(),
            ],
        ]);
    }

    public function review(Form $form, Submission $submission, SubmissionMatcher $matcher): Response
    {
        $this->authorize('processSubmissions', $form);
        $this->authorize('view', $submission);

        abort_unless($submission->form_id === $form->id, 404);

        $result = $matcher->match($submission);
        $submission->loadMissing('consents');

        return Inertia::render('forms/submissions/Review', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
            ],
            'submission' => [
                'id' => $submission->id,
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'status' => $submission->status->value,
                'status_label' => $submission->status->label(),
                'data' => $submission->data,
                'schema_snapshot' => $submission->schema_snapshot,
                'consents' => $this->consentsPayload($submission),
            ],
            'match' => $this->matchPayload($result),
        ]);
    }

    public function process(ProcessSubmissionRequest $request, Form $form, Submission $submission, AuditLogger $audit): RedirectResponse
    {
        abort_unless($submission->form_id === $form->id, 404);

        $playerAction = $request->playerAction();
        $guardianAction = $request->guardianAction();

        /** @var array{first_name?: ?string, last_name?: ?string, dob?: ?string, jersey_size?: ?string, medical_notes?: ?string}|null $playerInput */
        $playerInput = $request->input('player');
        $playerInput ??= [];

        /** @var array{first_name?: ?string, last_name?: ?string, email?: ?string, phone?: ?string}|null $guardianInput */
        $guardianInput = $request->input('guardian');
        $guardianInput ??= [];

        $decidedById = $request->user()?->id;
        $orgId = $submission->organization_id;

        DB::transaction(function () use (
            $submission,
            $playerAction,
            $guardianAction,
            $playerInput,
            $guardianInput,
            $request,
            $decidedById,
            $orgId,
            $audit,
        ): void {
            $playerId = $this->resolvePlayer($playerAction, $playerInput, $request->integer('player_id'), $orgId);
            $guardianId = $this->resolveGuardian($guardianAction, $guardianInput, $request->integer('guardian_id'), $orgId);

            if ($playerId !== null && $guardianId !== null) {
                $player = Player::query()->withoutGlobalScopes()->findOrFail($playerId);
                $player->guardians()->syncWithoutDetaching([$guardianId]);
            }

            if ($playerId !== null || $guardianId !== null) {
                Consent::query()->withoutGlobalScopes()
                    ->where('organization_id', $orgId)
                    ->where('submission_id', $submission->id)
                    ->update([
                        'player_id' => $playerId,
                        'guardian_id' => $guardianId,
                    ]);
            }

            $decision = SubmissionDecision::query()->create([
                'organization_id' => $orgId,
                'submission_id' => $submission->id,
                'decided_by_user_id' => $decidedById,
                'player_action' => $playerAction,
                'player_id' => $playerId,
                'guardian_action' => $guardianAction,
                'guardian_id' => $guardianId,
                'notes' => $request->input('notes'),
                'decided_at' => now(),
            ]);

            $submission->forceFill([
                'status' => $playerAction === MatchAction::Skipped && $guardianAction === MatchAction::Skipped
                    ? SubmissionStatus::Skipped
                    : SubmissionStatus::Processed,
            ])->save();

            $audit->log(
                organizationId: $orgId,
                action: 'submission.processed',
                subject: $submission,
                payload: [
                    'decision_id' => $decision->id,
                    'player_action' => $playerAction->value,
                    'guardian_action' => $guardianAction->value,
                    'player_id' => $playerId,
                    'guardian_id' => $guardianId,
                ],
            );
        });

        return to_route('forms.submissions.show', [$form, $submission]);
    }

    public function withdrawConsent(Form $form, Submission $submission, Consent $consent, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('withdraw', $consent);

        abort_unless($submission->form_id === $form->id, 404);
        abort_unless($consent->submission_id === $submission->id, 404);

        $consent->forceFill([
            'withdrawn_at' => now(),
            'withdrawn_by_user_id' => request()->user()?->id,
        ])->save();

        $audit->log(
            organizationId: $consent->organization_id,
            action: 'consent.withdrawn',
            subject: $consent,
            payload: ['consent_type' => $consent->consent_type->value, 'submission_id' => $submission->id],
        );

        return to_route('forms.submissions.show', [$form, $submission]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function consentsPayload(Submission $submission): array
    {
        return $submission->consents
            ->sortBy('consent_type')
            ->values()
            ->map(fn (Consent $consent): array => [
                'id' => $consent->id,
                'type' => $consent->consent_type->value,
                'type_label' => $consent->consent_type->label(),
                'version' => $consent->consent_text_version,
                'accepted_at' => $consent->accepted_at->toIso8601String(),
                'text_snapshot' => $consent->consent_text_snapshot,
                'is_withdrawn' => $consent->isWithdrawn(),
                'withdrawn_at' => $consent->withdrawn_at?->toIso8601String(),
                'withdrawn_by' => $consent->relationLoaded('withdrawnBy') && $consent->withdrawnBy !== null
                    ? ['name' => $consent->withdrawnBy->name]
                    : null,
            ])
            ->all();
    }

    /**
     * @param  array{first_name?: ?string, last_name?: ?string, dob?: ?string, jersey_size?: ?string, medical_notes?: ?string}  $input
     */
    private function resolvePlayer(MatchAction $action, array $input, int $existingId, int $orgId): ?int
    {
        if ($action === MatchAction::Skipped) {
            return null;
        }

        if ($action === MatchAction::Merged) {
            abort_if($existingId === 0, 422);

            $player = Player::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->find($existingId);
            abort_if($player === null, 422);

            $player->forceFill(array_filter([
                'jersey_size' => $input['jersey_size'] ?? null,
                'medical_notes' => $input['medical_notes'] ?? null,
            ], fn (mixed $v): bool => $v !== null && $v !== ''))->save();

            return $player->id;
        }

        $firstName = $input['first_name'] ?? null;
        $lastName = $input['last_name'] ?? null;
        $dob = $input['dob'] ?? null;
        abort_if(! is_string($firstName) || $firstName === '', 422);
        abort_if(! is_string($lastName) || $lastName === '', 422);
        abort_if(! is_string($dob) || $dob === '', 422);

        $player = new Player;
        $player->forceFill([
            'organization_id' => $orgId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'dob' => $dob,
            'jersey_size' => $input['jersey_size'] ?? null,
            'medical_notes' => $input['medical_notes'] ?? null,
        ])->save();

        return $player->id;
    }

    /**
     * @param  array{first_name?: ?string, last_name?: ?string, email?: ?string, phone?: ?string}  $input
     */
    private function resolveGuardian(MatchAction $action, array $input, int $existingId, int $orgId): ?int
    {
        if ($action === MatchAction::Skipped) {
            return null;
        }

        if ($action === MatchAction::Merged) {
            abort_if($existingId === 0, 422);

            $guardian = Guardian::query()->withoutGlobalScopes()
                ->where('organization_id', $orgId)
                ->find($existingId);
            abort_if($guardian === null, 422);

            $guardian->forceFill(array_filter([
                'phone' => $input['phone'] ?? null,
            ], fn (mixed $v): bool => $v !== null && $v !== ''))->save();

            return $guardian->id;
        }

        $firstName = $input['first_name'] ?? null;
        $lastName = $input['last_name'] ?? null;
        $email = $input['email'] ?? null;
        abort_if(! is_string($firstName) || $firstName === '', 422);
        abort_if(! is_string($lastName) || $lastName === '', 422);
        abort_if(! is_string($email) || $email === '', 422);

        $guardian = new Guardian;
        $guardian->forceFill([
            'organization_id' => $orgId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $input['phone'] ?? null,
        ])->save();

        return $guardian->id;
    }

    /**
     * @return array{
     *     can_match_player: bool,
     *     can_match_guardian: bool,
     *     player: array{extracted: array<string, ?string>, candidates: array<int, array<string, mixed>>},
     *     guardian: array{extracted: array<string, ?string>, candidates: array<int, array<string, mixed>>}
     * }
     */
    private function matchPayload(MatchResult $result): array
    {
        return [
            'can_match_player' => $result->canMatchPlayer(),
            'can_match_guardian' => $result->canMatchGuardian(),
            'player' => [
                'extracted' => $result->extractedPlayer,
                'candidates' => $result->playerCandidates
                    ->map(fn (Player $player): array => [
                        'id' => $player->id,
                        'first_name' => $player->first_name,
                        'last_name' => $player->last_name,
                        'dob' => $player->dob->toDateString(),
                        'jersey_size' => $player->jersey_size,
                    ])
                    ->all(),
            ],
            'guardian' => [
                'extracted' => $result->extractedGuardian,
                'candidates' => $result->guardianCandidates
                    ->map(fn (Guardian $guardian): array => [
                        'id' => $guardian->id,
                        'first_name' => $guardian->first_name,
                        'last_name' => $guardian->last_name,
                        'email' => $guardian->email,
                        'phone' => $guardian->phone,
                    ])
                    ->all(),
            ],
        ];
    }
}
