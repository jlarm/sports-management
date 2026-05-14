<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
final class DivisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => (string) fake()->unique()->numberBetween(6, 18).'U',
            'display_order' => 0,
        ];
    }
}
