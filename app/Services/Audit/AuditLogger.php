<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final readonly class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(int $organizationId, string $action, ?Model $subject = null, array $payload = [], ?int $actorUserId = null): AuditLog
    {
        $actor = $actorUserId ?? $this->request->user()?->id;

        return AuditLog::query()->create([
            'organization_id' => $organizationId,
            'actor_user_id' => $actor,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'payload' => $payload === [] ? null : $payload,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 255),
        ]);
    }
}
