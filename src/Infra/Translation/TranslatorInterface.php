<?php

declare(strict_types=1);

namespace Waterfront\Infra\Translation;

interface TranslatorInterface
{
    /**
     * Gets the translation for the given key. If no translation is found, the key name is returned.
     *
     * @param array<string, int|string> $replace
     *
     */
    public function translate(string $key, array $replace = [], ?string $locale = null): string;
}
