<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\Mapper\EventMapper;

#[CoversClass(EventMapper::class)]
class EventMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $event = (new EventMapper())([
            'id' => 'foo',
            'account' => 'abc',
            'domainid' => 'baz',
            'type' => 'mno',
            'description' => 'stu',
            'state' => 'vwx',
            'level' => 'yz',
            'created' => '2021-09-16T08:38:09+0200',
            'unknown' => 'pqr',
        ]);

        self::assertSame('foo', $event->id);
        self::assertSame('abc', $event->account);
        self::assertSame('baz', $event->domainId);
        self::assertSame('mno', $event->type);
        self::assertSame('vwx', $event->state);
        self::assertSame('yz', $event->level);
        self::assertEquals(new DateTimeImmutable('2021-09-16T08:38:09+0200'), $event->created);
        self::assertSame('stu', $event->description);
    }
}
