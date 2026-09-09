<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient\Mapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\Mapper\DomainMapper;

#[CoversClass(DomainMapper::class)]
class DomainMapperTest extends TestCase
{
    #[Test]
    public function invoke(): void
    {
        $domain = (new DomainMapper())([
            'id' => 'foo',
            'name' => 'bar',
            'parentdomainid' => 'baz',
            'unknown' => 'pqr',
        ]);

        self::assertSame('foo', $domain->id);
        self::assertSame('bar', $domain->name);
        self::assertSame('baz', $domain->parentDomainId);
    }
}
