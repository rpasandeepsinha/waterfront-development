<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\Repositories;

use Illuminate\Support\Facades\Cache;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\Consumers\NewsConsumer;
use Waterfront\Infra\News\Consumers\NewsConsumerFactory;
use Waterfront\Infra\News\DTO\News;

/**
 * A repository that retrieves and stores news using the Laravel Cache mechanism.
 *
 * @package Waterfront\Apps\API\Waterfront\Controllers
 */
class CachedNewsRepository implements NewsRepository
{
    public const NewsBaseCacheKey = 'NEWS.API.ITEMS';

    private readonly NewsConsumer $consumer;

    public function __construct(private readonly ConfigurationInterface $configuration, NewsConsumerFactory $factory)
    {
        $this->consumer = $factory->create(
            $this,
            $this->configuration->getAsString('news.type'),
            $this->configuration->getAsString('news.feed_uri')
        );
    }

    /**
     * Replaces the news items in cache with the specified ones.
     *
     * @param array<News> $news
     */
    public function store(array $news, ?string $alternative = null): void
    {
        $localeCacheKey = $this->cacheKey($alternative);

        Cache::put($localeCacheKey, $news);
    }

    /**
     * Returns a list of news articles.
     *
     * @param string|null $alternative a string with locale country id: en/nl
     *
     * @return array<News> a list of news items
     */
    public function get(?string $alternative = null): array
    {
        $localeCacheKey = $this->cacheKey($alternative);

        /**
         * uses the cache timing mechanism to attempt renewal of the cache
         * every 'renew_interval_seconds' seconds. Does not invalidate the
         * news cache itself, this is to avoid losing cached news when the
         * destination is offline.
         */
        Cache::remember($localeCacheKey . '.LOCK', $this->configuration->getAsInteger('news.renew_interval_seconds'), function () use ($alternative): bool {
            $this->consumer->consume($alternative, 2);
            return true;
        });

        // fall back to cache
        $news = Cache::get($localeCacheKey) ?? [];
        assert(is_array($news));

        return $news;
    }

    /**
     * Return a combined key to use during caching.
     *
     * @param string|null $alternative an alternative version, for example: en, nl
     *
     * @return string the combined cache key for this $alternative
     */
    private function cacheKey(?string $alternative = null): string
    {
        return rtrim(self::NewsBaseCacheKey . '.' . $alternative, '.');
    }
}
