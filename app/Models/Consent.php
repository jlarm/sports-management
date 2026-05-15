<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\ConsentType;
use Carbon\CarbonImmutable;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $submission_id
 * @property ?int $guardian_id
 * @property ?int $player_id
 * @property ConsentType $consent_type
 * @property string $consent_text_snapshot
 * @property int $consent_text_version
 * @property CarbonImmutable $accepted_at
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property ?CarbonImmutable $withdrawn_at
 * @property ?int $withdrawn_by_user_id
 */
#[Fillable([
    'organization_id',
    'submission_id',
    'guardian_id',
    'player_id',
    'consent_type',
    'consent_text_snapshot',
    'consent_text_version',
    'accepted_at',
    'ip_address',
    'user_agent',
    'withdrawn_at',
    'withdrawn_by_user_id',
])]
final class Consent extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_user_id');
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_type' => ConsentType::class,
            'consent_text_version' => 'integer',
            'accepted_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
