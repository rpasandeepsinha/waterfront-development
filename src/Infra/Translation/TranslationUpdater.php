<?php

declare(strict_types=1);

namespace Waterfront\Infra\Translation;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Translation\Loader;

class TranslationUpdater
{
    public function __construct(
        private readonly Loader $translationLoader,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function update(string $language, string $source): void
    {
        /** @var array<string> $translations */
        $translations = $this->translationLoader->load($language, $source);

        if ($translations === []) {
            return;
        }

        $json = json_encode(['data' => $translations], JSON_THROW_ON_ERROR);
        $this->filesystem->put(sprintf('%s-%s.json', $language, $source), $json);
    }
}
