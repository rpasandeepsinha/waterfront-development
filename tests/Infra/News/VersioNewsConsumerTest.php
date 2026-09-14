<?php

declare(strict_types=1);

namespace Tests\Infra\News;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use Tests\IntegrationTestCase;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\News\Consumers\VersioNewsConsumer;
use Waterfront\Infra\News\DTO\News;
use Waterfront\Infra\News\Repositories\NewsRepository;

#[CoversClass(VersioNewsConsumer::class)]
class VersioNewsConsumerTest extends IntegrationTestCase
{
    private NewsRepository&MockObject $repo;

    public function setUp(): void
    {
        parent::setUp();

        $this->repo = self::createMock(NewsRepository::class);
    }

    #[Test]
    public function consumeMultiNews(): void
    {
        $url = 'https://loremipsum.com';
        $expectedLanguage = 'en';

        $contents = (string) file_get_contents(__DIR__ . '/Data/yourhosting_multi.json');
        $json = json_decode($contents, null, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $json);
        /** @var stdClass[] $newsItems */
        $newsItems = array_values(get_object_vars($json->data));

        Http::fake([
            $url => Http::response($contents, 200),
        ]);

        $this->repo
            ->expects(self::once())
            ->method('store')
            ->with(
                self::callback(function (array $items) use ($newsItems) {
                    /** @var News[] $items */
                    self::assertCount(2, $items);
                    self::assertSame($newsItems[0]->id, $items[0]->id);
                    self::assertSame($newsItems[0]->title, $items[0]->title);
                    self::assertSame($newsItems[1]->id, $items[1]->id);
                    self::assertSame($newsItems[1]->title, $items[1]->title);

                    return true;
                }),
                self::equalTo($expectedLanguage),
            );

        $consumer = new VersioNewsConsumer($this->repo, $url, self::createStub(ConfigurationInterface::class));
        $consumer->consume($expectedLanguage, 5);
    }

    #[Test]
    public function consumeSingleNews(): void
    {
        $url = 'https://loremipsum.com';
        $expectedLanguage = 'en';

        $contents = (string) file_get_contents(__DIR__ . '/Data/yourhosting_single.json');
        $json = json_decode($contents, null, 512, JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $json);
        /** @var stdClass[] $newsItems */
        $newsItems = array_values(get_object_vars($json->data));

        Http::fake([
            $url => Http::response($contents, 200),
        ]);

        $this->repo
            ->expects(self::once())
            ->method('store')
            ->with(
                self::callback(function (array $items) use ($newsItems) {
                    /** @var News[] $items */
                    self::assertCount(1, $items);
                    self::assertSame($newsItems[0]->id, $items[0]->id);
                    self::assertSame($newsItems[0]->title, $items[0]->title);

                    return true;
                }),
                self::equalTo($expectedLanguage),
            );

        $consumer = new VersioNewsConsumer($this->repo, $url, self::createStub(ConfigurationInterface::class));
        $consumer->consume($expectedLanguage, 5);
    }

    #[Test]
    public function consumeNoNews(): void
    {
        $url = 'https://loremipsum.com';
        $expectedLanguage = 'en';

        Http::fake([
            $url => Http::response('{"data":{}}', 200),
        ]);

        $this->repo->expects(self::once())->method('store')->with([]);

        $consumer = new VersioNewsConsumer($this->repo, $url, self::createStub(ConfigurationInterface::class));
        $consumer->consume($expectedLanguage, 5);
    }
}
