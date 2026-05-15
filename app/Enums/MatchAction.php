<?php

declare(strict_types=1);

namespace App\Enums;

enum MatchAction: string
{
    case Created = 'created';
    case Merged = 'merged';
    case ForceCreated = 'force_created';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created new',
            self::Merged => 'Merged with existing',
            self::ForceCreated => 'Forced new record',
            self::Skipped => 'Skipped',
        };
    }
}
