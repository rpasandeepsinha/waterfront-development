<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class ZoneClientTest extends TestCase
{
    #[Test]
    public function listZones(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listZones')
            ->willReturn([
                'zone' => [
                    [
                        'id' => '10c85e3a-b499-4b73-a78d-f2f48ca2a3ba',
                        'name' => 'zone03.ams02.cldin.net',
                        'networktype' => 'Basic',
                        'securitygroupsenabled' => false,
                        'allocationstate' => 'Enabled',
                        'zonetoken' => '14fc3007-25eb-33fe-bc7f-fcebbd5ab755',
                        'dhcpprovider' => 'VirtualRouter',
                        'localstorageenabled' => true,
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->listZones();
    }
}
