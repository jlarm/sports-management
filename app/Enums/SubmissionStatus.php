<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Processed => 'Processed',
            self::Skipped => 'Skipped',
        };
    }
}
