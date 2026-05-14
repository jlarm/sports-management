<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $season_id
 * @property int $division_id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['season_id', 'division_id', 'name', 'slug'])]
final class Team extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<TeamFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsToMany<Player, $this, TeamPlayer, 'pivot'>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'team_player')
            ->using(TeamPlayer::class)
            ->withPivot(['id', 'jersey_number', 'primary_position', 'is_captain'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<TeamPlayer, $this>
     */
    public function rosterEntries(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }
}
