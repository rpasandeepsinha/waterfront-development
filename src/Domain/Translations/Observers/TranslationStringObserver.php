<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Observers;

use Waterfront\Domain\Translations\Loaders\TranslationLoader;
use Waterfront\Domain\Translations\Models\TranslationString;

class TranslationStringObserver
{
    public function __construct(
        private readonly TranslationLoader $loader,
    ) {
    }

    public function updated(TranslationString $translationString): void
    {
        $this->loader->clearCache($translationString->language->locale, $translationString->translationKey->source);
    }
}
