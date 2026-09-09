<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\Consumers;

/**
 * A news consumer that consumes a news api, and passes the news data to a repository.
 */
interface NewsConsumer
{
    /**
     * Stores the received news items after consuming a source.
     *
     * @param string|null $alternative an alternative channel, like: en, nl
     * @param int         $timeoutSec  max time the consume may take, after which it should time out/abort
     */
    public function consume(?string $alternative = null, int $timeoutSec = 5): void;
}
