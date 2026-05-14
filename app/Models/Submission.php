<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $form_id
 * @property ?int $submitted_by_user_id
 * @property array{fields: array<int, array<string, mixed>>} $schema_snapshot
 * @property int $schema_version
 * @property array<string, mixed> $data
 * @property CarbonImmutable $submitted_at
 */
#[Fillable([
    'organization_id',
    'form_id',
    'submitted_by_user_id',
    'schema_snapshot',
    'schema_version',
    'data',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_snapshot' => 'array',
            'schema_version' => 'integer',
            'data' => 'array',
            'submitted_at' => 'immutable_datetime',
        ];
    }
}
