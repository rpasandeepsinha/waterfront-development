<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Microsoft365\Integration;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\UuidInterface;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365AuthorizationUrlRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantAuthorizationUrlResult;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantIdResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Infra\MicrosoftOnlineClient\Connectors\MicrosoftOnlineConnector;
use Waterfront\Infra\MicrosoftOnlineClient\Exceptions\TenantNotFoundException;
use Waterfront\Infra\MicrosoftOnlineClient\Requests\GetOpenIdConfigurationRequest;

#[CoversClass(Microsoft365AuthorizationUrlRequest::class)]
#[CoversClass(Microsoft365TenantIdRequest::class)]
class TenantIntegrationTest extends IntegrationTestCase
{
    private ProvisionGateway $gateway;

    private MicrosoftOnlineConnector $microsoftOnlineClient;

    private UuidInterface $context;

    public function setUp(): void
    {
        parent::setUp();

        $this->context = Str::uuid();

        $this->microsoftOnlineClient = Container::getInstance()->make(MicrosoftOnlineConnector::class);
        $this->app->bind(MicrosoftOnlineConnector::class, fn () => $this->microsoftOnlineClient);

        $this->gateway = Container::getInstance()->make(ProvisionGateway::class);
    }

    #[Test]
    public function getTenantIdRequestSuccess(): void
    {
        $tenantId = '2d063914-d29d-4b79-a824-7dafe870343c'; // Same as in the json response
        $sandwaveTenantResponse = (string) file_get_contents(__DIR__ . '/response/openid-configuration-sandwave.json');

        $this->setupMicrosoftOnlineMockResponse($sandwaveTenantResponse);

        $request = new Microsoft365TenantIdRequest('sandwave', $this->context);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(TenantIdResult::class, $result);
        self::assertSame($tenantId, $result->tenantId);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getTenantIdRequestNotFoundError(): void
    {
        $testTenant = 'test-tenant'; // Same as in the json response
        $invalidTenantResponse = (string) file_get_contents(__DIR__ . '/response/openid-configuration-invalid.json');

        $this->setupMicrosoftOnlineMockResponse($invalidTenantResponse, 400);

        $request = new Microsoft365TenantIdRequest($testTenant, $this->context);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(TenantNotFoundException::class, $result->exception);
        self::assertSame(sprintf('Tenant "%s" does not exist.', $testTenant), $result->exception->getMessage());
        self::assertInstanceOf(TenantIdResult::class, $result);
        self::assertNull($result->tenantId);
    }

    #[Test]
    public function getAuthorizationUrlRequestSuccess(): void
    {
        // Same as in the json response
        $authorizationUrl = 'https://login.microsoftonline.com/2d063914-d29d-4b79-a824-7dafe870343c/oauth2/authorize';
        $sandwaveTenantResponse = (string) file_get_contents(__DIR__ . '/response/openid-configuration-sandwave.json');

        $this->setupMicrosoftOnlineMockResponse($sandwaveTenantResponse);

        $request = new Microsoft365AuthorizationUrlRequest('sandwave', $this->context);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(TenantAuthorizationUrlResult::class, $result);
        self::assertSame($authorizationUrl, $result->authorizationUrl);
        self::assertNull($result->exception);
    }

    #[Test]
    public function getAuthorizationUrlRequestNotFoundError(): void
    {
        $testTenant = 'test-tenant'; // Same as in the json response
        $invalidTenantResponse = (string) file_get_contents(__DIR__ . '/response/openid-configuration-invalid.json');

        $this->setupMicrosoftOnlineMockResponse($invalidTenantResponse, 400);

        $request = new Microsoft365AuthorizationUrlRequest($testTenant, $this->context);
        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(TenantNotFoundException::class, $result->exception);
        self::assertSame(sprintf('Tenant "%s" does not exist.', $testTenant), $result->exception->getMessage());
        self::assertInstanceOf(TenantAuthorizationUrlResult::class, $result);
        self::assertNull($result->authorizationUrl);
    }

    private function setupMicrosoftOnlineMockResponse(string $jsonResponse, int $statusCode = 200): void
    {
        $mockClient = new MockClient([
            GetOpenIdConfigurationRequest::class => MockResponse::make($jsonResponse, $statusCode),
        ]);

        $this->microsoftOnlineClient->withMockClient($mockClient);
    }
}
