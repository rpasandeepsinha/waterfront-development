<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Factories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Ssl\Factories\SslServiceFactory;

#[CoversClass(SslServiceFactory::class)]
class SslServiceFactoryTest extends IntegrationTestCase
{
    private SslServiceFactory $sslServiceFactory;

    public function setUp(): void
    {
        parent::setUp();

        $this->sslServiceFactory = self::resolve(SslServiceFactory::class);
    }

    #[Test]
    public function defaultDriver(): void
    {
        // Just make sure we're not running into a runtime exception
        $this->expectNotToPerformAssertions();

        ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);
        $this->sslServiceFactory->defaultDriver();
    }

    #[Test]
    public function defaultDriverMissingProvider(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->sslServiceFactory->defaultDriver();
    }

    #[Test]
    public function resolveProviderByProductDefault(): void
    {
        // Just make sure we're not running into a runtime exception
        $this->expectNotToPerformAssertions();

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);

        $this->sslServiceFactory->resolveProviderByProduct($product);
    }
}
