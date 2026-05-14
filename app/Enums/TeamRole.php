<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamRole: string
{
    case HeadCoach = 'head_coach';
    case AssistantCoach = 'assistant_coach';
    case TeamAdmin = 'team_admin';

    public function label(): string
    {
        return match ($this) {
            self::HeadCoach => 'Head coach',
            self::AssistantCoach => 'Assistant coach',
            self::TeamAdmin => 'Team admin',
        };
    }
}
