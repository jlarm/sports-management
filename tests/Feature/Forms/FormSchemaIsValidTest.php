<?php

declare(strict_types=1);

use App\Rules\FormSchemaIsValid;
use Illuminate\Support\Facades\Validator;

function assertSchemaPasses(mixed $schema): void
{
    $validator = Validator::make(['schema' => $schema], ['schema' => [new FormSchemaIsValid]]);
    expect($validator->passes())->toBeTrue();
}

function assertSchemaFailsWith(mixed $schema, string $needle): void
{
    $validator = Validator::make(['schema' => $schema], ['schema' => [new FormSchemaIsValid]]);
    expect($validator->fails())->toBeTrue();

    $messages = implode("\n", $validator->errors()->all());
    expect($messages)->toContain($needle);
}

test('accepts a minimal valid schema with one field', function () {
    assertSchemaPasses([
        'fields' => [
            ['key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'required' => true],
        ],
    ]);
});

test('rejects non-array schema', function () {
    assertSchemaFailsWith('not an array', 'schema object');
});

test('rejects schema with no fields array', function () {
    assertSchemaFailsWith(['title' => 'oops'], 'fields array');
});

test('rejects field that is not an object', function () {
    assertSchemaFailsWith(['fields' => ['not-an-object']], 'index 0 is not an object');
});

test('rejects field with bad key', function () {
    assertSchemaFailsWith(
        ['fields' => [['key' => 'Bad-Key', 'label' => 'X', 'type' => 'text']]],
        'snake_case key',
    );
});

test('rejects duplicate field keys', function () {
    assertSchemaFailsWith(
        [
            'fields' => [
                ['key' => 'dupe', 'label' => 'One', 'type' => 'text'],
                ['key' => 'dupe', 'label' => 'Two', 'type' => 'text'],
            ],
        ],
        "Duplicate field key 'dupe'",
    );
});

test('rejects field with empty label', function () {
    assertSchemaFailsWith(
        ['fields' => [['key' => 'k', 'label' => '   ', 'type' => 'text']]],
        'non-empty label',
    );
});

test('rejects field with unsupported type', function () {
    assertSchemaFailsWith(
        ['fields' => [['key' => 'k', 'label' => 'L', 'type' => 'magic']]],
        'unsupported type',
    );
});

test('rejects select field without options', function () {
    assertSchemaFailsWith(
        ['fields' => [['key' => 'size', 'label' => 'Jersey', 'type' => 'select']]],
        'at least one option',
    );
});

test('rejects select field with empty option', function () {
    assertSchemaFailsWith(
        [
            'fields' => [
                [
                    'key' => 'size',
                    'label' => 'Jersey',
                    'type' => 'select',
                    'options' => ['YS', '  '],
                ],
            ],
        ],
        'empty option entry',
    );
});

test('accepts select with valid options', function () {
    assertSchemaPasses([
        'fields' => [
            [
                'key' => 'size',
                'label' => 'Jersey',
                'type' => 'select',
                'required' => true,
                'options' => ['YS', 'YM', 'YL'],
            ],
        ],
    ]);
});
