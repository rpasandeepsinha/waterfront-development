<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\TranslationKeyFactory;
use Tests\Factories\TranslationLanguageFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Translations\TranslationsToDatabase;
use Waterfront\Apps\Console\Commands\Translations\UpdateTranslationsS3;
use Waterfront\Apps\Console\Kernel;
use Waterfront\Domain\Translations\Loaders\TranslationLoader;
use Waterfront\Infra\Translation\TranslationUpdater;

#[CoversClass(TranslationUpdater::class)]
class UpdateTranslationsS3Test extends IntegrationTestCase
{
    #[Test]
    public function translationCommand(): void
    {
        new TranslationLanguageFactory()->create(['locale' => 'en']);
        new TranslationLanguageFactory()->create(['locale' => 'nl']);
        new TranslationKeyFactory()->create(['source' => 'waterfront-backend']);
        new TranslationKeyFactory()->create(['source' => 'atlantis']);

        $translationUpdater = self::createMock(TranslationUpdater::class);
        $translationUpdater
            ->expects(self::exactly(4))
            ->method('update')
            ->with(
                ...self::withConsecutive(
                    ['en', 'atlantis'],
                    ['en', 'waterfront-backend'],
                    ['nl', 'atlantis'],
                    ['nl', 'waterfront-backend'],
                ),
            );

        $kernel = self::createMock(Kernel::class);
        $kernel->expects(self::once())->method('call')->with(TranslationsToDatabase::class);

        $service = new UpdateTranslationsS3();
        $service->handle($translationUpdater, $kernel, self::createStub(TranslationLoader::class));
    }
}
