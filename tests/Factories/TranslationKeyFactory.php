<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;

/**
 * @extends Factory<TranslationKey>
 */
class TranslationKeyFactory extends Factory
{
    protected $model = TranslationKey::class;

    /** @return array<string, string> */
    public function definition(): array
    {
        return [
            'key' => $this->faker->text(30),
            'source' => $this->faker->text(10),
        ];
    }

    public function withTranslatedString(TranslationLanguage $language, string $translatedString): self
    {
        return $this->afterCreating(function (TranslationKey $translationKey) use ($language, $translatedString): void {
            $translationString = $translationKey
                ->translationStrings()
                ->with(['language', 'translationKey'])
                ->where('language_id', $language->id)
                ->firstOrFail();
            $translationString->translated_string = $translatedString;
            $translationString->save();
        });
    }
}
