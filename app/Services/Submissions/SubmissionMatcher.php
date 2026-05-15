<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Models\Guardian;
use App\Models\Player;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

final class SubmissionMatcher
{
    public function match(Submission $submission): MatchResult
    {
        $data = $submission->data;
        $extractedPlayer = $this->extractPlayer($data);
        $extractedGuardian = $this->extractGuardian($data);

        $playerCandidates = $this->findPlayerCandidates(
            $submission->organization_id,
            $extractedPlayer['last_name'],
            $extractedPlayer['dob'],
        );

        $guardianCandidates = $this->findGuardianCandidates(
            $submission->organization_id,
            $extractedGuardian['email'],
        );

        return new MatchResult(
            $extractedPlayer,
            $playerCandidates,
            $extractedGuardian,
            $guardianCandidates,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{first_name: ?string, last_name: ?string, dob: ?string, jersey_size: ?string, medical_notes: ?string}
     */
    private function extractPlayer(array $data): array
    {
        return [
            'first_name' => $this->stringOrNull($data['first_name'] ?? null),
            'last_name' => $this->stringOrNull($data['last_name'] ?? null),
            'dob' => $this->normalizeDate($data['dob'] ?? null),
            'jersey_size' => $this->stringOrNull($data['jersey_size'] ?? null),
            'medical_notes' => $this->stringOrNull($data['allergies'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{first_name: ?string, last_name: ?string, email: ?string, phone: ?string}
     */
    private function extractGuardian(array $data): array
    {
        return [
            'first_name' => $this->stringOrNull($data['parent_first_name'] ?? null),
            'last_name' => $this->stringOrNull($data['parent_last_name'] ?? null),
            'email' => $this->normalizeEmail($data['parent_email'] ?? null),
            'phone' => $this->stringOrNull($data['parent_phone'] ?? null),
        ];
    }

    /**
     * @return Collection<int, Player>
     */
    private function findPlayerCandidates(int $organizationId, ?string $lastName, ?string $dob): Collection
    {
        if ($lastName === null || $lastName === '' || $dob === null || $dob === '') {
            /** @var Collection<int, Player> */
            return collect();
        }

        /** @var Collection<int, Player> $results */
        $results = Player::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
            ->whereDate('dob', $dob)
            ->orderBy('id')
            ->get();

        return $results;
    }

    /**
     * @return Collection<int, Guardian>
     */
    private function findGuardianCandidates(int $organizationId, ?string $email): Collection
    {
        if ($email === null || $email === '') {
            /** @var Collection<int, Guardian> */
            return collect();
        }

        /** @var Collection<int, Guardian> $results */
        $results = Guardian::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->orderBy('id')
            ->get();

        return $results;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = $this->stringOrNull($value);
        if ($raw === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $raw = $this->stringOrNull($value);

        return $raw === null ? null : mb_strtolower($raw);
    }
}
