<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property ?string $address
 * @property ?string $maps_link
 */
#[Fillable(['name', 'address', 'maps_link'])]
final class Location extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<LocationFactory> */
    use HasFactory, SoftDeletes;
}
