<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\InvitationStatus;
use App\Enums\OrganizationRole;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $email
 * @property OrganizationRole $role
 * @property string $token_hash
 * @property ?int $invited_by_user_id
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $accepted_at
 * @property ?CarbonImmutable $declined_at
 * @property ?CarbonImmutable $revoked_at
 */
#[Fillable(['email', 'role', 'token_hash', 'invited_by_user_id', 'expires_at'])]
final class Invitation extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * Mint a fresh raw token and its hashed form for storage.
     *
     * @return array{raw: string, hash: string}
     */
    public static function mintToken(): array
    {
        $raw = Str::random(64);

        return ['raw' => $raw, 'hash' => self::hashToken($raw)];
    }

    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }

    /**
     * @param  Builder<Invitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public function status(): InvitationStatus
    {
        return match (true) {
            $this->accepted_at !== null => InvitationStatus::Accepted,
            $this->declined_at !== null => InvitationStatus::Declined,
            $this->revoked_at !== null => InvitationStatus::Revoked,
            $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    public function isPending(): bool
    {
        return $this->status() === InvitationStatus::Pending;
    }

    public function matchesToken(string $raw): bool
    {
        return hash_equals($this->token_hash, self::hashToken($raw));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'declined_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
