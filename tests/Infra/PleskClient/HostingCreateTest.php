<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\HostingPackageClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\HostingPackageClient;

#[CoversClass(HostingPackageClient::class)]
#[AllowMockObjectsWithoutExpectations]
class HostingCreateTest extends IntegrationTestCase
{
    private LoggerInterface&MockObject $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = self::createMock(LoggerInterface::class);
    }

    #[Test]
    public function createHostingSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_hosting_create_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_hosting_create_response.xml'),
                );
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: $this->loggerMock,
            connection: $connection,
        );

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);

        $result = $customerClient->createHosting($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function hostingCreateLogMessage(): void
    {
        $connection = new Connection();
        $connection->setApiUrl('https://test');

        $client = self::createStub(Client::class);

        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: $this->loggerMock,
            connection: $connection,
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('debug')
            ->with(
                sprintf(
                    'Sending Plesk request: %s',
                    file_get_contents(__DIR__ . '/data/plesk_hosting_create_request_log.xml'),
                ),
            );

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);

        $customerClient->createHosting($parameters);
    }

    #[Test]
    public function createMailOnlyHostingSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            function (RequestInterface $request) {
                self::assertSame(
                    (string) file_get_contents(__DIR__ . '/data/plesk_mailonly_hosting_create_request.xml'),
                    (string) $request->getBody(),
                );

                return new Response(
                    200,
                    [],
                    (string) file_get_contents(__DIR__ . '/data/plesk_hosting_create_response.xml'),
                );
            },
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);
        $parameters->setMailOnlyHosting(true);

        $result = $customerClient->createHosting($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function mailOnlyHostingCreateLogMessage(): void
    {
        $connection = new Connection();
        $connection->setApiUrl('https://test');

        $client = self::createStub(Client::class);

        $customerClient = new HostingPackageClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: $this->loggerMock,
            connection: $connection,
        );

        $this->loggerMock
            ->expects(self::once())
            ->method('debug')
            ->with(
                sprintf(
                    'Sending Plesk request: %s',
                    file_get_contents(__DIR__ . '/data/plesk_mailonly_hosting_create_request_log.xml'),
                ),
            );

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);
        $parameters->setMailOnlyHosting(true);

        $customerClient->createHosting($parameters);
    }

    #[Test]
    public function hostingCreateSuccess(): void
    {
        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $hostingPackageClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);
        $result = $hostingPackageClient->createHosting($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function hostingCreateFailure(): void
    {
        $hostingPackageClient = self::resolve(HostingPackageClientFaker::class);
        $hostingPackageClient->setDesiredResponseCode(500);

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);
        $result = $hostingPackageClient->createHosting($parameters);

        self::assertSame(500, $result->getErrorCode());
        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
