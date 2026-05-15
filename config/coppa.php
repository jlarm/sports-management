<?php

declare(strict_types=1);

use App\Enums\ConsentType;

return [

    'consents' => [
        ConsentType::Registration->value => [
            'version' => 1,
            'text' => 'I am the parent or legal guardian of the named participant. '
                .'I authorize the participant to register for this program and acknowledge '
                .'that I have read the league rules and league waiver.',
        ],
        ConsentType::MediaRelease->value => [
            'version' => 1,
            'text' => 'I consent to the use of photographs and video of the participant '
                .'captured during program activities for promotional and informational '
                .'purposes by the organization. I may withdraw this consent at any time.',
        ],
        ConsentType::MedicalTreatment->value => [
            'version' => 1,
            'text' => 'In the event of injury or illness, I authorize the organization and '
                .'its coaches to seek emergency medical care for the participant and to '
                .'transport the participant to a medical facility if I cannot be reached.',
        ],
        ConsentType::CodeOfConduct->value => [
            'version' => 1,
            'text' => 'I have read and agree to the participant code of conduct and the '
                .'spectator code of conduct. I understand that violations may result in '
                .'removal from the program.',
        ],
    ],

];
