<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Services\DnsZoneFactories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Services\DnsZoneFactories\DnsZoneFromArrayTemplateFactory;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;

#[CoversClass(DnsZoneFromArrayTemplateFactory::class)]
class DnsTemplateFromArrayFactoryTest extends IntegrationTestCase
{
    #[Test]
    public function create(): void
    {
        $factory = new DnsZoneFromArrayTemplateFactory(
            [
                'default' => [],
                'hosting' => [],
            ],
            self::resolve(DnsRecordHydrator::class),
        );

        $actual = $factory->create('test.nl', 'default');
        $expected = new DnsZone(new Fqdn('test.nl'));
        self::assertEquals($expected, $actual);
    }
}
