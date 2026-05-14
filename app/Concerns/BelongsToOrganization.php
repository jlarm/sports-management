<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applies tenant scoping and auto-fills organization_id on create.
 *
 * `boot()` is called automatically by Eloquent when this trait is used on a model.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            if ($model->organization_id === null) {
                $model->organization_id = app(CurrentTenant::class)->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
