<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
final class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'status' => $this->status()->value,
            'expires_at' => $this->expires_at->toIso8601String(),
            'invited_by' => $this->invited_by_user_id !== null
                ? $this->invitedBy?->name
                : null,
        ];
    }
}
