<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackPaginationIterator;
use Waterfront\Infra\CloudStackClient\DTO\Volume;
use Waterfront\Infra\CloudStackClient\Mapper\VolumeMapper;

#[CoversClass(CloudStackPaginationIterator::class)]
class PaginationIteratorTest extends TestCase
{
    #[Test]
    public function multiPageResponse(): void
    {
        $client = self::createMock(CloudStackBaseClient::class);
        $client
            ->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(fn (string $command, array $params): array => match (true) {
                $command === 'listvolumes' && $params === [
                    'page' => '1',
                    'pagesize' => '2',
                ] => [
                    'count' => 3,
                    'volume' => [
                        [
                            'id' => 'abc',
                            'name' => 'abc',
                            'domainid' => 'abc',
                            'account' => 'abc',
                            'type' => 'abc',
                            'state' => 'abc',
                        ],
                        [
                            'id' => 'def',
                            'name' => 'def',
                            'domainid' => 'def',
                            'account' => 'def',
                            'type' => 'def',
                            'state' => 'def',
                        ],
                    ],
                ],
                $command === 'listvolumes' && $params === [
                    'page' => '2',
                    'pagesize' => '2',
                ] => [
                    'count' => 3,
                    'volume' => [
                        [
                            'id' => 'ghi',
                            'name' => 'ghi',
                            'domainid' => 'ghi',
                            'account' => 'ghi',
                            'type' => 'ghi',
                            'state' => 'ghi',
                        ],
                    ],
                ],
                default => throw new LogicException(),
            });

        $iterator = new CloudStackPaginationIterator($client, 'listvolumes', [], 'volume', new VolumeMapper(), 2);

        self::assertCount(3, $iterator);
        self::assertCount(3, $iterator);
        self::assertContainsOnlyInstancesOf(Volume::class, $iterator);
        self::assertContainsOnlyInstancesOf(Volume::class, $iterator);

        $iterator->rewind();

        $current = $iterator->current();
        self::assertInstanceOf(Volume::class, $current);
        self::assertTrue($iterator->valid());
        self::assertSame(0, $iterator->key());
        self::assertSame('abc', $current->id);

        $iterator->next();

        $current = $iterator->current();
        self::assertInstanceOf(Volume::class, $current);
        self::assertTrue($iterator->valid());
        self::assertSame('def', $current->id);
        self::assertSame(1, $iterator->key());

        $iterator->next();

        $current = $iterator->current();
        self::assertInstanceOf(Volume::class, $current);
        self::assertTrue($iterator->valid());
        self::assertSame('ghi', $current->id);
        self::assertSame(2, $iterator->key());

        $iterator->next();

        $current = $iterator->current();
        self::assertFalse($iterator->valid());
        self::assertNull($current);

        $iterator->rewind();

        $current = $iterator->current();
        self::assertInstanceOf(Volume::class, $current);
        self::assertTrue($iterator->valid());
        self::assertSame('abc', $current->id);
        self::assertSame(0, $iterator->key());
    }

    #[Test]
    public function emptyResponse(): void
    {
        $client = self::createMock(CloudStackBaseClient::class);
        $client
            ->expects(self::once())
            ->method('execute')
            ->willReturn([]);

        $iterator = new CloudStackPaginationIterator($client, 'listvolumes', [], 'volume', new VolumeMapper(), 2);

        self::assertFalse($iterator->valid());
        self::assertNull($iterator->current());
    }
}
