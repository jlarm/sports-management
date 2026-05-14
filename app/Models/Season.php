<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property bool $is_active
 * @property bool $is_registration_open
 */
#[Fillable(['name', 'start_date', 'end_date', 'is_active', 'is_registration_open'])]
final class Season extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<SeasonFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'is_active' => 'boolean',
            'is_registration_open' => 'boolean',
        ];
    }
}
