<?php

declare(strict_types=1);

namespace App\Services\Consents;

use App\Enums\ConsentType;

final class ConsentTextRegistry
{
    /**
     * @return array{text: string, version: int}
     */
    public function entry(ConsentType $type): array
    {
        $text = config('coppa.consents.'.$type->value.'.text');
        $version = config('coppa.consents.'.$type->value.'.version');

        return [
            'text' => is_string($text) ? $text : '',
            'version' => is_int($version) ? $version : 1,
        ];
    }
}
