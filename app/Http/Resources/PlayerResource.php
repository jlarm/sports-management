<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Player
 */
final class PlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'dob' => $this->dob->toDateString(),
            'graduation_year' => $this->graduation_year,
            'gender' => $this->gender,
            'bats' => $this->bats?->value,
            'throws' => $this->throws?->value,
            'school' => $this->school,
            'jersey_size' => $this->jersey_size,
            'medical_notes' => $this->medical_notes,
            'external_id' => $this->external_id,
            'notes' => $this->notes,
        ];
    }
}
