<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Loaders;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Filesystem\Filesystem;
use Waterfront\Domain\Translations\Models\TranslationString;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class TranslationLoader implements Loader
{
    private const string CACHE_KEY_PREFIX = 'sandwaveio.translation.locale.';

    /**
     * @var array<string, string>
     */
    private array $hints;

    public function __construct(
        private readonly Filesystem $files,
        private readonly Repository $cache,
        private readonly ConfigurationInterface $configuration,
        private readonly ?string $path = null,
    ) {
    }

    /**
     * Load the messages for the given locale.
     *
     * @param string      $locale
     * @param string      $group
     * @param string|null $namespace
     *
     * @return array<string>
     */
    public function load($locale, $group, $namespace = null): array
    {
        return $this->cache->remember(
            self::getCacheKey($locale, $group),
            $this->configuration->getAsInteger('translations.cache_time'),
            function () use ($group, $locale, $namespace): array {
                $databaseTranslations = $this->loadDatabaseTranslations($group, $locale);

                $fileTranslations = $this->loadFileTranslations($group, $locale, $namespace);

                return array_merge($databaseTranslations, $fileTranslations);
            },
        );
    }

    public function clearCache(string $locale, string $group): void
    {
        $this->cache->forget(self::getCacheKey($locale, $group));
    }

    public static function getCacheKey(string $locale, string $group): string
    {
        $key = $locale . $group;

        return sprintf('%s.%s', self::CACHE_KEY_PREFIX, $key);
    }

    /**
     * @param string $namespace
     * @param string $hint
     */
    public function addNamespace($namespace, $hint): void
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * @param string $path
     */
    public function addJsonPath($path): void
    {
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->hints;
    }

    /**
     * @throws FileNotFoundException
     *
     * @return array<string>
     */
    private function loadPath(?string $path, string $locale, string $group): array
    {
        if ($path === null) {
            return [];
        }

        if ($this->files->exists($full = "{$path}/{$locale}/{$group}.php")) {
            $content = $this->files->getRequire($full);
            assert(is_array($content));

            return $content;
        }

        return [];
    }

    /**
     * @throws FileNotFoundException
     *
     * @return array<string>
     */
    private function loadFileTranslations(string $group, string $locale, ?string $namespace = null): array
    {
        if (is_null($namespace) || $namespace === '*') {
            return $this->loadPath($this->path, $locale, $group);
        }

        return [];
    }

    /**
     *
     * @return array<string>
     */
    private function loadDatabaseTranslations(string $group, string $locale): array
    {
        return TranslationString::getGroup($group, $locale);
    }
}
