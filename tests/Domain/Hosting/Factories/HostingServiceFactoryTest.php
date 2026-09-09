<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Factories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;

#[CoversClass(HostingServiceFactory::class)]
class HostingServiceFactoryTest extends IntegrationTestCase
{
    private HostingServiceFactory $hostingServiceFactory;

    public function setUp(): void
    {
        parent::setUp();

        $this->hostingServiceFactory = self::resolve(HostingServiceFactory::class);
    }

    #[Test]
    public function driver(): void
    {
        $this->expectNotToPerformAssertions();

        $this->hostingServiceFactory->driver(ProviderSlug::DIRECTADMIN);
    }

    #[Test]
    public function defaultDriver(): void
    {
        $this->expectNotToPerformAssertions();

        new ProviderFactory()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);
        $this->hostingServiceFactory->defaultDriver();
    }

    #[Test]
    public function defaultDriverMissingProvider(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->hostingServiceFactory->defaultDriver();
    }
}
