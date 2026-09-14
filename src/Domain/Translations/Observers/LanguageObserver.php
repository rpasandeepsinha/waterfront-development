<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Observers;

use Waterfront\Domain\Translations\Models\TranslationLanguage;

class LanguageObserver
{
    public function created(TranslationLanguage $language): void
    {
        $this->onlyOneDefaultLanguage($language);
    }

    public function updated(TranslationLanguage $language): void
    {
        $this->onlyOneDefaultLanguage($language);
    }

    private function onlyOneDefaultLanguage(TranslationLanguage $language): void
    {
        if ($language->default === true) {
            TranslationLanguage::where('id', '!=', $language->id)->update(['default' => 0]);
        }
    }
}
