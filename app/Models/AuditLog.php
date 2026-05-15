<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property ?int $actor_user_id
 * @property string $action
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?array<string, mixed> $payload
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'organization_id',
    'actor_user_id',
    'action',
    'subject_type',
    'subject_id',
    'payload',
    'ip_address',
    'user_agent',
])]
final class AuditLog extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
