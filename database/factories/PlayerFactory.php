<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BattingHand;
use App\Enums\ThrowingHand;
use App\Models\Organization;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
final class PlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dob = CarbonImmutable::instance(fake()->dateTimeBetween('-18 years', '-6 years'));
        $hsGradYear = $dob->addYears(18)->year;

        return [
            'organization_id' => Organization::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'dob' => $dob->toDateString(),
            'graduation_year' => $hsGradYear,
            'gender' => null,
            'bats' => BattingHand::Right->value,
            'throws' => ThrowingHand::Right->value,
            'school' => null,
            'jersey_size' => null,
            'medical_notes' => null,
            'external_id' => null,
            'notes' => null,
        ];
    }
}
