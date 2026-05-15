<?php

declare(strict_types=1);

namespace App\Services\Submissions;

use App\Models\Guardian;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * @phpstan-type ExtractedPlayer array{
 *     first_name: ?string,
 *     last_name: ?string,
 *     dob: ?string,
 *     jersey_size: ?string,
 *     medical_notes: ?string
 * }
 * @phpstan-type ExtractedGuardian array{
 *     first_name: ?string,
 *     last_name: ?string,
 *     email: ?string,
 *     phone: ?string
 * }
 */
final readonly class MatchResult
{
    /**
     * @param  ExtractedPlayer  $extractedPlayer
     * @param  Collection<int, Player>  $playerCandidates
     * @param  ExtractedGuardian  $extractedGuardian
     * @param  Collection<int, Guardian>  $guardianCandidates
     */
    public function __construct(
        public array $extractedPlayer,
        public Collection $playerCandidates,
        public array $extractedGuardian,
        public Collection $guardianCandidates,
    ) {}

    public function canMatchPlayer(): bool
    {
        return is_string($this->extractedPlayer['last_name'])
            && $this->extractedPlayer['last_name'] !== ''
            && is_string($this->extractedPlayer['dob'])
            && $this->extractedPlayer['dob'] !== '';
    }

    public function canMatchGuardian(): bool
    {
        return is_string($this->extractedGuardian['email'])
            && $this->extractedGuardian['email'] !== '';
    }
}
