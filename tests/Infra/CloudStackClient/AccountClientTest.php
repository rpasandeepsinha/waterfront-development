<?php

declare(strict_types=1);

namespace Tests\Infra\CloudStackClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\Account;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

#[CoversClass(CloudStackClient::class)]
class AccountClientTest extends TestCase
{
    #[Test]
    public function listAccounts(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('listAccounts', [
                'domainid' => 'foo',
                'name' => 'bar',
                'page' => 1,
                'pagesize' => 500,
            ])
            ->willReturn([
                'count' => 1,
                'account' => [
                    [
                        'id' => 'foo',
                        'name' => 'bar',
                        'domainid' => 'baz',
                        'accounttype' => '1',
                    ],
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());

        $accounts = $client->listAccounts('foo', 'bar');

        self::assertContainsOnlyInstancesOf(Account::class, $accounts);
        self::assertCount(1, $accounts);
    }

    #[Test]
    public function createAccount(): void
    {
        $mock = self::createMock(CloudStackBaseClient::class);
        $mock
            ->expects(self::once())
            ->method('execute')
            ->with('createAccount', [
                'domainid' => 'baz',
                'username' => 'myuser',
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => 'support@example.com',
                'password' => 's3cr3t',
                'roleid' => 'foo',
            ])
            ->willReturn([
                'account' => [
                    'id' => 'abc',
                    'name' => 'myuser',
                    'domainid' => 'baz',
                    'username' => 'myuser',
                    'firstname' => 'John',
                    'lastname' => 'Doe',
                    'email' => 'support@example.com',
                    'password' => 's3cr3t',
                    'roleid' => 'foo',
                    'accounttype' => '2',
                ],
            ]);
        $client = new CloudStackClient($mock, CloudstackSerializerFactory::get());
        $client->createAccount(
            'baz',
            'myuser',
            'John',
            'Doe',
            'support@example.com',
            's3cr3t',
            'foo',
        );
    }
}
