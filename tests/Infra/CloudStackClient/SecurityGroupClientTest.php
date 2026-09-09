<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class SecurityGroupClientTest extends TestCase
{
    #[Test]
    public function deployVirtualMachineWithJobIdFailedRetries(): void
    {
        $account = 'foo';
        $domainId = 'bar';
        $securityGroupId = 'baz';

        $mock = self::createMock(CloudStackBaseClient::class);
        $mock->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(fn (string $command, array $params): array => match (true) {
                $command === 'authorizeSecurityGroupIngress' && $params === [
                    'account'         => $account,
                    'domainid'        => $domainId,
                    'cidrlist'        => '0.0.0.0/0',
                    'protocol'        => 'ALL',
                    'securitygroupid' => $securityGroupId,
                ] => [],
                $command === 'authorizeSecurityGroupIngress' && $params === [
                    'account'         => $account,
                    'domainid'        => $domainId,
                    'cidrlist'        => '::/0',
                    'protocol'        => 'ALL',
                    'securitygroupid' => $securityGroupId,
                ] => [],
                default => throw new LogicException(),
            });

        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->authorizeSecurityGroupIngress(
            account: $account,
            domainId: $domainId,
            securityGroupId: $securityGroupId
        );
    }
}
