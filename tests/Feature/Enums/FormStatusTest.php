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
    'select' => [FieldType::Select, 'Dropdown'],
    'checkboxes' => [FieldType::Checkboxes, 'Checkboxes'],
    'toggle' => [FieldType::Toggle, 'Toggle'],
    'email' => [FieldType::Email, 'Email'],
    'name' => [FieldType::Name, 'Name'],
    'phone' => [FieldType::Phone, 'Phone'],
]);

test('FieldType::requiresOptions() is true for option-bearing types only', function () {
    expect(FieldType::Select->requiresOptions())->toBeTrue()
        ->and(FieldType::Checkboxes->requiresOptions())->toBeTrue()
        ->and(FieldType::Text->requiresOptions())->toBeFalse()
        ->and(FieldType::Date->requiresOptions())->toBeFalse();
});
