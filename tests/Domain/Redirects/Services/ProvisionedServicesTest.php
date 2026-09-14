<?php

declare(strict_types=1);

namespace Tests\Domain\Redirects\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Waterfront\Domain\Redirects\Services\RedirectDnsService;
use Waterfront\Domain\Redirects\Services\RedirectDnsServiceInterface;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Redirects\Services\RedirectServiceInterface;

#[CoversClass(RedirectService::class)]
class ProvisionedServicesTest extends TestCase
{
    #[Test]
    public function ifAllServicesAreRegistered(): void
    {
        self::assertInstanceOf(
            RedirectDnsService::class,
            $this->app->get(RedirectDnsServiceInterface::class),
            'The Redirects.CustomerSharedDnsService was not registered correctly.',
        );
        self::assertInstanceOf(
            RedirectService::class,
            $this->app->get(RedirectServiceInterface::class),
            'The Redirects.CustomerSharedDnsService was not registered correctly.',
        );
    }
}
