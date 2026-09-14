<?php

declare(strict_types=1);

namespace Tests\Domain\Translations\Loaders;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Translations\Loaders\TranslationLoader;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(TranslationLoader::class)]
class TranslationLoaderTest extends IntegrationTestCase
{
    #[Test]
    public function trans(): void
    {
        $language = new TranslationLanguageFactory()->createOne();
        $key = new TranslationKeyFactory()
            ->withTranslatedString($language, 'sometranslation')
            ->createOne();

        $string = $key->translationStrings->firstOrFail();
        $key = $string->translationKey->key;
        $rawTrans = $string->translated_string;
        $rawLocale = $string->language->locale;

        self::assertSame($rawTrans, self::resolve(TranslatorInterface::class)->translate($key, [], $rawLocale));
    }

    #[Test]
    public function transNotFound(): void
    {
        $nonExistingKey = 'NOT.EXISTING.KEY';

        $language = new TranslationLanguageFactory()->createOne();
        $key = new TranslationKeyFactory()
            ->withTranslatedString($language, 'sometranslation')
            ->createOne();

        $string = $key->translationStrings->firstOrFail();
        $rawLocale = $string->language->locale;

        self::assertSame($nonExistingKey, self::resolve(TranslatorInterface::class)
            ->translate($nonExistingKey, [], $rawLocale));
    }

    #[Test]
    public function loadLocale(): void
    {
        $language = new TranslationLanguageFactory()->createOne();
        $key = new TranslationKeyFactory()
            ->withTranslatedString($language, 'sometranslation')
            ->createOne();

        $string = $key->translationStrings->firstOrFail();

        $source = $string->translationKey->source;
        $rawLocale = $string->language->locale;

        self::assertTrue(Cache::missing(TranslationLoader::getCacheKey($rawLocale, $source)));
        self::resolve(TranslationLoader::class)->load($rawLocale, $source);
        self::assertTrue(Cache::has(TranslationLoader::getCacheKey($rawLocale, $source)));
    }

    #[Test]
    public function loadGroup(): void
    {
        $language = new TranslationLanguageFactory()->createOne();
        $key = new TranslationKeyFactory()
            ->withTranslatedString($language, 'sometranslation')
            ->createOne();
        $string = $key->translationStrings->firstOrFail();

        $source = $string->translationKey->source;
        $rawLocale = $string->language->locale;

        self::assertTrue(Cache::missing(TranslationLoader::getCacheKey($rawLocale, $source)));
        self::resolve(TranslationLoader::class)->load($rawLocale, $source);
        self::assertTrue(Cache::has(TranslationLoader::getCacheKey($rawLocale, $source)));
    }
}
