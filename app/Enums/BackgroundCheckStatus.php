<?php

declare(strict_types=1);

namespace App\Enums;

enum BackgroundCheckStatus: string
{
    case Pending = 'pending';
    case Cleared = 'cleared';
    case Flagged = 'flagged';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Cleared => 'Cleared',
            self::Flagged => 'Flagged',
            self::Expired => 'Expired',
        };
    }
}
