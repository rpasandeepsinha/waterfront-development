<?php

declare(strict_types=1);

namespace Tests\Domain\Servers\Integration\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Servers\Actions\UpdateServerAction;
use Waterfront\Domain\Servers\DTO\ServerDTO;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(UpdateServerAction::class)]
class UpdateServerActionTest extends IntegrationTestCase
{
    private UpdateServerAction $updateServerAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateServerAction = new UpdateServerAction();
    }

    #[Test]
    public function executeOverwritesTheSubmittedAttributes(): void
    {
        $server = new ServerFactory()->plesk()->createOne();

        $this->updateServerAction->execute($server, $this->serverData());

        $persistedServer = Server::findOrFail($server->id);
        self::assertSame(ServerType::DIRECTADMIN, $persistedServer->type);
        self::assertSame('renamed-server.nl', $persistedServer->hostname);
        self::assertSame(2222, $persistedServer->port);
        self::assertFalse($persistedServer->use_ssl);
        self::assertFalse($persistedServer->allow_new_websites);
        self::assertSame('Renamed server', $persistedServer->name);
        self::assertSame('yourhosting', $persistedServer->owner);
        self::assertSame('4.3.2.1', $persistedServer->ipv4);
        self::assertSame('::2', $persistedServer->ipv6);
        self::assertSame('renamed-admin', $persistedServer->username);
        self::assertSame(250, $persistedServer->maximum_websites);
    }

    #[Test]
    public function executeKeepsStoredCredentialsWhenNoneAreSubmitted(): void
    {
        $server = new ServerFactory()->directadmin()->createOne(['secret_key' => 'stored-secret-key']);

        $this->updateServerAction->execute($server, $this->serverData(
            password: null,
            loginKey: null,
            secretKey: null,
        ));

        $persistedServer = Server::findOrFail($server->id);
        self::assertSame('password12345', $persistedServer->password);
        self::assertSame('loginkey12345', $persistedServer->loginkey);
        self::assertSame('stored-secret-key', $persistedServer->secret_key);
    }

    #[Test]
    public function executeReplacesStoredCredentialsWhenNewOnesAreSubmitted(): void
    {
        $server = new ServerFactory()->directadmin()->createOne(['secret_key' => 'stored-secret-key']);

        $this->updateServerAction->execute($server, $this->serverData(
            password: 'rotated-password',
            loginKey: 'rotated-loginkey',
            secretKey: 'rotated-secret-key',
        ));

        $persistedServer = Server::findOrFail($server->id);
        self::assertSame('rotated-password', $persistedServer->password);
        self::assertSame('rotated-loginkey', $persistedServer->loginkey);
        self::assertSame('rotated-secret-key', $persistedServer->secret_key);
    }

    #[Test]
    public function executeClearsAttributesThatAreSubmittedEmpty(): void
    {
        $server = new ServerFactory()->plesk()->createOne();

        $this->updateServerAction->execute($server, new ServerDTO(
            type: ServerType::PLESK,
            hostname: 'cleared-server.nl',
            port: 8443,
            useSsl: true,
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
        self::assertNull($persistedServer->name);
        self::assertNull($persistedServer->owner);
        self::assertNull($persistedServer->ipv4);
        self::assertNull($persistedServer->ipv6);
        self::assertNull($persistedServer->username);
        self::assertNull($persistedServer->maximum_websites);
    }

    private function serverData(
        ?string $password = 'secret-password',
        ?string $loginKey = 'secret-loginkey',
        ?string $secretKey = 'secret-key',
    ): ServerDTO {
        return new ServerDTO(
            type: ServerType::DIRECTADMIN,
            hostname: 'renamed-server.nl',
            port: 2222,
            useSsl: false,
            allowNewWebsites: false,
            name: 'Renamed server',
            owner: 'yourhosting',
            ipv4: '4.3.2.1',
            ipv6: '::2',
            username: 'renamed-admin',
            maximumWebsites: 250,
            password: $password,
            loginKey: $loginKey,
            secretKey: $secretKey,
        );
    }
}
