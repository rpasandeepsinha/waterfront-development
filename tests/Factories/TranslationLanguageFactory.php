<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Translations\Models\TranslationLanguage;

/**
 * @extends Factory<TranslationLanguage>
 */
class TranslationLanguageFactory extends Factory
{
    protected $model = TranslationLanguage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => $this->faker->languageCode(),
            'display_name' => $this->faker->country(),
            'active' => true,
            'default' => $this->faker->boolean(10),
        ];
    }
}
