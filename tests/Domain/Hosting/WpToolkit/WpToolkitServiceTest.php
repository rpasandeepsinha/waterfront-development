<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\WpToolkit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use LogicException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\WpToolkit\DTO\WpLogin;
use Waterfront\Domain\Hosting\WpToolkit\Serializers\WpToolkitSerializerFactory;
use Waterfront\Domain\Hosting\WpToolkit\WpToolkitService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(WpToolkitService::class)]
#[AllowMockObjectsWithoutExpectations]
class WpToolkitServiceTest extends IntegrationTestCase
{
    private WpToolkitService $wordpressService;

    private LoggerInterface&MockObject $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = self::createMock(LoggerInterface::class);

        $this->wordpressService = new WpToolkitService(
            serializerFactory: self::resolve(WpToolkitSerializerFactory::class),
            logger: $this->loggerMock,
        );
    }

    #[Test]
    public function initiateClient(): void
    {
        $server = ServerFactory::new()->createOne(['secret_key' => 'gdgdgdggdgdgd']);

        $this->loggerMock
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Instantiate WpToolkitClient',
                [
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::SERVER_TYPE => $server->type,
                    LoggingContextKeys::META => [
                        'Authentication-type' => 'Token Authentication',
                    ],
                ],
            );

        $this->wordpressService->instantiateClient($server);
    }

    #[Test]
    public function initiateClientWithCredentials(): void
    {
        $server = ServerFactory::new()->createOne(
            [
                'username' => 'test',
                'password' => 'test',
            ],
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('debug')
            ->with(
                'Instantiate WpToolkitClient',
                [
                    LoggingContextKeys::SERVER_ID => $server->id,
                    LoggingContextKeys::SERVER_HOSTNAME => $server->hostname,
                    LoggingContextKeys::SERVER_TYPE => $server->type,
                    LoggingContextKeys::META => [
                        'Authentication-type' => 'User-Password Authentication',
                    ],
                ],
            );

        $this->wordpressService->instantiateClient($server);
    }

    #[Test]
    public function getWpInstallationIdClientNotInstantiated(): void
    {
        ServerFactory::new()->createOne();

        $this->expectException(LogicException::class);
        $this->wordpressService->getWpInstallationId('test-domain.nl');
    }

    #[Test]
    public function wpLoginClientNotInstantiated(): void
    {
        ServerFactory::new()->createOne();

        $this->expectException(LogicException::class);
        $this->wordpressService->getWpLogin(installationId: 1);
    }

    #[Test]
    public function getWpInstallationId(): void
    {
        $server = ServerFactory::new()->createOne(['secret_key' => 'gdgdgdggdgdgd']);

        $responseMock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/instances.json')),
        ]);

        $clientMock = new GuzzleClient(['handler' => HandlerStack::create($responseMock)]);

        $installationId = $this->wordpressService
            ->instantiateClient($server, $clientMock)
            ->getWpInstallationId('mockdomain-2.nl');

        self::assertSame(2, $installationId);
    }

    #[Test]
    public function getWpLoginSuccess(): void
    {
        $server = ServerFactory::new()->createOne(['secret_key' => 'gdgdgdggdgdgd']);

        $clientResponseMock = new MockHandler([
            new Response(
                200,
                [],
                '{"credentials": {"login": "username", "password": "password"}, "loginUrl": "https://test-domain.nl/wp-login.php"}',
            ),
        ]);

        $wpCredentials = $this->wordpressService
            ->instantiateClient(
                server: $server,
                guzzleClient: new GuzzleClient(['handler' => HandlerStack::create($clientResponseMock)]),
            )
            ->getWpLogin(installationId: 1);

        self::assertInstanceOf(WpLogin::class, $wpCredentials);
    }

    #[Test]
    public function getWpLoginFailedDueUnknownId(): void
    {
        $server = ServerFactory::new()->createOne(['secret_key' => 'gdgdgdggdgdgd']);

        $clientResponseMock = new MockHandler([
            new Response(
                404,
                [],
                '{"meta":{"status":404,"message":"Kan de WordPress-installatie met het opgegeven kenmerk niet vinden"}}',
            ),
        ]);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with('There are no credentials found for installationId');

        $credentials = $this->wordpressService
            ->instantiateClient(
                server: $server,
                guzzleClient: new GuzzleClient(['handler' => HandlerStack::create($clientResponseMock)]),
            )
            ->getWpLogin(installationId: 1);

        self::assertNull($credentials);
    }
}
