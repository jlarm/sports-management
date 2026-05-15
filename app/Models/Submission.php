<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\SubmissionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $form_id
 * @property ?int $submitted_by_user_id
 * @property array{fields: array<int, array<string, mixed>>} $schema_snapshot
 * @property int $schema_version
 * @property array<string, mixed> $data
 * @property SubmissionStatus $status
 * @property CarbonImmutable $submitted_at
 */
#[Fillable([
    'organization_id',
    'form_id',
    'submitted_by_user_id',
    'schema_snapshot',
    'schema_version',
    'data',
    'status',
    'submitted_at',
])]
final class Submission extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<SubmissionDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(SubmissionDecision::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_snapshot' => 'array',
            'schema_version' => 'integer',
            'data' => 'array',
            'status' => SubmissionStatus::class,
            'submitted_at' => 'immutable_datetime',
        ];
    }
}
