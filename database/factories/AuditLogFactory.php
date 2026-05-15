<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
final class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'actor_user_id' => null,
            'action' => 'test.event',
            'subject_type' => null,
            'subject_id' => null,
            'payload' => null,
            'ip_address' => null,
            'user_agent' => null,
        ];
    }
}
