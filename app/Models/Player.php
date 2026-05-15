<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\BattingHand;
use App\Enums\ThrowingHand;
use Carbon\CarbonImmutable;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $first_name
 * @property string $last_name
 * @property CarbonImmutable $dob
 * @property ?int $graduation_year
 * @property ?string $gender
 * @property ?BattingHand $bats
 * @property ?ThrowingHand $throws
 * @property ?string $school
 * @property ?string $jersey_size
 * @property ?string $medical_notes
 * @property ?string $external_id
 * @property ?string $notes
 */
#[Fillable([
    'first_name',
    'last_name',
    'dob',
    'graduation_year',
    'gender',
    'bats',
    'throws',
    'school',
    'jersey_size',
    'medical_notes',
    'external_id',
    'notes',
])]
final class Player extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<PlayerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsToMany<Team, $this, TeamPlayer, 'pivot'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_player')
            ->using(TeamPlayer::class)
            ->withPivot(['id', 'jersey_number', 'primary_position', 'is_captain'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Guardian, $this, PlayerGuardian, 'pivot'>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'player_guardian')
            ->using(PlayerGuardian::class)
            ->withPivot(['id', 'relationship', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'immutable_date',
            'graduation_year' => 'integer',
            'bats' => BattingHand::class,
            'throws' => ThrowingHand::class,
        ];
    }
}
