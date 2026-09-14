<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Plesk;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\HostingPackageInterface;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SessionTokenInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ChainableHostingPackageClient;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\ClientListStrategyInterface;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(ChainableHostingPackageClient::class)]
#[AllowMockObjectsWithoutExpectations]
class ChainableHostingPackageClientTest extends TestCase
{
    private ChainableHostingPackageClient $testItem;

    private HostingPackageInterface&MockObject $client;

    private SessionTokenInterface&MockObject $ssoClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = self::createMock(HostingPackageInterface::class);
        $this->ssoClient = self::createMock(SessionTokenInterface::class);
        $hostingStrategy = self::createMock(ClientListStrategyInterface::class);
        $hostingStrategy->method('selectClient')->willReturn($this->client);

        $ssoStrategy = self::createMock(ClientListStrategyInterface::class);
        $ssoStrategy->method('selectClient')->willReturn($this->ssoClient);

        $otherStrategy = self::createMock(ClientListStrategyInterface::class);

        $this->testItem = new ChainableHostingPackageClient(
            $hostingStrategy,
            $otherStrategy,
            $otherStrategy,
            $otherStrategy,
            $ssoStrategy,
            $otherStrategy,
        );
    }

    #[Test]
    public function createHosting(): void
    {
        $server = new Server();
        self::assertTrue($this->testItem->setServer($server, []));

        $parameters = Parameters::create(
            [
                'contactPersonName' => 'not used for EAT',
                'emailAddress' => 'not-used@on-the-eat-server.nl',
                'domain' => 'justeat.nl',
                'ipv4Address' => '1.2.3.4',
            ],
        );
        $this->client->expects(self::atLeastOnce())->method('createHosting');

        $this->testItem->createHosting($parameters);
    }

    #[Test]
    public function getSsoUrl(): void
    {
        $server = new Server();
        self::assertTrue($this->testItem->setServer($server, []));

        $this->ssoClient->expects(self::atLeastOnce())->method('getSsoUrl');

        $this->testItem->getSsoUrl('test-user', '127.0.0.1', false);
    }
}
