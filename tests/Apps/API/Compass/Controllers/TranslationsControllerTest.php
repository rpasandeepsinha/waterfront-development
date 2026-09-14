<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\Factories\TranslationStringFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\TranslationsController;
use Waterfront\Domain\Translations\Enums\TranslationSource;

#[CoversClass(TranslationsController::class)]
class TranslationsControllerTest extends IntegrationTestCase
{
    #[Test]
    public function keyDoesNotExistAndThrowsUnprocessable(): void
    {
        TranslationKeyFactory::new()->createOne(['key' => 'test']);

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.translations.update', [
                    'source' => TranslationSource::COMPASS->value,
                    'key' => 'nonexistent',
                    'translations' => [
                        'nl' => 'asdfasdfa',
                        'en' => 'sdfasdfasfasdf',
                    ],
                ]),
            )
            ->assertUnprocessable();
    }

    #[Test]
    public function keyExistAndUpdatesTranslations(): void
    {
        $key = new TranslationKeyFactory()->createOne(['key' => 'test', 'source' => TranslationSource::COMPASS->value]);

        $english = new TranslationLanguageFactory()->createOne(['locale' => 'en']);
        $dutch = new TranslationLanguageFactory()->createOne(['locale' => 'nl']);
        $duthcTranslation = new TranslationStringFactory()
            ->for($key)
            ->for($dutch, 'language')
            ->createOne(
                ['translated_string' => 'Test translation'],
            );
        $englishTranslation = new TranslationStringFactory()
            ->for($key)
            ->for($english, 'language')
            ->createOne(
                ['translated_string' => 'Test translation'],
            );

        $this->actingAsEmployee()
            ->patchJson(
                $this->generateRoute('admin.translations.update', [
                    'source' => TranslationSource::COMPASS->value,
                    'key' => 'test',
                    'translations' => [
                        'nl' => 'asdfasdfa',
                        'en' => 'sdfasdfasfasdf',
                    ],
                ]),
            )
            ->assertNoContent();

        $englishTranslation->refresh();
        $duthcTranslation->refresh();

        self::assertSame('sdfasdfasfasdf', $englishTranslation->translated_string);
        self::assertSame('asdfasdfa', $duthcTranslation->translated_string);
    }
}
