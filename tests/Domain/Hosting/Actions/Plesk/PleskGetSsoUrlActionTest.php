<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Actions\Plesk;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskGetSsoUrlAction;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(PleskGetSsoUrlAction::class)]
class PleskGetSsoUrlActionTest extends IntegrationTestCase
{
    private Server $pleskServer;

    public function setUp(): void
    {
        parent::setUp();

        $this->pleskServer = new ServerFactory()->createOne([
            'hostname' => 'plesk.sso.testing',
            'use_ssl' => true,
        ]);
    }

    #[Test]
    public function getSslUrlActionPlesk(): void
    {
        $action = self::resolve(PleskGetSsoUrlAction::class);
        $ssoUrl = $action->execute($this->pleskServer, 'Test', '192.0.0.1', false);

        self::assertSame(
            'https://plesk.sso.testing:8443/enterprise/rsession_init.php?PHPSESSID=64b6f51df8b3e33875744dc1d194526f',
            $ssoUrl,
        );
    }

    #[Test]
    public function getSslUrlActionPleskMail(): void
    {
        $action = self::resolve(PleskGetSsoUrlAction::class);
        $ssoUrl = $action->execute($this->pleskServer, 'Test', '192.0.0.1', true);

        self::assertSame(
            'https://plesk.sso.testing:8443/enterprise/rsession_init.php?PHPSESSID=64b6f51df8b3e33875744dc1d194526f&success_redirect_url=%2Fsmb%2Femail-address%2Flist',
            $ssoUrl,
        );
    }
}
