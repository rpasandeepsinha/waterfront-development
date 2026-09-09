<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Observers;

use Waterfront\Domain\Translations\Loaders\TranslationLoader;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;

class TranslationKeyObserver
{
    public function __construct(private readonly TranslationLoader $loader)
    {
    }

    public function created(TranslationKey $key): void
    {
        $languages = TranslationLanguage::all();

        foreach ($languages as $lang) {
            if (! TranslationString::where([['language_id', '=', $lang->id], ['key_id', '=', $key->id]])->exists()) {
                $translationString = new TranslationString();
                $translationString->language_id = $lang->id;
                $translationString->key_id = $key->id;
                $translationString->save();
            }

            $this->loader->clearCache($lang->locale, $key->source);
        }
    }
}
