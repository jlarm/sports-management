<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Database\Factories\GuardianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property ?int $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property ?string $phone
 */
#[Fillable([
    'user_id',
    'first_name',
    'last_name',
    'email',
    'phone',
])]
final class Guardian extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<GuardianFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Player, $this, PlayerGuardian, 'pivot'>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_guardian')
            ->using(PlayerGuardian::class)
            ->withPivot(['id', 'relationship', 'is_primary'])
            ->withTimestamps();
    }
}
