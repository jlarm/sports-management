<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class RecentAuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor_name' => $this->actor?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
