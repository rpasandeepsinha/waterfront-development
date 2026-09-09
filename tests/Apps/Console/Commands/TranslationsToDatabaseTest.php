<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Translations\TranslationsToDatabase;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationString;

#[CoversClass(TranslationsToDatabase::class)]
class TranslationsToDatabaseTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $languageNL = new TranslationLanguageFactory()->createOne([
            'display_name' => 'Nederlands',
            'locale' => 'nl',
            'active' => true,
            'default' => true,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        new TranslationKeyFactory()
            ->withTranslatedString($languageNL, 'lorum')
            ->create([
                'key' => 'test.lorum',
                'source' => 'waterfront-backend',
            ]);
    }

    #[Test]
    public function findOrCreateTranslationsNoChangesToExistingTranslation(): void
    {
        TranslationKey::firstOrFail();
        $firstString = TranslationString::firstOrFail();

        $this->artisan(
            TranslationsToDatabase::class,
            [
                'atlantis' => 'atlantis-test.json',
                'audit-log-summary' => 'audit-log-summary.json',
                'beacon' => 'beacon-test.json',
                'coast' => 'coast-test.json',
                'compass' => 'compass-test.json',
                'waterfront-backend' => 'waterfront-backend-test.json',
            ]
        );

        $firstString->refresh();

        $initialTranslationString = $firstString->translated_string;
        $freshTranslationString = $firstString->translated_string;

        self::assertSame($freshTranslationString, $initialTranslationString);
    }
}
