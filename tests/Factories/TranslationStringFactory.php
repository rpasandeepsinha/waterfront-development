<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Translations\Models\TranslationString;

/**
 * @extends Factory<TranslationString>
 */
class TranslationStringFactory extends Factory
{
    protected $model = TranslationString::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'translated_string' => $this->faker->text(),
        ];
    }
}
