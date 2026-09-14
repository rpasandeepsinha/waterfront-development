<?php

declare(strict_types=1);

namespace Tests\Infra\Translation;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Translation\Loader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Translation\TranslationUpdater;

#[CoversClass(TranslationUpdater::class)]
class TranslationUpdaterTest extends IntegrationTestCase
{
    #[Test]
    public function translationUpdater(): void
    {
        $translationContent = [
            'general.form-labels.email' => 'Email',
        ];
        $translationLoaderContent = [
            'general.form-labels.email' => 'Email',
        ];
        $language = 'en';
        $source = 'waterfront-backend';

        $loader = self::createMock(Loader::class);
        $loader->expects(self::once())->method('load')->with($language, $source)->willReturn($translationLoaderContent);

        $filesystem = self::createMock(Filesystem::class);
        $filesystem
            ->expects(self::once())
            ->method('put')
            ->with(sprintf('%s-%s.json', $language, $source), json_encode(['data' => $translationContent]));

        $service = new TranslationUpdater($loader, $filesystem);
        $service->update($language, $source);
    }

    #[Test]
    public function translationUpdaterNotWorking(): void
    {
        $language = 'nl';
        $source = 'waterfront-backend';

        $loader = self::resolve(Loader::class);
        $filesystem = self::createMock(Filesystem::class);
        $filesystem->expects(self::never())->method('put');

        $service = new TranslationUpdater($loader, $filesystem);
        $service->update($language, $source);
    }
}
