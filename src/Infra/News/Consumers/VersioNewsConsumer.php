<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\Consumers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use stdClass;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\DTO\News;
use Waterfront\Infra\News\DTO\NewsCover;
use Waterfront\Infra\News\Repositories\NewsRepository;

/**
 * Consumes news through the Versio news api format. Takes in an alternative parameter for locale.
 *
 * @package Waterfront\Apps\API\Waterfront\Controllers
 */
class VersioNewsConsumer implements NewsConsumer
{
    public function __construct(
        private readonly NewsRepository $newsRepository,
        private readonly string $newsApiUrl,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    public function consume(?string $alternative = null, int $timeoutSec = 5): void
    {
        if ($timeoutSec <= 0 || is_nan($timeoutSec)) {
            throw new InvalidArgumentException('Invalid timeout duration: ' . $timeoutSec);
        }

        $newsApiUrl = $this->newsApiUrl;
        $maxItemsToStore = $this->configuration->getAsInteger('news.maxItemsToStore');

        $response = Http::withOptions(['timeout' => $timeoutSec])->acceptJson()->get($newsApiUrl);

        if (! $response->ok()) {
            $status = $response->status();
            Log::warning("Unable to consume news. Response failed with code [{$status}]");

            return;
        }

        $jsonResponse = json_decode($response->body(), null, 512, JSON_THROW_ON_ERROR);
        assert($jsonResponse instanceof stdClass);

        $jsonResponse = get_object_vars($jsonResponse->data);

        $news = [];
        Log::debug('Consume received news');
        foreach ($jsonResponse as $item) {
            assert($item instanceof stdClass);
            $newsItem = new News();
            $newsItem->id = (int) $item->id;
            $newsItem->title = $item->title;
            $newsItem->description = $item->description;
            $newsItem->url = $item->url;
            $newsItem->date = $item->date;
            $newsItem->language = $item->language;
            $newsItem->author = 'Versio';
            $newsItem->ishtml = $item->ishtml;
            $newsItem->cover = new NewsCover();
            $newsItem->cover->medium = $item->cover->medium;

            $news[] = $newsItem;
        }

        // Make sure to order on latest
        usort($news, fn (News $a, News $b): int => strcmp($a->date, $b->date));

        // Only take the last $maxItemsToStore items / newest news items
        $news = array_slice($news, -$maxItemsToStore);

        Log::debug('Store news');
        $this->newsRepository->store($news, $alternative);
    }
}
