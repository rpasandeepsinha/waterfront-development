<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Translations;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;

#[AsCommand(name: 'translate:export-to-database')]
#[Description('find or create translations based on the json files committed in vcs towards the database.
        These will not overwrite existing translations')]
#[Signature('translate:export-to-database {audit-log-summary=audit-log-summary.json} {atlantis=atlantis.json} {compass=compass.json} {coast=coast.json} {beacon=beacon.json} {waterfront-backend=waterfront-backend.json}')]
class TranslationsToDatabase extends Command
{
    public function handle(): int
    {
        $this->findOrInsertLanguages();

        /** @var string $atlantisFile */
        $atlantisFile = $this->argument('atlantis');
        /** @var string $compassFile */
        $compassFile = $this->argument('compass');
        /** @var string $auditLogSummaryFile */
        $auditLogSummaryFile = $this->argument('audit-log-summary');
        /** @var string $coastFile */
        $coastFile = $this->argument('coast');
        /** @var string $beacon */
        $beacon = $this->argument('beacon');
        /** @var string $waterfrontBackend */
        $waterfrontBackend = $this->argument('waterfront-backend');

        $translationFiles = [
            'atlantis' => $atlantisFile,
            'audit-log-summary' => $auditLogSummaryFile,
            'compass' => $compassFile,
            'coast' => $coastFile,
            'beacon' => $beacon,
            'waterfront-backend' => $waterfrontBackend,
        ];

        // loop over the files and find or create keys and strings.
        foreach ($translationFiles as $source => $file) {
            $this->findOrCreateTranslationKeys($source, $file);
            $this->findOrCreateTranslationStrings($source, $file);
        }

        return self::SUCCESS;
    }

    private function findOrInsertLanguages(): void
    {
        DB::table(new TranslationLanguage()->getTable())->insertOrIgnore([
            'display_name' => 'Nederlands',
            'locale' => 'nl',
            'active' => true,
            'default' => true,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        DB::table(new TranslationLanguage()->getTable())->insertOrIgnore([
            'display_name' => 'English',
            'locale' => 'en',
            'active' => true,
            'default' => false,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    private function findOrCreateTranslationKeys(string $source, string $file): void
    {
        $translationKeysInDB = $this->getTranslationKeysBySource($source)->all();

        foreach ($this->getTranslationItems($file) as $key => $translation) {
            if (! in_array($key, $translationKeysInDB, true)) {
                $translationKey = new TranslationKey();
                $translationKey->key = $key;
                $translationKey->source = $source;
                $translationKey->save();
            }
        }
    }

    private function findOrCreateTranslationStrings(string $source, string $file): void
    {
        $localeIds  = TranslationLanguage::pluck('id', 'locale');

        /** @var array<int, string> $translationKeysInDB */
        $translationKeysInDB = $this->getTranslationKeysBySource($source)->all();

        $translationStringsInDB = $this->getTranslationStringsBySource($translationKeysInDB);

        // Get the json resource files that contain translations
        foreach ($this->getTranslationItems($file) as $key => $item) {
            // Translations from the json always need to have a locale.
            if (! array_key_exists('locales', $item)) {
                continue;
            }

            /** @var int $translationKeyId */
            $translationKeyId = array_search($key, $translationKeysInDB, true);
            /** @var array<mixed> $translationStrings */
            $translationStrings = Arr::get($translationStringsInDB, $translationKeyId, []);

            // For each item from the json file instantiate a object and findOrCreate based on the given values.
            $translations = Arr::get($item, 'locales', []);
            assert(is_array($translations));

            foreach ($translations as $locale => $translation) {
                $translationModel = new TranslationString();
                $languageId = Arr::get($localeIds, $locale);
                assert(is_int($languageId));
                $translatedString = Arr::get($translation, 'translated_string');
                assert(is_string($translatedString) || is_null($translatedString));

                $translationModel->language_id       = $languageId;
                $translationModel->key_id            = $translationKeyId;
                $translationModel->translated_string = strval($translatedString);

                $translationStringKey = array_search($translationModel->language_id, array_column($translationStrings, 'language_id'), true) ;

                if ($translationStringKey === false) {
                    $translationModel->save();
                    continue;
                }

                assert(is_array($translationStrings[$translationStringKey]));
                if ($translationStrings[$translationStringKey]['translated_string'] === null) {
                    TranslationString::where('id', $translationStrings[$translationStringKey]['id'])
                        ->update(['translated_string' => $translationModel->translated_string]);
                }
            }
        }
    }

    /**
     * @throws JsonException
     *
     * @return array<string, array<string, array<string>>>
     */
    private function getTranslationItems(string $file): array
    {
        $translationFile = Storage::disk('translations')->get($file) ?? '';

        $decoded = json_decode($translationFile, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        return $decoded;
    }

    /**
     * @return Collection<int|string, mixed>
     */
    private function getTranslationKeysBySource(string $source): Collection
    {
        return TranslationKey::where([
                'source' => $source,
            ])->pluck('key', 'id');
    }

    /**
     * @param array<int, string> $sourceIds
     *
     * @return array<mixed>
     */
    private function getTranslationStringsBySource(array $sourceIds): array
    {
        return TranslationString::whereIn(
            'key_id',
            array_flip($sourceIds)
        )->get()->groupBy('key_id')->toArray();
    }
}
