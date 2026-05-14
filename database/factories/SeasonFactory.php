<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
final class SeasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', '+1 year');
        $end = (clone $start)->modify('+3 months');
        $terms = ['Spring', 'Summer', 'Fall', 'Winter'];
        $term = $terms[array_rand($terms)];
        $suffix = (string) fake()->unique()->numberBetween(1, 999999);

        return [
            'organization_id' => Organization::factory(),
            'name' => $term.' '.$start->format('Y').' '.$suffix,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'is_active' => false,
            'is_registration_open' => false,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }

    public function registrationOpen(): self
    {
        return $this->state(fn (): array => ['is_registration_open' => true]);
    }
}
