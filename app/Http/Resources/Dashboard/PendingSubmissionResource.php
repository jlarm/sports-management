<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboard;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Submission
 */
final class PendingSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'form_title' => $this->form?->title,
            'submitted_at' => $this->submitted_at->toIso8601String(),
        ];
    }
}
