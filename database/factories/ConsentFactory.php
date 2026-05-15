<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConsentType;
use App\Models\Consent;
use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use App\Services\Consents\ConsentTextRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
final class ConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();
        $form = Form::factory()->for($organization)->create();
        $submission = Submission::factory()->for($organization)->for($form)->create();
        $type = ConsentType::Registration;
        $entry = app(ConsentTextRegistry::class)->entry($type);

        return [
            'organization_id' => $organization->id,
            'submission_id' => $submission->id,
            'guardian_id' => null,
            'player_id' => null,
            'consent_type' => $type,
            'consent_text_snapshot' => $entry['text'],
            'consent_text_version' => $entry['version'],
            'accepted_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'withdrawn_at' => null,
            'withdrawn_by_user_id' => null,
        ];
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => ['withdrawn_at' => now()]);
    }
}
