<?php

declare(strict_types=1);

namespace Tests\Infra\News;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\Consumers\NewsConsumerFactory;
use Waterfront\Infra\News\DTO\News;
use Waterfront\Infra\News\Repositories\CachedNewsRepository;

#[CoversClass(CachedNewsRepository::class)]
class CachedNewsRepositoryTest extends IntegrationTestCase
{
    private ConfigurationInterface&Stub $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $configuration = self::createStub(ConfigurationInterface::class);
        $configuration->method('getAsString')
            ->willReturn('versio');

        $this->configuration = $configuration;
    }

    #[Test]
    public function storeNews(): void
    {
        $news1 = new News();
        $news1->id = 1;
        $news1->title = 'test#1';
        $news1->description = 'description#1';
        $news2 = new News();
        $news2->id = 1;
        $news2->title = 'test#1';
        $news2->description = 'description#1';

        $repository = new CachedNewsRepository(
            $this->configuration,
            self::resolve(NewsConsumerFactory::class)
        );
        $repository->store([
            $news1,
            $news2,
        ]);
        $news = (array) Cache::get('NEWS.API.ITEMS');
        self::assertCount(2, $news);
    }

    #[Test]
    public function retrieveNews(): void
    {
        $news1 = new News();
        $news1->id = 1;
        $news1->title = 'test#1';
        $news1->description = 'description#1';

        $news2 = new News();
        $news2->id = 1;
        $news2->title = 'test#1';
        $news2->language = 'en';
        $news2->description = 'description#1';

        $repository = new CachedNewsRepository(
            $this->configuration,
            self::resolve(NewsConsumerFactory::class)
        );
        Cache::put('NEWS.API.ITEMS.LOCK', []);
        Cache::put('NEWS.API.ITEMS', [
            $news1,
            $news2,
        ]);
        $news = $repository->get();

        self::assertCount(2, $news);
    }
}
