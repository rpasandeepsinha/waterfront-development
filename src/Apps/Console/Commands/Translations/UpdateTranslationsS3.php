<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Translations;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Translations\Enums\TranslationPlatforms;
use Waterfront\Domain\Translations\Loaders\TranslationLoader;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Infra\Translation\TranslationUpdater;

#[AsCommand(name: 'export:translations')]
#[Description('Export the translations into the object storage bucket')]
class UpdateTranslationsS3 extends Command
{
    public function handle(TranslationUpdater $translationUpdater, Kernel $kernel, TranslationLoader $loader): int
    {
        $kernel->call(TranslationsToDatabase::class);

        $languages = TranslationLanguage::query()->pluck('locale')->sort();
        $translationSources = TranslationKey::query()->pluck('source')->unique()->sort();

        foreach ($languages as $language) {
            assert(is_string($language));
            foreach ($translationSources as $translationSource) {
                assert(is_string($translationSource));

                if (TranslationPlatforms::tryFrom($translationSource) === null) {
                    continue;
                }

                $translationUpdater->update($language, $translationSource);
                $loader->clearCache($language, $translationSource);
            }
        }

        return self::SUCCESS;
    }
}
