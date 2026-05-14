<?php

declare(strict_types=1);

namespace App\Enums;

enum BattingHand: string
{
    case Right = 'R';
    case Left = 'L';
    case Switch = 'S';
}
