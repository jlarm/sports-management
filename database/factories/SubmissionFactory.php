<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
final class SubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();
        $form = Form::factory()->for($organization)->create();

        return [
            'organization_id' => $organization->id,
            'form_id' => $form->id,
            'submitted_by_user_id' => null,
            'schema_snapshot' => $form->schema,
            'schema_version' => $form->schema_version,
            'data' => ['first_name' => 'Anonymous', 'last_name' => 'Submitter'],
            'submitted_at' => now(),
        ];
    }
}
