<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Organization;
use App\Tenancy\CurrentTenant;
use App\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
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

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute(
                    'organization_id',
                    app(CurrentTenant::class)->id(),
                );
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
