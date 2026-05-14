<?php

declare(strict_types=1);

namespace Tests\Fixtures\Models;

use App\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

final class TenantWidget extends Model
{
    use BelongsToOrganization;

    protected $table = 'tenant_widgets';

    protected $guarded = [];
}
