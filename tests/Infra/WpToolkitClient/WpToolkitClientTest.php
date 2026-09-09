<?php

declare(strict_types=1);

namespace Tests\Infra\WpToolkitClient;

use GuzzleHttp\Client as guzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Infra\WpToolkitClient\Client;
use Waterfront\Infra\WpToolkitClient\DTO\ConnectionDetails;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(Client::class)]
class WpToolkitClientTest extends TestCase
{
    private string $pleskTestApiUrl;

    public function setUp(): void
    {
        parent::setUp();
        $this->pleskTestApiUrl = 'https://yh-plesk-10.dev.cldin.mock';
    }

    #[Test]
    public function tokenValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The token cannot be empty');

        new Client(
            guzzleClient: self::createStub(guzzleClient::class),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                token: ''
            )
        );
    }

    #[Test]
    public function usernamePasswordValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Password is required and cannot be empty');

        new Client(
            guzzleClient: self::createStub(guzzleClient::class),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                username: 'username'
            )
        );
    }

    #[Test]
    public function failedRequestDueTokenMismatch(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessageIsOrContains('Unauthorized');

        $mock = new MockHandler([
            new Response(401, [], '{"meta":{"status":401,"message":"Unauthorized"}}'),
        ]);

        $client = new Client(
            guzzleClient: new guzzleClient(['handler' => HandlerStack::create($mock)]),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                token: 'xxxxxx'
            )
        );

        $client->getExistingInstallations();
    }

    #[Test]
    public function failedRequestDueUsernamePasswordMismatch(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessageIsOrContains('Unauthorized');

        $mock = new MockHandler([
            new Response(401, [], '{"meta":{"status":401,"message":"Unauthorized"}}'),
        ]);

        $client = new Client(
            guzzleClient: new guzzleClient(['handler' => HandlerStack::create($mock)]),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                username: 'username',
                password: 'password'
            )
        );

        $client->getExistingInstallations();
    }

    #[Test]
    public function getExistingInstallationsSuccess(): void
    {
        /**
         * The file instances.json contains a snippet of the actual response.
         * For the current requirements we need id, domain->name.
         */
        $getInstallationsSuccessResponse = (string) file_get_contents(__DIR__ . '/data/instances.json');

        $mock = new MockHandler([
            new Response(200, [], $getInstallationsSuccessResponse),
        ]);

        $client = new Client(
            guzzleClient: new guzzleClient(['handler' => HandlerStack::create($mock)]),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                username: 'username',
                password: 'password'
            )
        );

        $responseJson = $client->getExistingInstallations();
        self::assertJson($responseJson);

        /** @var array<string, array<string,string>> $jsonArray */
        $jsonArray = json_decode($responseJson, true);
        self::assertArrayHasKey('id', $jsonArray);
        self::assertArrayHasKey('domain', $jsonArray);
        self::assertArrayHasKey('name', $jsonArray['domain']);
    }

    #[Test]
    public function getCredentialsSuccess(): void
    {
        $getCredentialsSuccessResponse = (string) file_get_contents(__DIR__ . '/data/credentials.json');
        $mock = new MockHandler([
            new Response(200, [], $getCredentialsSuccessResponse),
        ]);

        $client = new Client(
            guzzleClient: new guzzleClient(['handler' => HandlerStack::create($mock)]),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                username: 'username',
                password: 'password'
            )
        );

        $responseJson = $client->getCredentials(1908);
        self::assertJson($responseJson);

        /** @var array<string, array<string,string>> $jsonArray */
        $jsonArray = json_decode($responseJson, true);
        self::assertArrayHasKey('credentials', $jsonArray);
        self::assertArrayHasKey('login', $jsonArray['credentials']);
        self::assertArrayHasKey('password', $jsonArray['credentials']);
        self::assertArrayHasKey('loginUrl', $jsonArray);
    }

    #[Test]
    public function getCredentialsFailureDueInstanceNotExists(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessageIsOrContains('Kan de WordPress-installatie met het opgegeven kenmerk niet vinden');

        $mock = new MockHandler([
            new Response(404, [], '{"meta":{"status":404,"message":"Kan de WordPress-installatie met het opgegeven kenmerk niet vinden"}'),
        ]);

        $client = new Client(
            guzzleClient: new guzzleClient(['handler' => HandlerStack::create($mock)]),
            connectionDetails: new ConnectionDetails(
                pleskHost: $this->pleskTestApiUrl,
                username: 'username',
                password: 'test'
            )
        );

        $client->getCredentials(1908);
    }
}
