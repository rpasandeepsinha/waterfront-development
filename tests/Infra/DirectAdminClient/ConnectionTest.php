<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Infra\DirectAdminClient\Mock\DirectAdminTestServer;
use Tests\TestCase;
use Waterfront\Infra\DirectAdminClient\Connection\Connection;

#[CoversClass(Connection::class)]
class ConnectionTest extends TestCase
{
    #[Test]
    public function credentials_are_loaded_from_server_model(): void
    {
        $serverModel = new DirectAdminTestServer('', 'test-user', '', true, 'test-domain.nl', 1234);
        $credentials = new Connection($serverModel);

        self::assertSame(1234, $credentials->getPort());
        self::assertSame('test-domain.nl', $credentials->getDomain());
        self::assertSame('test-user', $credentials->getUsername());
    }

    #[Test]
    public function a_admin_should_be_able_to_login_as_another_user(): void
    {
        $serverModel = new DirectAdminTestServer('', 'test-user', '', true, 'test-domain.nl', 1234);
        $connection = new Connection($serverModel);
        self::assertStringNotContainsString('login-as', (string) $connection);
        self::assertFalse($connection->usesAsUser());

        $connection->asUser('client');
        self::assertStringContainsString('login-as[client]', (string) $connection);
        self::assertTrue($connection->usesAsUser());
    }

    #[Test]
    public function it_should_use_https_when_ssl_is_used(): void
    {
        $serverModel = new DirectAdminTestServer('', '', '', true, '', 1234);
        $connection = new Connection($serverModel);

        self::assertSame('https://', $connection->getProtocolString());
    }

    #[Test]
    public function it_should_use_http_when_ssl_is_not_used(): void
    {
        $serverModel = new DirectAdminTestServer('', '', '', false, '', 1234);
        $connection = new Connection($serverModel);

        self::assertSame('http://', $connection->getProtocolString());
    }

    #[Test]
    public function full_direct_admin_url_should_be_build_from_server(): void
    {
        $server = new DirectAdminTestServer('', 'test-user', '', false, 'not-safe.nl', 3214);
        $connection = new Connection($server);

        $expectedUrl = 'http://not-safe.nl:3214/';
        self::assertSame($expectedUrl, $connection->getUrl());
    }

    #[Test]
    public function use_login_key_if_set(): void
    {
        $server = new DirectAdminTestServer('my-testing-key', '', '', true, '', 1234);
        $connection = new Connection($server);
        self::assertTrue($connection->usesLoginKey());

        $server = new DirectAdminTestServer('', '', '', true, '', 1234);
        $connection = new Connection($server);
        self::assertFalse($connection->usesLoginKey());
    }
}
