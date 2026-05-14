<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
final class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Field '.fake()->unique()->numberBetween(1, 9999),
            'address' => fake()->streetAddress(),
            'maps_link' => null,
        ];
    }
}
