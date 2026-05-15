<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MatchAction;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Models\SubmissionDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionDecision>
 */
final class SubmissionDecisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();
        $form = Form::factory()->for($organization)->create();
        $submission = Submission::factory()->for($organization)->for($form)->create();

        return [
            'organization_id' => $organization->id,
            'submission_id' => $submission->id,
            'decided_by_user_id' => null,
            'player_action' => MatchAction::Skipped,
            'player_id' => null,
            'guardian_action' => MatchAction::Skipped,
            'guardian_id' => null,
            'notes' => null,
            'decided_at' => now(),
        ];
    }
}
