<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Enums\FormStatus;

test('FormStatus::label() covers every case', function (FormStatus $status, string $expected) {
    expect($status->label())->toBe($expected);
})->with([
    'draft' => [FormStatus::Draft, 'Draft'],
    'published' => [FormStatus::Published, 'Published'],
    'closed' => [FormStatus::Closed, 'Closed'],
]);

test('FieldType::label() covers every case', function (FieldType $type, string $expected) {
    expect($type->label())->toBe($expected);
})->with([
    'text' => [FieldType::Text, 'Short text'],
    'textarea' => [FieldType::Textarea, 'Long text'],
    'number' => [FieldType::Number, 'Number'],
    'date' => [FieldType::Date, 'Date'],
    'select' => [FieldType::Select, 'Choice'],
    'checkbox' => [FieldType::Checkbox, 'Checkbox'],
]);

test('FieldType::requiresOptions() is true only for select', function () {
    expect(FieldType::Select->requiresOptions())->toBeTrue()
        ->and(FieldType::Text->requiresOptions())->toBeFalse()
        ->and(FieldType::Checkbox->requiresOptions())->toBeFalse();
});
