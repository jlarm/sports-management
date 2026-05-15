<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class BlockedCoachResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $status = $user->getAttribute('latest_check_status');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => is_string($status) ? $status : null,
        ];
    }
}
