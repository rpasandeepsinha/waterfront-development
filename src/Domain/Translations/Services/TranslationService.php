<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Services;

use Waterfront\Domain\Translations\Enums\TranslationSource;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;

class TranslationService
{
    /**
     * @param array<mixed, mixed> $translations
     */
    public function createTranslation(TranslationSource $source, string $translationKey, array $translations): void
    {
        $newTranslationKey = new TranslationKey();
        $newTranslationKey->key = $translationKey;
        $newTranslationKey->source = $source->value;
        $newTranslationKey->save();

        $languageCollection = TranslationLanguage::get();
        foreach ($translations as $locale => $translationString) {
            $language = $languageCollection->where('locale', $locale)->firstOrFail();
            TranslationString::where('key_id', $newTranslationKey->id)
                ->where('language_id', $language->id)
                ->update(['translated_string' => $translationString]);
        }
    }

    /**
     * @param array<mixed, mixed> $translations
     */
    public function updateTranslation(TranslationSource $source, string $translationKey, array $translations): void
    {
        $languageCollection = TranslationLanguage::get();
        foreach ($translations as $locale => $translationString) {
            $translationKeyModel = TranslationKey::where('key', $translationKey)
                ->where('source', $source->value)
                ->first();
            assert($translationKeyModel instanceof TranslationKey);
            $language = $languageCollection->where('locale', $locale)->firstOrFail();
            TranslationString::where('key_id', $translationKeyModel->id)
                ->where('language_id', $language->id)
                ->update(['translated_string' => $translationString]);
        }
    }
}
