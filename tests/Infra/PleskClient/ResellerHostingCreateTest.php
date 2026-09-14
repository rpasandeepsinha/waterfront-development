<?php

declare(strict_types=1);

namespace Tests\Infra\PleskClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\ResellerHosting\Parameters\ResellerHostingParameters;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PleskClient\Exceptions\PleskClientException;
use Waterfront\Infra\PleskClient\Messages\Connection;
use Waterfront\Infra\PleskClient\Services\ResellerHostingClient;

#[CoversClass(ResellerHostingClient::class)]
class ResellerHostingCreateTest extends IntegrationTestCase
{
    #[Test]
    public function resellerCreateWrongServerType(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_resellerhosting_create_response.xml');

        if ($xmlMessage === false) {
            throw new PleskClientException('Could not load plesk reseller xml response');
        }

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                $xmlMessage,
            ),
        ]));
        $stack->push($history);
        $client = new Client(
            [
                'http_errors' => true,
                'handler' => $stack,
            ],
        );

        $server = new ServerFactory()->createOne([
            'name' => 'localhost',
            'username' => 'Secret',
            'password' => 'secret',
            'type' => ServerType::DIRECTADMIN,
        ]);

        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new ResellerHostingClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $setServer = $pleskClient->setServer($server, ['username' => 'Secret', 'password', 'secret']);

        self::assertFalse($setServer);
    }

    #[Test]
    public function resellerHostingCreateOutOfResources(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_resellerhosting_create_no-resources_response.xml');

        if ($xmlMessage === false) {
            throw new PleskClientException('Could not load plesk reseller xml response');
        }

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                $xmlMessage,
            ),
        ]));

        $stack->push($history);
        $client = new Client(
            [
                'http_errors' => true,
                'handler' => $stack,
            ],
        );

        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new ResellerHostingClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $parameters = new ResellerHostingParameters(
            contactPerson: 'Reseller tester',
            username: 'resellerTest',
            password: 'V5%7et6v',
            email: 'test@email.com',
            domain: null,
            ipv4Address: null,
            ipv6Address: null,
            packageName: null,
            resellerHostingId: null,
            providerId: 1,
        );

        $this->expectException(PleskClientException::class);
        $this->expectExceptionCode(1024);

        $pleskClient->createResellerHosting($parameters);
    }

    #[Test]
    public function invalidAdminCredentials(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_resellerhosting_create_invalid-adminlogin_response.xml');

        if ($xmlMessage === false) {
            throw new PleskClientException('Could not load plesk reseller xml response');
        }

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                $xmlMessage,
            ),
        ]));

        $stack->push($history);
        $client = new Client(
            [
                'http_errors' => true,
                'handler' => $stack,
            ],
        );

        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new ResellerHostingClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $parameters = new ResellerHostingParameters(
            contactPerson: 'Reseller tester',
            username: 'resellerTest',
            password: 'V5%7et6v',
            email: 'test@email.com',
            domain: null,
            ipv4Address: null,
            ipv6Address: null,
            packageName: null,
            resellerHostingId: null,
            providerId: 1,
        );

        $this->expectException(PleskClientException::class);
        $this->expectExceptionCode(1001);

        $pleskClient->createResellerHosting($parameters);
    }

    /**
     * @throws PleskClientException
     */
    #[Test]
    public function resellerHostingCreateSuccess(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_resellerhosting_create_response.xml');

        if ($xmlMessage === false) {
            throw new PleskClientException('Could not load plesk reseller xml response');
        }

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                $xmlMessage,
            ),
        ]));

        $stack->push($history);
        $client = new Client(
            [
                'http_errors' => true,
                'handler' => $stack,
            ],
        );

        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new ResellerHostingClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $parameters = new ResellerHostingParameters(
            contactPerson: 'Reseller tester',
            username: 'resellerTest',
            password: 'V5%7et6v',
            email: 'test@email.com',
            domain: null,
            ipv4Address: null,
            ipv6Address: null,
            packageName: null,
            resellerHostingId: null,
            providerId: 1,
        );

        $response = $pleskClient->createResellerHosting($parameters);

        self::assertSame(3, $response->getResellerId());
    }

    #[Test]
    public function resellerHostingSetSpecsUnknownReseller(): void
    {
        $container = [];
        $history = Middleware::history($container);

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_resellerhosting_setspecs_unknownreseller_response.xml');

        if ($xmlMessage === false) {
            throw new PleskClientException('Could not load plesk reseller xml response');
        }

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                $xmlMessage,
            ),
        ]));

        $stack->push($history);
        $client = new Client(
            [
                'http_errors' => true,
                'handler' => $stack,
            ],
        );

        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new ResellerHostingClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $parameters = new ResellerHostingParameters(
            contactPerson: 'Reseller tester',
            username: 'resellerTest',
            password: 'V5%7et6v',
            email: 'test@email.com',
            domain: null,
            ipv4Address: null,
            ipv6Address: null,
            packageName: null,
            // The reseller ID should correspond to the one from the error response.
            resellerHostingId: 12345,
            providerId: 1,
        );

        $this->expectException(PleskClientException::class);
        $this->expectExceptionCode(1013);

        $pleskClient->setResellerHostingSpecs($parameters);
    }

    /**
     * @throws PleskClientException
     */
    #[Test]
    public function resellerHostingSetSpecsSuccess(): void
    {
        $this->expectNotToPerformAssertions();

        $container = [];
        $history = Middleware::history($container);

        $xmlMessage = file_get_contents(__DIR__ . '/data/plesk_resellerhosting_setspecs_success_response.xml');

        if ($xmlMessage === false) {
            throw new PleskClientException('Could not load plesk reseller xml response');
        }

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                $xmlMessage,
            ),
        ]));

        $stack->push($history);
        $client = new Client(
            [
                'http_errors' => true,
                'handler' => $stack,
            ],
        );

        $connection = new Connection();
        $connection->setApiUrl('https://test');
        $pleskClient = new ResellerHostingClient(
            client: $client,
            configuration: self::resolve(ConfigurationInterface::class),
            logger: self::resolve(LoggerInterface::class),
            connection: $connection,
        );

        $parameters = new ResellerHostingParameters(
            contactPerson: 'Reseller tester',
            username: 'resellerTest',
            password: 'V5%7et6v',
            email: 'test@email.com',
            domain: null,
            ipv4Address: null,
            ipv6Address: null,
            packageName: null,
            resellerHostingId: 2,
            providerId: 1,
        );

        $pleskClient->setResellerHostingSpecs($parameters);
    }
}
