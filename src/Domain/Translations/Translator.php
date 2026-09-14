<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations;

use Illuminate\Support\Str;
use Illuminate\Translation\Translator as LaravelTranslator;

class Translator extends LaravelTranslator
{
    /**
     * @param string                $key
     * @param mixed[]|array<string> $replace
     * @param string|null           $locale
     * @param bool                  $fallback
     *
     * @return mixed[]|string
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $locale ??= $this->locale;

        // For JSON translations, there is only one file per locale, so we will simply load
        // that file, and then we will be ready to check the array for the key. These are
        // only one level deep, so we do not need to do any fancy searching through it.
        $this->load('*', '*', $locale);

        $line = $this->loaded['*']['*'][$locale][$key]['translation'] ?? null;

        // If we can't find a translation for the JSON key, we will attempt to translate it
        // using the typical translation file. This way developers can always just use a
        // helper such as __ instead of having to pick between trans or __ with views.
        if (! $line) {
            [$namespace, $group, $item] = $this->parseKey($key);

            // Here we will get the locale that should be used for the language line. If one
            // was not passed, we will use the default locales which was given to us when
            // the translator was instantiated. Then, we can load the lines and return.
            $locales = $fallback ? $this->localeArray($locale) : [$locale];

            foreach ($locales as $loopLocale) {
                if (! is_null(
                    $line = $this->getLine(
                        $namespace,
                        $group,
                        $loopLocale,
                        $item,
                        $replace,
                    ),
                )) {
                    return $line;
                }
            }
        }

        // If the line doesn't exist, we will return the key which was requested as
        // that will be quick to spot in the UI if language keys are wrong or missing
        // from the application's language files. Otherwise, we can return the line.
        return $this->makeReplacements($line ?? $key, $replace);
    }

    /**
     * @param string       $line
     * @param array<mixed> $replace
     */
    protected function makeReplacements($line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        $shouldReplace = [];

        /**
         * @var string             $key
         * @var string|array<void> $value
         */
        foreach ($replace as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $shouldReplace[':' . Str::ucfirst($key)] = Str::ucfirst($value);
            $shouldReplace[':' . Str::upper($key)] = Str::upper($value);
            $shouldReplace[':' . $key] = $value;
        }

        return strtr($line, $shouldReplace);
    }
}
