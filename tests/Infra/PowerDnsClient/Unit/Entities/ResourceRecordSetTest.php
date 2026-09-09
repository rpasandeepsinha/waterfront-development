<?php

declare(strict_types=1);

namespace Tests\Infra\PowerDnsClient\Unit\Entities;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\PowerDnsClient\Entities\PowerDnsRecord;
use Waterfront\Infra\PowerDnsClient\Entities\ResourceRecordSet;

#[CoversClass(ResourceRecordSet::class)]
class ResourceRecordSetTest extends TestCase
{
    #[Test]
    public function getters(): void
    {
        $testItem = new ResourceRecordSet();
        self::assertSame('DELETE', $testItem->getChangetype());
        self::assertSame([], $testItem->getRecords());
        $record = new PowerDnsRecord();
        $testItem->addRecord($record);
        self::assertEquals([$record], $testItem->getRecords());
        self::assertSame('REPLACE', $testItem->getChangetype());
    }
}
