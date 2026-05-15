<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BackgroundCheckStatus;
use App\Models\BackgroundCheck;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackgroundCheck>
 */
final class BackgroundCheckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'provider' => 'NCSI',
            'status' => BackgroundCheckStatus::Cleared,
            'cleared_through' => now()->addYear()->toDateString(),
            'notes' => null,
        ];
    }

    public function cleared(): static
    {
        return $this->state(fn (): array => [
            'status' => BackgroundCheckStatus::Cleared,
            'cleared_through' => now()->addYear()->toDateString(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => BackgroundCheckStatus::Pending,
            'cleared_through' => null,
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (): array => [
            'status' => BackgroundCheckStatus::Flagged,
            'cleared_through' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => BackgroundCheckStatus::Cleared,
            'cleared_through' => now()->subDay()->toDateString(),
        ]);
    }
}
