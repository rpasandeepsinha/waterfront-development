<?php

declare(strict_types=1);

namespace Tests\Infra\News;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\Consumers\NewsConsumerFactory;
use Waterfront\Infra\News\Consumers\VersioNewsConsumer;
use Waterfront\Infra\News\Consumers\YourhostingNewsConsumer;
use Waterfront\Infra\News\Repositories\CachedNewsRepository;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(NewsConsumerFactory::class)]
class NewsConsumerFactoryTest extends IntegrationTestCase
{
    #[Test]
    public function createUnknown(): void
    {
        $configuration = self::createStub(ConfigurationInterface::class);
        $factory = new NewsConsumerFactory($configuration);

        $this->expectException(NotImplementedException::class);

        $newsRepository = new CachedNewsRepository($configuration, $factory);
        $factory->create($newsRepository, 'aNonExistingConsumer', 'http://unknownUrl.com');
    }

    #[Test]
    public function createYourhostingConsumer(): void
    {
        $configuration = self::createMock(ConfigurationInterface::class);
        $configuration->expects(self::exactly(2))->method('getAsString')->willReturn('yourhosting');

        $factory = new NewsConsumerFactory($configuration);
        $newsRepository = new CachedNewsRepository($configuration, $factory);
        $result = $factory->create($newsRepository, 'yourhosting', 'http://myurl.com');

        self::assertInstanceOf(YourhostingNewsConsumer::class, $result);
    }

    #[Test]
    public function createVersioConsumer(): void
    {
        $configuration = self::createMock(ConfigurationInterface::class);
        $configuration->expects(self::exactly(2))->method('getAsString')->willReturn('versio');

        $factory = new NewsConsumerFactory($configuration);
        $newsRepository = new CachedNewsRepository($configuration, $factory);
        $result = $factory->create($newsRepository, 'versio', 'http://myurl.com');

        self::assertInstanceOf(VersioNewsConsumer::class, $result);
    }
}
