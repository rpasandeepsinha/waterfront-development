<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;

class TranslationStringSeeder extends Seeder
{
    private const array TRANSLATION_FILES = [
        'atlantis' => 'atlantis.json',
        'compass' => 'compass.json',
        'audit-log-summary' => 'audit-log-summary.json',
        'coast' => 'coast.json',
        'beacon' => 'beacon.json',
        'waterfront-backend' => 'waterfront-backend.json',
    ];

    public function run(): void
    {
        $localeIds  = TranslationLanguage::pluck('id', 'locale');

        $translationKeysMap = $this->getTranslationKeysMap();

        foreach (self::TRANSLATION_FILES as $source => $file) {
            $translationStrings = [];

            foreach ($this->getTranslationKeyItems($file) as $key => $item) {
                assert(is_array($item));

                if (! array_key_exists('locales', $item)) {
                    continue;
                }

                $translationKeyId = $translationKeysMap[$source][$key];

                $translations = Arr::get($item, 'locales', []);
                assert(is_array($translations));

                foreach ($translations as $locale => $translation) {
                    $translationModel = new TranslationString();

                    $translatedString = Arr::get($translation, 'translated_string');
                    assert(is_string($translatedString) || is_null($translatedString));

                    $languageId = Arr::get($localeIds, $locale);
                    assert(is_string($languageId) || is_int($languageId));

                    $translationModel->language_id       = intval($languageId);
                    $translationModel->key_id            = $translationKeyId;
                    $translationModel->translated_string = $translatedString;

                    $translationStrings[] = $translationModel->toArray();
                }
            }

            TranslationString::insert($translationStrings);
        }
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function getTranslationKeysMap(): array
    {
        $translationKeys = TranslationKey::query()->lazy();

        $translationKeysMapped = [];

        foreach ($translationKeys as $translationKey) {
            $translationKeysMapped[$translationKey->source][$translationKey->key] = $translationKey->id;
        }

        return $translationKeysMapped;
    }

    /**
     * @return array<mixed>
     */
    private function getTranslationKeyItems(string $file): array
    {
        if (Storage::disk('translations')->missing($file)) {
            return [];
        }

        $fileContent = Storage::disk('translations')->get($file);
        assert(is_string($fileContent));
        $translations = json_decode($fileContent, true);
        assert(is_array($translations));

        return $translations;
    }
}
