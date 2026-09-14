<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions\Directadmin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Actions\DirectAdmin\DirectAdminGetSsoUrlAction;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;

#[CoversClass(DirectAdminGetSsoUrlAction::class)]
class DirectadminGetSsoUrlActionTest extends IntegrationTestCase
{
    private Server $directadminServer;

    public function setUp(): void
    {
        parent::setUp();

        $this->directadminServer = new ServerFactory()
            ->directadmin()
            ->createOne([
                'hostname' => 'directadmin.sso.testing',
                'domain' => 'directadmin.sso.testing',
                'name' => 'username123',
                'port' => 1337,
                'use_ssl' => true,
            ]);
    }

    #[Test]
    public function getSslUrlActionDirectadmin(): void
    {
        $expectedUrl = 'https://directadmin.sso.testing:1337/login-hash';

        $mockClient = self::createMock(DirectAdminClient::class);
        $mockClient->expects(self::once())->method('createLoginUrl')->willReturn($expectedUrl);

        $action = new DirectAdminGetSsoUrlAction($mockClient);
        $ssoUrl = $action->execute($this->directadminServer, 'Test');

        self::assertSame($expectedUrl, $ssoUrl);
    }
}
