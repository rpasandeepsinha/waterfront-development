<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\Domain;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class DomainClientTest extends TestCase
{
    #[Test]
    public function listDomainChildren(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listDomainChildren', [
                'id' => 'foo',
                'listall' => 'true',
                'name' => 'bar',
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'domain' => [
                    [
                        'id' => 'foo',
                        'name' => 'bar',
                        'parentdomainid' => 'baz',
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $domains = $client->listDomainChildren('foo', 'bar');

        self::assertContainsOnlyInstancesOf(Domain::class, $domains);
        self::assertCount(1, $domains);
    }

    #[Test]
    public function createDomain(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock->expects(self::once())
            ->method('execute')
            ->with('createDomain', ['name' => 'bar', 'parentdomainid' => 'baz'])
            ->willReturn([
                'domain' => [
                    'id' => 'foo',
                    'name' => 'bar',
                    'parentdomainid' => 'baz',
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->createDomain('baz', 'bar');
    }
}
