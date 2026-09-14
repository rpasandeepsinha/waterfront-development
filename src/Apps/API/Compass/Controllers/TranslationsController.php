<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Waterfront\Apps\API\Compass\Resources\Translations\TranslationsResource;
use Waterfront\Apps\Console\Commands\Translations\UpdateTranslationsS3;
use Waterfront\Domain\Translations\Enums\TranslationSource;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Services\TranslationService;

class TranslationsController
{
    public function __construct(
        private readonly TranslationService $translationService,
    ) {
    }

    public function updateDictionary(): Response
    {
        Artisan::call(UpdateTranslationsS3::class);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function list(string $source): ResourceCollection
    {
        $keys = TranslationKey::where('source', $source)->with(['translationStrings.language'])->get();

        $translations = [];
        foreach ($keys as $key) {
            $translations[$key->key] = ['key' => $key->key, 'translations' => []];
            foreach ($key->translationStrings as $string) {
                $translations[$key->key]['translations'][$string->language->locale] = $string->translated_string;
            }
        }

        return TranslationsResource::collection(array_values($translations));
    }

    public function updateTranslation(Request $request, string $source): Response
    {
        $request->validate([
            'key' => ['required', 'string', 'exists:translation_keys,key'],
            'translations' => ['required', 'array'],
            'translations.*' => ['required', 'string'],
        ]);

        $translationKey = $request->input('key', '');
        $translations = $request->array('translations');
        assert(is_string($translationKey));

        $sourceEnum = TranslationSource::from($source);

        $this->translationService->updateTranslation($sourceEnum, $translationKey, $translations);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function addTranslations(string $source, Request $request): Response
    {
        $translationKey = $request->input('key');
        $translations = $request->array('translations');
        assert(is_string($translationKey));

        $sourceEnum = TranslationSource::from($source);
        $this->translationService->createTranslation($sourceEnum, $translationKey, $translations);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
