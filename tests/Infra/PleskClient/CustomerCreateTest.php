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
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Fakers\CustomerClientFaker;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\CustomerClient;

#[CoversClass(CustomerClient::class)]
#[AllowMockObjectsWithoutExpectations]
class CustomerCreateTest extends IntegrationTestCase
{
    private LoggerInterface&MockObject $loggerMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->loggerMock = self::createMock(LoggerInterface::class);
    }

    #[Test]
    public function customerCreateSendsCorrectXmlMessageAndReturnsStatusOk(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/data/plesk_customer_create_response.xml')),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(fn (callable $handler) => function (RequestInterface $request, $options) use ($handler) {
            self::assertSame(
                (string) file_get_contents(__DIR__ . '/data/plesk_customer_create_request.xml'),
                (string) $request->getBody(),
            );

            return $handler($request, $options);
        });

        $client = new Client(['handler' => $handlerStack]);
        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $customerClient = new CustomerClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: $this->loggerMock,
            connection: $connection,
        );

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);

        $result = $customerClient->createCustomer($parameters);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
    }

    #[Test]
    public function customerCreateLogMessage(): void
    {
        $connection = new Connection();
        $connection->setApiUrl('https://test');

        $client = self::createMock(Client::class);

        $customerClient = new CustomerClient(
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
                    file_get_contents(__DIR__ . '/data/plesk_customer_create_request_log.xml'),
                ),
            );

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);

        $customerClient->createCustomer($parameters);
    }

    #[Test]
    public function customerCreateSuccessWillReturnCustomer(): void
    {
        $customerClient = self::resolve(CustomerClientFaker::class);
        $customerClient->setDesiredResponseCode(200);

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);
        $result = $customerClient->createCustomer($parameters);

        self::assertArrayNotHasKey('password', $parameters->toArray(true));
        self::assertArrayHasKey('password', $parameters->toArray(false));
        self::assertSame('3', $result->getCustomerId());
    }

    #[Test]
    public function customerCreateFailureWillReturnStatusError(): void
    {
        $customerClient = self::resolve(CustomerClientFaker::class);
        $customerClient->setDesiredResponseCode(500);

        $data = require __DIR__ . '/data/pleskCreateData.php';
        $parameters = Parameters::create($data);
        $result = $customerClient->createCustomer($parameters);

        self::assertSame(500, $result->getErrorCode());
        self::assertArrayHasKey('password', $parameters->toArray(false));
        self::assertArrayNotHasKey('password', $parameters->toArray(true));
        self::assertSame(Result::STATUS_ERROR, $result->getStatus());
    }
}
