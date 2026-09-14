<?php

declare(strict_types=1);

namespace Waterfront\Infra\News\Providers;

use Illuminate\Support\ServiceProvider;

class NewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/news.php',
            'news',
        );
    }
}
