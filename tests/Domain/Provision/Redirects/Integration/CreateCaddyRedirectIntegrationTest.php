<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\Request\RequestException;
use Tests\Factories\CaddyContextFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\CaddyRedirectDeployment;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Domain\Provision\Redirects\Requests\CreateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Infra\CaddyClient\CaddyClient;
use Waterfront\Infra\CaddyClient\Enums\RedirectType as CaddyRedirectType;
use Waterfront\Infra\CaddyClient\Generators\CaddyRouteIdGenerator;

#[CoversClass(CreateRedirectRequest::class)]
#[CoversClass(CaddyProvisionService::class)]
class CreateCaddyRedirectIntegrationTest extends IntegrationTestCase
{
    private CaddyClient&MockObject $caddyClient;

    private ProvisionGateway $gateway;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->caddyClient = self::createMock(CaddyClient::class);
        $this->app->bind(CaddyClient::class, fn () => $this->caddyClient);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $this->requestRepository = self::resolve(ProvisioningRequestRepository::class);
    }

    /**
     * @param list<string>|null                $expectedPaths
     * @param array<string, list<string>>|null $expectedQuery
     */
    #[DataProvider('destinationUrlAndRedirectTypeProvider')]
    #[Test]
    public function createRedirectRequestSuccessful(
        string $domain,
        string $expectedHost,
        string $destination,
        RedirectType $redirectType,
        CaddyRedirectType $expectedCaddyType,
        ?array $expectedPaths,
        ?array $expectedQuery,
    ): void {
        $context = Uuid::uuid4();
        $caddyId = new CaddyRouteIdGenerator()->generate(
            $expectedHost,
            $expectedPaths,
            $expectedQuery,
        );

        CaddyContextFactory::new()->createOne([
            'context_uuid' => $context,
            'host' => $domain,
        ]);

        $request = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: $redirectType,
            context: $context,
        );

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                $expectedHost,
                $destination,
                $expectedCaddyType,
                $expectedPaths,
                $expectedQuery,
            )
            ->willReturn($caddyId);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(RedirectDeployment::class, 0);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(RedirectDeployment::class, 1);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 1);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);

        self::assertNull($result->exception);
        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_REDIRECT, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"domain": "%s", "redirectType": "%s", "destinationUrl": "%s"}',
                $domain,
                $redirectType->value,
                $destination,
            ),
            $savedRequest->request_data,
        );

        self::assertSame($context->toString(), $savedRequest->tag->toString());
        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->status);
        $redirectDeployment = $savedRequest->deployment;
        self::assertInstanceOf(RedirectDeployment::class, $redirectDeployment);

        self::assertSame($domain, $redirectDeployment->source);
        self::assertSame($destination, $redirectDeployment->destination);

        $caddyRedirectDeployment = $redirectDeployment->caddyRedirectDeployment;
        self::assertInstanceOf(CaddyRedirectDeployment::class, $caddyRedirectDeployment);
        self::assertSame($caddyId, $caddyRedirectDeployment->caddy_id);
    }

    #[Test]
    public function createRedirectRequestRestoresSoftDeletedContext(): void
    {
        $domain = 'yourhosting.com';
        $destination = 'https://versio.com';
        $redirectType = RedirectType::PERMANENT;
        $context = Uuid::uuid4();
        $caddyId = new CaddyRouteIdGenerator()->generate($domain);

        $caddyContext = CaddyContextFactory::new()->createOne([
            'context_uuid' => $context,
            'host' => 'old-host.example',
        ]);

        $caddyContext->delete();

        self::assertSoftDeleted('redirects_context_caddy', [
            'id' => $caddyContext->id,
            'context_uuid' => $context->toString(),
        ]);

        $request = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: $redirectType,
            context: $context,
        );

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                $domain,
                $destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
                null,
            )
            ->willReturn($caddyId);

        $result = $this->gateway->request($request);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        self::assertDatabaseHas('redirects_context_caddy', [
            'id' => $caddyContext->id,
            'context_uuid' => $context->toString(),
            'host' => $domain,
            'deleted_at' => null,
        ]);

        self::assertDatabaseCount(RedirectDeployment::class, 1);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 1);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_REDIRECT, $savedRequest->request_name);
        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($context->toString(), $savedRequest->tag->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $redirectDeployment = $savedRequest->deployment;
        self::assertInstanceOf(RedirectDeployment::class, $redirectDeployment);
        self::assertSame($domain, $redirectDeployment->source);
        self::assertSame($destination, $redirectDeployment->destination);

        $caddyRedirectDeployment = $redirectDeployment->caddyRedirectDeployment;
        self::assertInstanceOf(CaddyRedirectDeployment::class, $caddyRedirectDeployment);
        self::assertSame($caddyId, $caddyRedirectDeployment->caddy_id);
    }

    #[Test]
    public function createRedirectRequestThrowsRequestException(): void
    {
        $domain = 'yourhosting.com';
        $destination = 'versio.com';
        $redirectType = RedirectType::PERMANENT;
        $context = Uuid::uuid4();

        CaddyContextFactory::new()->createOne([
            'context_uuid' => $context,
            'host' => $domain,
        ]);

        $request = new CreateRedirectRequest(
            domain: $domain,
            destinationUrl: $destination,
            redirectType: $redirectType,
            context: $context,
        );

        $requestException = self::createStub(RequestException::class);
        $this->caddyClient->expects(self::once())->method('createRedirect')->willThrowException($requestException);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 0);
        self::assertDatabaseCount(RedirectDeployment::class, 0);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 0);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(RedirectDeployment::class, 0);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 0);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);

        self::assertNotNull($result->exception);
        self::assertSame($requestException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::CREATE_REDIRECT, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"domain": "%s", "redirectType": "%s", "destinationUrl": "%s"}',
                $domain,
                $redirectType->value,
                $destination,
            ),
            $savedRequest->request_data,
        );

        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($context->toString(), $savedRequest->tag->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::FAILED, $result->status);
    }

    /**
     * @return array<string, array{
     *     domain: string,
     *     expectedHost: string,
     *     destination: string,
     *     redirectType: RedirectType,
     *     expectedCaddyType: CaddyRedirectType,
     *     expectedPaths: list<string>|null,
     *     expectedQuery: array<string, list<string>>|null
     * }>
     */
    public static function destinationUrlAndRedirectTypeProvider(): array
    {
        return [
            'normal domain, no path, permanent' => [
                'domain' => 'yourhosting.com',
                'expectedHost' => 'yourhosting.com',
                'destination' => 'versio.com',
                'redirectType' => RedirectType::PERMANENT,
                'expectedCaddyType' => CaddyRedirectType::MOVED_PERMANENTLY,
                'expectedPaths' => null,
                'expectedQuery' => null,
            ],
            'subdomain, no path, temporary' => [
                'domain' => 'sub.yourhosting.com',
                'expectedHost' => 'sub.yourhosting.com',
                'destination' => 'https://versio.com/',
                'redirectType' => RedirectType::TEMPORARY,
                'expectedCaddyType' => CaddyRedirectType::FOUND,
                'expectedPaths' => null,
                'expectedQuery' => null,
            ],
            'deep subdomain, no path, frame' => [
                'domain' => 'shop.sub.yourhosting.com',
                'expectedHost' => 'shop.sub.yourhosting.com',
                'destination' => 'http://versio.com/?ref=yh',
                'redirectType' => RedirectType::FRAME,
                'expectedCaddyType' => CaddyRedirectType::FRAME,
                'expectedPaths' => null,
                'expectedQuery' => null,
            ],
            'different TLD, with path and slash at end, permanent' => [
                'domain' => 'yourhosting.nl/promo/',
                'expectedHost' => 'yourhosting.nl',
                'destination' => 'https://versio.com',
                'redirectType' => RedirectType::PERMANENT,
                'expectedCaddyType' => CaddyRedirectType::MOVED_PERMANENTLY,
                'expectedPaths' => ['/promo/'],
                'expectedQuery' => null,
            ],
            'normal domain with path, permanent' => [
                'domain' => 'yourhosting.com/old-page',
                'expectedHost' => 'yourhosting.com',
                'destination' => 'versio.com',
                'redirectType' => RedirectType::PERMANENT,
                'expectedCaddyType' => CaddyRedirectType::MOVED_PERMANENTLY,
                'expectedPaths' => ['/old-page'],
                'expectedQuery' => null,
            ],
            'subdomain with path, temporary' => [
                'domain' => 'sub.yourhosting.nl/promo',
                'expectedHost' => 'sub.yourhosting.nl',
                'destination' => 'https://test.versio.com/promo',
                'redirectType' => RedirectType::TEMPORARY,
                'expectedCaddyType' => CaddyRedirectType::FOUND,
                'expectedPaths' => ['/promo'],
                'expectedQuery' => null,
            ],
            'normal domain with query, permanent' => [
                'domain' => 'yourhosting.com?x=1',
                'expectedHost' => 'yourhosting.com',
                'destination' => 'https://versio.com',
                'redirectType' => RedirectType::PERMANENT,
                'expectedCaddyType' => CaddyRedirectType::MOVED_PERMANENTLY,
                'expectedPaths' => null,
                'expectedQuery' => [
                    'x' => ['1'],
                ],
            ],
            'normal domain with path and query, permanent' => [
                'domain' => 'yourhosting.com/promo?x=1',
                'expectedHost' => 'yourhosting.com',
                'destination' => 'https://versio.com',
                'redirectType' => RedirectType::PERMANENT,
                'expectedCaddyType' => CaddyRedirectType::MOVED_PERMANENTLY,
                'expectedPaths' => ['/promo'],
                'expectedQuery' => [
                    'x' => ['1'],
                ],
            ],
        ];
    }
}
