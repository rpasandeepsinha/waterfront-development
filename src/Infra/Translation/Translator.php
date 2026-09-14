<?php

declare(strict_types=1);

namespace Waterfront\Infra\Translation;

use Illuminate\Contracts\Translation\Translator as LaravelTranslator;

class Translator implements TranslatorInterface
{
    public function __construct(
        private readonly LaravelTranslator $translator,
    ) {
    }

    public function translate(string $key, array $replace = [], ?string $locale = null): string
    {
        $translated = $this->translator->get($key, $replace, $locale);

        if (! is_string($translated)) {
            return $key;
        }

        return $translated;
    }
}
