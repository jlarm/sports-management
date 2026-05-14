<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Division;
use App\Models\Organization;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();
        $word = (string) fake()->unique()->word();
        $colors = ['Red', 'Blue', 'Gold', 'Black'];
        $color = $colors[array_rand($colors)];
        $name = $word.' '.$color;

        return [
            'organization_id' => $organization->id,
            'season_id' => Season::factory()->for($organization),
            'division_id' => Division::factory()->for($organization),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
        ];
    }
}
