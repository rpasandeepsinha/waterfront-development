<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Throwable;
use Waterfront\Apps\API\Waterfront\Resources\NewsResource;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\Repositories\CachedNewsRepository;

/**
 * Offers an endpoint to consume news on the front-ends.
 */
class NewsController
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly CachedNewsRepository $repository,
    ) {
    }

    /**
     * Returns a list of news items.
     */
    public function get(string $locale): mixed
    {
        $newsOverview = $this->configuration->getAsString('news.feed_overview');

        try {
            $news = $this->repository->get($locale);
        } catch (Throwable) {
            $news = [];
        }
        return NewsResource::collection($news)->additional(['links' => [
            'overview' => $newsOverview,
        ]]);
    }
}
