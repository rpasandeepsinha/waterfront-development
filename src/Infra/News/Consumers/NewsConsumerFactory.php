<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\Consumers;

use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\Repositories\NewsRepository;
use Waterfront\Support\Exceptions\NotImplementedException;

/**
 * Creates a news api consumer for the given type and url.
 */
class NewsConsumerFactory
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    /**
     * Finds the correct consumer to create for the given type and url.
     *
     * @throws NotImplementedException for unknown consumer $types
     */
    public function create(NewsRepository $newsRepository, string $type, string $baseUrl): NewsConsumer
    {
        return match ($type) {
            'versio' => new VersioNewsConsumer($newsRepository, $baseUrl, $this->configuration),
            'yourhosting' => new YourhostingNewsConsumer($newsRepository, $baseUrl, $this->configuration),
            default => throw new NotImplementedException("Unknown news consumer [$type]"),
        };
    }
}
