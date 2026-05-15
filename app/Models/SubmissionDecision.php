<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\MatchAction;
use Carbon\CarbonImmutable;
use Database\Factories\SubmissionDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $submission_id
 * @property ?int $decided_by_user_id
 * @property MatchAction $player_action
 * @property ?int $player_id
 * @property MatchAction $guardian_action
 * @property ?int $guardian_id
 * @property ?string $notes
 * @property CarbonImmutable $decided_at
 */
#[Fillable([
    'submission_id',
    'decided_by_user_id',
    'player_action',
    'player_id',
    'guardian_action',
    'guardian_id',
    'notes',
    'decided_at',
])]
final class SubmissionDecision extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SubmissionDecisionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

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
            'player_action' => MatchAction::class,
            'guardian_action' => MatchAction::class,
            'decided_at' => 'immutable_datetime',
        ];
    }
}
