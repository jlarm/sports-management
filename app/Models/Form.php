<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\ConsentType;
use App\Enums\FormStatus;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property ?string $description
 * @property FormStatus $status
 * @property array{fields: array<int, array<string, mixed>>} $schema
 * @property int $schema_version
 * @property ?array<int, string> $required_consents
 * @property ?array<int, array<string, mixed>> $custom_consents
 */
#[Fillable(['title', 'description', 'status', 'schema', 'schema_version', 'required_consents', 'custom_consents'])]
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
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return array<int, ConsentType>
     */
    public function requiredConsentTypes(): array
    {
        $values = $this->required_consents ?? [];

        return array_values(array_filter(
            array_map(ConsentType::tryFrom(...), $values),
        ));
    }

    /**
     * @return array<int, array{key: string, label: string, text: string}>
     */
    public function customConsents(): array
    {
        $values = $this->custom_consents ?? [];

        return array_values(array_filter(
            $values,
            fn (array $entry): bool => is_string($entry['key'] ?? null)
                && is_string($entry['label'] ?? null)
                && is_string($entry['text'] ?? null)
                && $entry['key'] !== ''
                && $entry['label'] !== ''
                && $entry['text'] !== '',
        ));
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
            'required_consents' => 'array',
            'custom_consents' => 'array',
        ];
    }
}
