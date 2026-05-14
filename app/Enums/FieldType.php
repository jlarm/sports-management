<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Choice',
            self::Checkbox => 'Checkbox',
        };
    }

    public function requiresOptions(): bool
    {
        return $this === self::Select;
    }
}
