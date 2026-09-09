<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\Volume;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class VolumeClientTest extends TestCase
{
    #[Test]
    public function listVolumes(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listVolumes', [
                'domainid' => 'foo',
                'name' => 'bar',
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'volume' => [
                    [
                        'id' => 'abc',
                        'name' => 'bar',
                        'domainid' => 'foo',
                        'account' => 'myaccount',
                        'type' => 'DATA-DISK',
                        'state' => 'Ready',
                        'virtualmachineid' => 'def',
                        'diskofferingid' => 'ghi',
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $volumes = $client->listVolumes('foo', 'bar');

        self::assertContainsOnlyInstancesOf(Volume::class, $volumes);
        self::assertCount(1, $volumes);
    }
}
