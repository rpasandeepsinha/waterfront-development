<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\ServiceOffering;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class ServiceOfferingClientTest extends TestCase
{
    #[Test]
    public function listServiceOfferings(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listServiceOfferings', [
                'domainid' => '8e5ce938-d5ee-48c1-852a-54aed90a898d',
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'serviceoffering' => [
                    [
                        'id' => '5bc9f753-7878-49e9-8165-31bbc9017830',
                        'name' => 'Test versio-staging',
                        'memory' => 2048,
                        'cpunumber' => 2,
                        'domainid' => '35071d7e-9fda-4af0-a1c2-791caf015e13',
                        'domain' => 'vps',
                        'rootdisksize' => 20,
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $serviceOfferings = $client->listServiceOfferings('8e5ce938-d5ee-48c1-852a-54aed90a898d');

        self::assertContainsOnlyInstancesOf(ServiceOffering::class, $serviceOfferings);
        self::assertCount(1, $serviceOfferings);
    }
}
