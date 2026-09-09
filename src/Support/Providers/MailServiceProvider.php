<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Mailer\MailerInterface;

class MailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(MailerInterface::class, Mailer::class);
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [
            MailerInterface::class,
        ];
    }
}
