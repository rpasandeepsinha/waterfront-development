<?php

declare(strict_types=1);

use Illuminate\Support\Env;

$maxItems = Env::get('NEWS_MAX_ITEMS_TO_STORE', 3);
assert(is_int($maxItems) || is_string($maxItems));

$interval = Env::get('NEWS_RENEW_INTERVAL_SECONDS', 5);
assert(is_int($interval) || is_string($interval));

return [
    'maxItemsToStore' => intval($maxItems),
    'renew_interval_seconds' => intval($interval),
    'type' => Env::get('NEWS_TYPE', 'unconfigured'),
    'feed_uri' => Env::get('NEWS_FEED_URI'),
    'feed_overview' => Env::get('NEWS_FEED_OVERVIEW'),
];
