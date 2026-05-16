<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentType: string
{
    case Registration = 'registration';
    case MediaRelease = 'media_release';
    case MedicalTreatment = 'medical_treatment';
    case CodeOfConduct = 'code_of_conduct';
    case Custom = 'custom';

    /**
     * @return array<int, self>
     */
    public static function presets(): array
    {
        return [
            self::Registration,
            self::MediaRelease,
            self::MedicalTreatment,
            self::CodeOfConduct,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration consent',
            self::MediaRelease => 'Media release',
            self::MedicalTreatment => 'Medical treatment authorization',
            self::CodeOfConduct => 'Code of conduct',
            self::Custom => 'Custom consent',
        };
    }
}
