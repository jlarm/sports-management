<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $player_id
 * @property int $guardian_id
 * @property ?string $relationship
 * @property bool $is_primary
 */
final class PlayerGuardian extends Pivot
{
    public $incrementing = true;

    protected $table = 'player_guardian';

    protected $fillable = [
        'player_id',
        'guardian_id',
        'relationship',
        'is_primary',
    ];

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
