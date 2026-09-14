<?php

declare(strict_types=1);

namespace Tests\Infra\DirectAdminJsonClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Response;
use Tests\TestCase;
use Waterfront\Infra\DirectAdminJsonClient\Connectors\DirectAdminConnector;
use Waterfront\Infra\DirectAdminJsonClient\DirectAdminClient;
use Waterfront\Infra\DirectAdminJsonClient\DTO\DirectAdminServer;
use Waterfront\Infra\DirectAdminJsonClient\Requests\CreateLoginUrl;
use Waterfront\Infra\DirectAdminJsonClient\Serializers\DirectAdminSerializer;

#[CoversClass(DirectAdminClient::class)]
class DirectAdminClientTest extends TestCase
{
    #[Test]
    public function createLoginUrl(): void
    {
        $server = new DirectAdminServer(
            baseUrl: 'da-server.nl',
            username: 'testuser',
            password: 'testpassword',
            port: 2222,
        );

        $expectedUrl = 'https://da-server.nl:2222/api/login/url?key=NHJqMEGow7XaloazXC_F07CF_dHSHy7O'; // Same in the json
        $successUrlResponse = file_get_contents(__DIR__ . '/data/success-login-url.json');

        $mockResponse = self::mock(Response::class);
        $mockResponse->shouldReceive('body')->andReturn($successUrlResponse);

        $mockConnector = self::mock(DirectAdminConnector::class);
        $mockConnector
            ->shouldReceive('sendWithServer')
            ->once()
            ->with($server, CreateLoginUrl::class)
            ->andReturn($mockResponse);

        $da = new DirectAdminClient($mockConnector, new DirectAdminSerializer());

        $ssoUrl = $da->createLoginUrl($server);

        self::assertSame($expectedUrl, $ssoUrl);
    }
}
