<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $display_order
 */
#[Fillable(['name', 'display_order'])]
final class Division extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<DivisionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }
}
