<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\BackgroundCheckStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BackgroundCheckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property string $provider
 * @property BackgroundCheckStatus $status
 * @property ?CarbonImmutable $cleared_through
 * @property ?string $notes
 */
#[Fillable([
    'user_id',
    'provider',
    'status',
    'cleared_through',
    'notes',
])]
final class BackgroundCheck extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<BackgroundCheckFactory> */
    use HasFactory;

    protected $table = 'coach_background_checks';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        if ($this->status !== BackgroundCheckStatus::Cleared) {
            return false;
        }
        if ($this->cleared_through === null) {
            return true;
        }
        if ($this->cleared_through->endOfDay()->isFuture()) {
            return true;
        }

        return $this->cleared_through->isToday();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackgroundCheckStatus::class,
            'cleared_through' => 'immutable_date',
        ];
    }
}
