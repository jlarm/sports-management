<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\FormStatus;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property ?string $description
 * @property FormStatus $status
 * @property array<int, array<string, mixed>> $schema
 * @property int $schema_version
 */
#[Fillable(['title', 'description', 'status', 'schema', 'schema_version'])]
final class Form extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<FormFactory> */
    use HasFactory, SoftDeletes;

    public function isDraft(): bool
    {
        return $this->status === FormStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === FormStatus::Published;
    }

    public function isClosed(): bool
    {
        return $this->status === FormStatus::Closed;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'schema' => 'array',
            'schema_version' => 'integer',
        ];
    }
}
