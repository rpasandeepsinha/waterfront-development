<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Waterfront\Domain\Translations\Models\TranslationKey;

class TranslationKeySeeder extends Seeder
{
    private const array TRANSLATION_FILES = [
        'atlantis.json',
        'audit-log-summary.json',
        'compass.json',
        'coast.json',
        'beacon.json',
        'waterfront-backend.json',
    ];

    public function run(): void
    {
        foreach (self::TRANSLATION_FILES as $file) {
            $this->seedTranslationKeys($file);
        }
    }

    private function seedTranslationKeys(string $file): void
    {
        $keyModels = [];

        $sourceParts = explode('.', $file);

        foreach ($this->getTranslationKeyItems($file) as $key => $translation) {
            assert(is_array($translation));

            $translationKey = new TranslationKey();
            $translationKey->key = $key;
            $translationKey->source = $sourceParts[0];

            $keyModels[] = $translationKey->toArray();
        }

        TranslationKey::insert($keyModels);
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
