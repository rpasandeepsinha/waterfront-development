<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Actions\BaseKit\BaseKitGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\BehavesAsDirectAdmin;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;

#[CoversClass(GetSsoUrlAction::class)]
class GetSsoUrlActionTest extends IntegrationTestCase
{
    private Server $pleskServer;

    private Server $directadminServer;

    private Server $sitebuilderServer;

    public function setUp(): void
    {
        parent::setUp();

        $this->pleskServer = new ServerFactory()->plesk()->createOne([
            'hostname' => 'plesk.sso.testing',
        ]);

        $this->directadminServer = new ServerFactory()->directadmin()->createOne([
            'hostname' => 'directadmin.sso.testing',
        ]);

        $this->sitebuilderServer = new ServerFactory()->sitebuilder()->createOne([
            'hostname' => 'https://sitebuilder.sso.testing/',
        ]);
    }

    #[Test]
    public function getSslUrlActionPleskThrowsSsoResolveException(): void
    {
        $sessionTokenInterface = self::mock(SessionTokenInterface::class);
        $sessionTokenInterface->shouldReceive('setServer')->andReturns();
        $sessionTokenInterface->shouldReceive('getSsoUrl')->andReturn('');

        self::expectException(SsoResolveException::class);

        $action = self::resolve(GetSsoUrlAction::class);
        $action->execute($this->pleskServer, 'Test');
    }

    #[Test]
    public function getSslUrlActionDirectadminThrowsSsoResolveException(): void
    {
        $mockClient = self::createMock(DirectAdminClient::class);
        $mockClient->expects(self::once())
            ->method('createLoginUrl')
            ->willReturn('');

        $this->app->bind(DirectAdminClient::class, fn () => $mockClient);

        self::expectException(SsoResolveException::class);

        $action = self::resolve(GetSsoUrlAction::class);
        $action->execute($this->directadminServer, 'Test');
    }

    #[Test]
    public function getSsoUrlActionNoUsernameSuppliedException(): void
    {
        $directAdminMock = self::mock(BehavesAsDirectAdmin::class);
        $directAdminMock->shouldReceive('useServer')->andReturns();
        $directAdminMock->shouldReceive('getSSO')->never();

        self::expectException(SsoResolveException::class);

        $action = self::resolve(GetSsoUrlAction::class);
        $action->execute($this->directadminServer, '');
    }

    #[Test]
    public function getSsoUrlActionSitebuilderThrowsSsoResolveException(): void
    {
        $baseKitActionMock = self::mock(BaseKitGetSsoUrlAction::class);
        $baseKitActionMock->shouldReceive('execute')->andReturns('');

        self::expectException(SsoResolveException::class);

        $action = self::resolve(GetSsoUrlAction::class);
        $action->execute($this->sitebuilderServer, username: '123', siteRef: '456');
    }
}
