<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\Season;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Season
 */
final class ActiveSeasonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = CarbonImmutable::today();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days_remaining' => $this->end_date->isBefore($today)
                ? 0
                : (int) $today->diffInDays($this->end_date),
        ];
    }
}
