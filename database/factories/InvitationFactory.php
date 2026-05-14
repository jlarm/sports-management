<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrganizationRole::Coach->value,
            'token_hash' => Invitation::hashToken(bin2hex(random_bytes(32))),
            'invited_by_user_id' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => ['accepted_at' => now()]);
    }

    public function declined(): self
    {
        return $this->state(fn (): array => ['declined_at' => now()]);
    }

    public function revoked(): self
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
