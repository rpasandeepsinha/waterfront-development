<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\InfrastructureController;
use Waterfront\Domain\Hosting\Factories\HostingServiceFactory;
use Waterfront\Domain\Hosting\Interfaces\Drivers\HostingServiceInterface;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(InfrastructureController::class)]
class InfrastructureControllerTest extends IntegrationTestCase
{
    #[Test]
    public function storeHostingServerCreatesTheServer(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.infrastructure.servers.hosting.store'), [
                'type' => ServerType::SITEBUILDER->value,
                'hostname' => 'new-sitebuilder.nl',
                'port' => 8443,
                'name' => 'New sitebuilder',
                'owner' => 'yourhosting',
                'ipv4' => '1.2.3.4',
                'ipv6' => '::1',
                'username' => 'admin',
                'password' => 'secret-password',
                'use_ssl' => true,
                'allow_new_websites' => false,
                'maximum_websites' => 500,
            ])
            ->assertCreated()
            ->assertJsonPath('hostname', 'new-sitebuilder.nl')
            ->assertJsonMissingPath('password');

        $server = Server::where('hostname', 'new-sitebuilder.nl')->firstOrFail();
        self::assertSame(ServerType::SITEBUILDER, $server->type);
        self::assertSame('New sitebuilder', $server->name);
        self::assertSame('admin', $server->username);
        self::assertSame('secret-password', $server->password);
        self::assertFalse($server->allow_new_websites);
        self::assertSame(500, $server->maximum_websites);
    }

    #[Test]
    public function storeHostingServerRejectsADuplicateHostname(): void
    {
        new ServerFactory()->sitebuilder()->createOne(['hostname' => 'taken.nl']);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.infrastructure.servers.hosting.store'), [
                'type' => ServerType::SITEBUILDER->value,
                'hostname' => 'taken.nl',
                'port' => 8443,
                'use_ssl' => true,
                'allow_new_websites' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hostname');
    }

    #[Test]
    public function storeHostingServerRejectsAPayloadMissingTheNonNullableFields(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.infrastructure.servers.hosting.store'), [
                'type' => ServerType::SITEBUILDER->value,
                'hostname' => 'incomplete.nl',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['port', 'use_ssl', 'allow_new_websites']);
    }

    #[Test]
    public function storeHostingServerRejectsAServerItCannotConnectTo(): void
    {
        $this->mockHostingDriver(serverIsValid: false);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.infrastructure.servers.hosting.store'), [
                'type' => ServerType::PLESK->value,
                'hostname' => 'unreachable.nl',
                'port' => 8443,
                'use_ssl' => true,
                'allow_new_websites' => true,
                'secret_key' => 'secret-key',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        self::assertNull(Server::where('hostname', 'unreachable.nl')->first());
    }

    #[Test]
    public function updateHostingServerOverwritesTheSubmittedAttributes(): void
    {
        $server = new ServerFactory()->sitebuilder()->createOne(['hostname' => 'existing.nl']);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.infrastructure.servers.hosting.update', ['server' => $server->id]), [
                'type' => ServerType::SITEBUILDER->value,
                'hostname' => 'renamed.nl',
                'port' => 8443,
                'use_ssl' => true,
                'allow_new_websites' => false,
                'name' => 'Renamed',
            ])
            ->assertOk()
            ->assertJsonPath('hostname', 'renamed.nl');

        $server->refresh();
        self::assertSame('renamed.nl', $server->hostname);
        self::assertSame('Renamed', $server->name);
        self::assertFalse($server->allow_new_websites);
    }

    #[Test]
    public function updateHostingServerKeepsStoredCredentialsWhenTheyAreSubmittedEmpty(): void
    {
        $server = new ServerFactory()->sitebuilder()->createOne([
            'hostname' => 'keep-credentials.nl',
            'password' => 'stored-password',
        ]);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.infrastructure.servers.hosting.update', ['server' => $server->id]), [
                'type' => ServerType::SITEBUILDER->value,
                'hostname' => 'keep-credentials.nl',
                'port' => 8443,
                'use_ssl' => true,
                'allow_new_websites' => true,
                'password' => '',
                'loginkey' => '',
                'secret_key' => '',
            ])
            ->assertOk();

        $server->refresh();
        self::assertSame('stored-password', $server->password);
    }

    private function mockHostingDriver(bool $serverIsValid): void
    {
        $driver = self::createStub(HostingServiceInterface::class);
        $driver->method('serverIsValid')->willReturn($serverIsValid);

        $hostingServiceFactory = self::createStub(HostingServiceFactory::class);
        $hostingServiceFactory->method('getDriverFromServer')->willReturn(ProviderSlug::PLESK);
        $hostingServiceFactory->method('driver')->willReturn($driver);

        $this->app->instance(HostingServiceFactory::class, $hostingServiceFactory);
    }
}
