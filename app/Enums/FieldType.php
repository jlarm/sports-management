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
    case Checkboxes = 'checkboxes';
    case Toggle = 'toggle';
    case Email = 'email';
    case Name = 'name';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Short text',
            self::Textarea => 'Long text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Dropdown',
            self::Checkboxes => 'Checkboxes',
            self::Toggle => 'Toggle',
            self::Email => 'Email',
            self::Name => 'Name',
            self::Phone => 'Phone',
        };
    }

    public function requiresOptions(): bool
    {
        return $this === self::Select || $this === self::Checkboxes;
    }
}
