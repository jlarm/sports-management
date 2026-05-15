<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentType: string
{
    case Registration = 'registration';
    case MediaRelease = 'media_release';
    case MedicalTreatment = 'medical_treatment';
    case CodeOfConduct = 'code_of_conduct';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration consent',
            self::MediaRelease => 'Media release',
            self::MedicalTreatment => 'Medical treatment authorization',
            self::CodeOfConduct => 'Code of conduct',
        };
    }
}
