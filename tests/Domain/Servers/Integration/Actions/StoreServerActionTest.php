<?php

declare(strict_types=1);

namespace Tests\Domain\Servers\Integration\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Servers\Actions\StoreServerAction;
use Waterfront\Domain\Servers\Actions\UpdateServerAction;
use Waterfront\Domain\Servers\DTO\ServerDTO;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(StoreServerAction::class)]
class StoreServerActionTest extends IntegrationTestCase
{
    private StoreServerAction $storeServerAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeServerAction = new StoreServerAction(new UpdateServerAction());
    }

    #[Test]
    public function executePersistsAllSubmittedServerAttributes(): void
    {
        $server = $this->storeServerAction->execute($this->serverData());

        self::assertTrue($server->exists);

        $persistedServer = Server::findOrFail($server->id);
        self::assertSame(ServerType::PLESK, $persistedServer->type);
        self::assertSame('new-server.nl', $persistedServer->hostname);
        self::assertSame(8443, $persistedServer->port);
        self::assertTrue($persistedServer->use_ssl);
        self::assertFalse($persistedServer->allow_new_websites);
        self::assertSame('New server', $persistedServer->name);
        self::assertSame('yourhosting', $persistedServer->owner);
        self::assertSame('1.2.3.4', $persistedServer->ipv4);
        self::assertSame('::1', $persistedServer->ipv6);
        self::assertSame('admin', $persistedServer->username);
        self::assertSame(500, $persistedServer->maximum_websites);
        self::assertSame('secret-password', $persistedServer->password);
        self::assertSame('secret-loginkey', $persistedServer->loginkey);
        self::assertSame('secret-key', $persistedServer->secret_key);
    }

    /**
     * The credential columns are encrypted through accessors, so an unwritten
     * one reads back as an empty string; the raw column is what proves nothing
     * was stored.
     */
    #[Test]
    public function executeLeavesOmittedCredentialsEmpty(): void
    {
        $server = $this->storeServerAction->execute($this->serverData(
            password: null,
            loginKey: null,
            secretKey: null,
        ));

        $persistedServer = Server::findOrFail($server->id);
        self::assertNull($persistedServer->getRawOriginal('password'));
        self::assertNull($persistedServer->getRawOriginal('loginkey'));
        self::assertNull($persistedServer->getRawOriginal('secret_key'));
    }

    #[Test]
    public function executeStoresNullableAttributesAsNull(): void
    {
        $server = $this->storeServerAction->execute(new ServerDTO(
            type: ServerType::SITEBUILDER,
            hostname: 'minimal-server.nl',
            port: 8443,
            useSsl: false,
            allowNewWebsites: true,
            name: null,
            owner: null,
            ipv4: null,
            ipv6: null,
            username: null,
            maximumWebsites: null,
            password: null,
            loginKey: null,
            secretKey: null,
        ));

        $persistedServer = Server::findOrFail($server->id);
        self::assertSame(ServerType::SITEBUILDER, $persistedServer->type);
        self::assertNull($persistedServer->name);
        self::assertNull($persistedServer->owner);
        self::assertNull($persistedServer->ipv4);
        self::assertNull($persistedServer->ipv6);
        self::assertNull($persistedServer->username);
        self::assertNull($persistedServer->maximum_websites);
        self::assertTrue($persistedServer->allow_new_websites);
    }

    private function serverData(
        ?string $password = 'secret-password',
        ?string $loginKey = 'secret-loginkey',
        ?string $secretKey = 'secret-key',
    ): ServerDTO {
        return new ServerDTO(
            type: ServerType::PLESK,
            hostname: 'new-server.nl',
            port: 8443,
            useSsl: true,
            allowNewWebsites: false,
            name: 'New server',
            owner: 'yourhosting',
            ipv4: '1.2.3.4',
            ipv6: '::1',
            username: 'admin',
            maximumWebsites: 500,
            password: $password,
            loginKey: $loginKey,
            secretKey: $secretKey,
        );
    }
}
