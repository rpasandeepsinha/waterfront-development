<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\Repositories;

use Waterfront\Infra\News\DTO\News;

interface NewsRepository
{
    /**
     * Retrieve a list of news items for the given alternative, could be: nl, en.
     *
     * @param string|null $alternative an alternative, for example: nl, en versions
     *
     * @return array<News>
     */
    public function get(?string $alternative = null): array;

    /**
     * Stores the given news items.
     *
     * @param array<News> $news
     * @param string|null $alternative specifies an alternate version, for example: nl, en versions
     */
    public function store(array $news, ?string $alternative = null): void;
}
