<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class VirtualMachineClientTest extends TestCase
{
    #[Test]
    public function emptyResponse(): void
    {
        $mock = self::createStub(CloudStackBaseClient::class);
        $mock->method('execute')->willReturn([]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $virtualMachines = $client->listVirtualMachines(
            domainId: 'foo',
            name: 'bar',
            state: CloudstackMachineState::PRESENT,
        );

        self::assertCount(0, $virtualMachines);
    }

    #[Test]
    public function listVirtualMachines(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listVirtualMachines', [
                'domainid' => 'foo',
                'name' => 'bar',
                'state' => 'Present',
                'listall' => 'true',
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'virtualmachine' => [
                    [
                        'id' => 'abc',
                        'name' => 'bar',
                        'username' => 'terminator',
                        'nic' => [['ipaddress' => '185.185.185.185', 'ip6address' => '2600:1801:1::1']],
                        'state' => 'Present',
                        'domainid' => 'foo',
                        'account' => 'myaccount',
                        'serviceofferingid' => 'def',
                        'userdata' => self::anything(),
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $virtualMachines = $client->listVirtualMachines(
            domainId: 'foo',
            name: 'bar',
            state: CloudstackMachineState::PRESENT,
        );

        self::assertContainsOnlyInstancesOf(VirtualMachine::class, $virtualMachines);
        self::assertCount(1, $virtualMachines);
    }

    #[Test]
    public function getVirtualMachine(): void
    {
        $id = 'abc';

        $nic = [
            'id' => '3f5a059a-76e1-4bb0-9a2d-316c6ce55f93',
            'networkid' => 'da1d7155-a503-4f43-a9e7-fc97238077af',
            'networkname' => '59210 - Provider Private 1',
            'netmask' => '255.255.255.128',
            'gateway' => '185.159.242.3',
            'ipaddress' => '127.0.0.1',
            'isolationuri' => 'vxlan://503',
            'broadcasturi' => 'vxlan://503',
            'traffictype' => 'Guest',
            'type' => 'Shared',
            'isdefault' => true,
            'macaddress' => '1e:00:f3:00:03:46',
            'ip6gateway' => '2a03:3060:c::1',
            'ip6cidr' => '2a03:3060:c::/64',
            'ip6address' => '2600:1801:1::1',
            'secondaryip' => [],
            'extradhcpoption' => [],
            'deviceid' => '0',
        ];

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listVirtualMachines', [
                'id' => $id,
            ])
            ->willReturn([
                'count' => 1,
                'virtualmachine' => [
                    [
                        'id' => $id,
                        'name' => 'bar',
                        'username' => 'terminator',
                        'nic' => [$nic],
                        'state' => 'Present',
                        'domainid' => 'foo',
                        'account' => 'myaccount',
                        'serviceofferingid' => 'def',
                        'userdata' => self::anything(),
                    ],
                ],
            ]);

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->getVirtualMachine($id);
    }

    #[Test]
    public function getVirtualMachineNotFound(): void
    {
        $nonExistingId = 'cba';

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listVirtualMachines', [
                'id' => $nonExistingId,
            ])
            ->willThrowException(new ClientException());

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        self::expectException(CloudstackNotFoundException::class);

        $client->getVirtualMachine($nonExistingId);
    }

    #[Test]
    public function authorizeSecurityGroupIngress(): void
    {
        $account = 'test-account';
        $domainId = 'test-domain-id';
        $securityGroupId = 'f1e5c823-dfd2-4905-9ef0-ae9d09c173a4';

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(fn (string $command, array $params): array => match (true) {
                $command === 'authorizeSecurityGroupIngress'
                    && $params === [
                        'account' => $account,
                        'domainid' => $domainId,
                        'cidrlist' => '0.0.0.0/0',
                        'protocol' => 'ALL',
                        'securitygroupid' => $securityGroupId,
                    ]
                    => [],
                $command === 'authorizeSecurityGroupIngress'
                    && $params === [
                        'account' => $account,
                        'domainid' => $domainId,
                        'cidrlist' => '::/0',
                        'protocol' => 'ALL',
                        'securitygroupid' => $securityGroupId,
                    ]
                    => [],
                default => throw new LogicException(),
            });

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $client->authorizeSecurityGroupIngress($account, $domainId, $securityGroupId);
    }

    #[Test]
    public function createSecurityGroup(): void
    {
        $account = 'test-account';
        $domainId = 'test-domain-id';
        $securityGroupId = 'f1e5c823-dfd2-4905-9ef0-ae9d09c173a4';

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('createSecurityGroup', [
                'name' => $account,
                'description' => 'Default for ' . $account,
                'account' => $account,
                'domainid' => $domainId,
            ])
            ->willReturn([
                'count' => 1,
                'securitygroup' => [
                    'id' => $securityGroupId,
                    'description' => 'security group info',
                    'domainid' => $domainId,
                    'name' => $account,
                    'account' => $account,
                ],
            ]);

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $createdSecurityGroup = $client->createSecurityGroup($account, $domainId);

        self::assertSame($createdSecurityGroup, $securityGroupId);
    }

    #[Test]
    public function deployVirtualMachineWithJobIdSucceeds(): void
    {
        $hostname = 'builder';
        $fqdn = 'builder';
        $userData =
            '#cloud-config
                        manage_etc_hosts: true
                        fqdn: '
            . $fqdn
            . '
                        hostname: '
            . $hostname
            . '
                        timezone: Europe/Amsterdam
                        ssh_pwauth: True
                        chpasswd:
                            expire: false';

        $mockClient = self::createMock(CloudStackBaseClient::class);
        $mockClient
            ->expects(self::once())
            ->method('execute')
            ->with(
                'deployVirtualMachine',
                [
                    'serviceofferingid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    'templateid' => '2cbb2073-523d-477b-af5b-c913076a8586',
                    'zoneId' => '10c85e3a-b499-4b73-a78d-f2f48ca2a3ba',
                    'domainid' => 'foo',
                    'account' => 'bar',
                    'securitygroupids' => 'baz',
                    'displayname' => 'VirtualMachine A',
                    'userdata' => base64_encode($userData),
                    'networkids' => '1f428807-11c1-488a-a640-dc6c3f615eeb',
                ],
            )
            ->willReturn(
                json_decode(
                    (string) file_get_contents(__DIR__ . '/data/created_job.json'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );

        $client = new CloudStackClient($mockClient, CloudstackSerializerFactory::get());
        $client->deployVirtualMachine(
            serviceOfferingId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            osTemplateId: '2cbb2073-523d-477b-af5b-c913076a8586',
            zoneId: '10c85e3a-b499-4b73-a78d-f2f48ca2a3ba',
            domainId: 'foo',
            account: 'bar',
            securityGroupId: 'baz',
            displayName: 'VirtualMachine A',
            hostname: $hostname,
            fqdn: $fqdn,
            keyPair: null,
            networkId: '1f428807-11c1-488a-a640-dc6c3f615eeb',
        );
    }

    #[Test]
    public function deployVirtualMachineWithJobIdSucceedsWithSshKey(): void
    {
        $hostname = 'builder';
        $fqdn = 'builder';
        $sshKeyName = sha1('the cloudstack keyname');
        $userData =
            '#cloud-config
                        manage_etc_hosts: true
                        fqdn: '
            . $fqdn
            . '
                        hostname: '
            . $hostname
            . '
                        timezone: Europe/Amsterdam
                        ssh_pwauth: True
                        chpasswd:
                            expire: false';

        $mockClient = self::createMock(CloudStackBaseClient::class);
        $mockClient
            ->expects(self::once())
            ->method('execute')
            ->with(
                'deployVirtualMachine',
                [
                    'serviceofferingid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    'templateid' => '2cbb2073-523d-477b-af5b-c913076a8586',
                    'zoneId' => '10c85e3a-b499-4b73-a78d-f2f48ca2a3ba',
                    'domainid' => 'foo',
                    'account' => 'bar',
                    'securitygroupids' => 'baz',
                    'displayname' => 'VirtualMachine A',
                    'userdata' => base64_encode($userData),
                    'keypair' => $sshKeyName,
                    'networkids' => '1f428807-11c1-488a-a640-dc6c3f615eeb',
                ],
            )
            ->willReturn(
                json_decode(
                    (string) file_get_contents(__DIR__ . '/data/created_job.json'),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );

        $client = new CloudStackClient($mockClient, CloudstackSerializerFactory::get());
        $client->deployVirtualMachine(
            serviceOfferingId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            osTemplateId: '2cbb2073-523d-477b-af5b-c913076a8586',
            zoneId: '10c85e3a-b499-4b73-a78d-f2f48ca2a3ba',
            domainId: 'foo',
            account: 'bar',
            securityGroupId: 'baz',
            displayName: 'VirtualMachine A',
            hostname: $hostname,
            fqdn: $fqdn,
            keyPair: $sshKeyName,
            networkId: '1f428807-11c1-488a-a640-dc6c3f615eeb',
        );
    }
}
