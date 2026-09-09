<?php

declare(strict_types=1);

namespace Tests\Infra\MicrosoftOnlineClient;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Http\Response;
use Tests\TestCase;
use Waterfront\Infra\MicrosoftOnlineClient\Connectors\MicrosoftOnlineConnector;
use Waterfront\Infra\MicrosoftOnlineClient\Exceptions\TenantNotFoundException;
use Waterfront\Infra\MicrosoftOnlineClient\MicrosoftOnlineClient;
use Waterfront\Infra\MicrosoftOnlineClient\Requests\GetOpenIdConfigurationRequest;
use Waterfront\Infra\MicrosoftOnlineClient\Serializers\MicrosoftOnlineSerializer;

#[CoversClass(MicrosoftOnlineClient::class)]
class MicrosoftOnlineClientTest extends TestCase
{
    #[Test]
    public function getOpenIdConfiguration(): void
    {
        $testTenant = 'yourhosting.onmicrosoft.com';

        $successRecordResponse = file_get_contents(__DIR__ . '/data/valid_tenant.json');

        $mockResponse = self::mock(Response::class);
        $mockResponse->shouldReceive('body')
            ->andReturn($successRecordResponse);

        $mockConnector = self::mock(MicrosoftOnlineConnector::class);
        $mockConnector->shouldReceive('send')
            ->once()
            ->with(GetOpenIdConfigurationRequest::class)
            ->andReturn($mockResponse);

        $microsoftOnlineClient = new MicrosoftOnlineClient($mockConnector, new MicrosoftOnlineSerializer());

        $openConfigurationId = $microsoftOnlineClient->getOpenIdConfiguration($testTenant);
        self::assertSame('https://login.microsoftonline.com/11741a99-4335-4d73-ab02-dedc34a64dbf/oauth2/authorize', $openConfigurationId->authorizationEndpoint);
    }

    #[Test]
    public function getTenantIdByTenantName(): void
    {
        $testTenant = 'yourhosting.onmicrosoft.com';

        $successRecordResponse = file_get_contents(__DIR__ . '/data/valid_tenant.json');

        $mockResponse = self::mock(Response::class);
        $mockResponse->shouldReceive('body')
            ->andReturn($successRecordResponse);

        $mockConnector = self::mock(MicrosoftOnlineConnector::class);
        $mockConnector->shouldReceive('send')
            ->once()
            ->with(GetOpenIdConfigurationRequest::class)
            ->andReturn($mockResponse);

        $microsoftOnlineClient = new MicrosoftOnlineClient($mockConnector, new MicrosoftOnlineSerializer());

        $tenantId = $microsoftOnlineClient->getTenantIdByTenantName($testTenant);
        self::assertSame('11741a99-4335-4d73-ab02-dedc34a64dbf', $tenantId);
    }

    #[Test]
    public function ifTenantNotFoundExceptionIsThrown(): void
    {
        $invalidTenant = 'invalidtenant.onmicrosoft.com';

        $errorResponse = file_get_contents(__DIR__ . '/data/invalid_tenant.json');

        $mockResponse = self::mock(Response::class);
        $mockResponse->shouldReceive('body')
            ->andReturn($errorResponse);

        $mockException = self::mock(ClientException::class);
        $mockException->shouldReceive('getResponse')
            ->andReturn($mockResponse);

        $mockConnector = self::mock(MicrosoftOnlineConnector::class);
        $mockConnector->shouldReceive('send')
            ->once()
            ->with(GetOpenIdConfigurationRequest::class)
            ->andThrow($mockException);

        $microsoftOnlineClient = new MicrosoftOnlineClient($mockConnector, new MicrosoftOnlineSerializer());

        self::expectException(TenantNotFoundException::class);
        self::expectExceptionMessageIs(sprintf('Tenant "%s" does not exist.', $invalidTenant));

        $microsoftOnlineClient->getTenantIdByTenantName($invalidTenant);
    }
}
