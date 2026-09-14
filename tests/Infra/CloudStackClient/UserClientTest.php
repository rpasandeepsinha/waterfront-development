<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\User;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class UserClientTest extends TestCase
{
    #[Test]
    public function listUsers(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listUsers', [
                'domainid' => 'bar',
                'username' => '',
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'user' => [
                    [
                        'id' => 'foo',
                        'username' => 'baz',
                        'accountid' => 'abc',
                        'domainid' => 'bar',
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $cloudstackUsers = $client->listUsers('bar');

        self::assertContainsOnlyInstancesOf(User::class, $cloudstackUsers);
        self::assertCount(1, $cloudstackUsers);
    }

    #[Test]
    public function getUserKeys(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('getUserKeys', ['id' => 'baz'])
            ->willReturn([
                'userkeys' => [
                    'secretkey' => 'foo',
                    'apikey' => 'bar',
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->getUserKeys('baz');
    }

    #[Test]
    public function registerUserKeys(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('registerUserKeys', ['id' => 'baz'])
            ->willReturn([
                'userkeys' => [
                    'secretkey' => 'foo',
                    'apikey' => 'bar',
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->registerUserKeys('baz');
    }

    #[Test]
    public function updateUser(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('updateUser', ['id' => 'baz', 'password' => 'bar'])
            ->willReturn([
                'user' => [
                    'id' => 'baz',
                    'username' => 'foo',
                    'accountid' => 'abc',
                    'domainid' => 'bar',
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->updateUser('baz', 'bar');
    }

    #[Test]
    public function listAllVirtualMachines(): void
    {
        $listVirtualMachineResponse = json_decode(
            (string) file_get_contents(__DIR__ . '/data/listvirtualmachines.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listVirtualMachines', ['listall' => true])
            ->willReturn($listVirtualMachineResponse);

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $virtualMachines = $client->listAllVirtualMachines();

        self::assertContainsOnlyInstancesOf(VirtualMachine::class, $virtualMachines);
        self::assertCount(3, $virtualMachines);
    }

    #[Test]
    public function listAllVirtualMachinesCanHandleEmptyResponse(): void
    {
        $listVirtualMachineResponse = [];

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listVirtualMachines', ['listall' => true])
            ->willReturn($listVirtualMachineResponse);

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $virtualMachines = $client->listAllVirtualMachines();

        self::assertCount(0, $virtualMachines);
    }
}
