<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FieldType;
use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
final class FormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => 'Registration '.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'status' => FormStatus::Draft->value,
            'schema' => [
                'fields' => [
                    [
                        'key' => 'first_name',
                        'label' => 'First name',
                        'type' => FieldType::Text->value,
                        'required' => true,
                    ],
                    [
                        'key' => 'last_name',
                        'label' => 'Last name',
                        'type' => FieldType::Text->value,
                        'required' => true,
                    ],
                ],
            ],
            'schema_version' => 1,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => ['status' => FormStatus::Published->value]);
    }

    public function closed(): self
    {
        return $this->state(fn (): array => ['status' => FormStatus::Closed->value]);
    }
}
