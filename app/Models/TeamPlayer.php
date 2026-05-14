<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $team_id
 * @property int $player_id
 * @property ?int $jersey_number
 * @property ?string $primary_position
 * @property bool $is_captain
 * @property ?Player $player
 * @property ?Team $team
 */
final class TeamPlayer extends Pivot
{
    public $incrementing = true;

    protected $table = 'team_player';

    protected $fillable = [
        'team_id',
        'player_id',
        'jersey_number',
        'primary_position',
        'is_captain',
    ];

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jersey_number' => 'integer',
            'is_captain' => 'boolean',
        ];
    }
}
