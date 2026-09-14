<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CaddyRedirectDeploymentFactory;
use Tests\Factories\RedirectDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Exceptions\CaddyMapperException;
use Waterfront\Domain\Provision\Redirects\Requests\GetRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Infra\CaddyClient\CaddyClient;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteHandle;
use Waterfront\Infra\CaddyClient\DTO\RedirectRouteMatch;

#[CoversClass(GetRedirectRequest::class)]
#[CoversClass(CaddyProvisionService::class)]
class GetCaddyRedirectIntegrationTest extends IntegrationTestCase
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

    #[Test]
    public function getRedirectSuccessful(): void
    {
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $caddyId = 'caddy-id';

        new CaddyRedirectDeploymentFactory()->for(
            new RedirectDeploymentFactory()->createOne(
                [
                    'source' => $domain,
                    'context_uuid' => $context->toString(),
                ],
            ),
        )->createOne(['caddy_id' => $caddyId]);

        $request = new GetRedirectRequest(
            domainName: $domain,
            context: $context,
        );

        $this->caddyClient
            ->expects(self::once())
            ->method('getRedirect')
            ->with(
                $caddyId,
            )
            ->willReturn(new RedirectRoute(
                id: $caddyId,
                match: [
                    new RedirectRouteMatch(
                        ['yourhosting.nl'],
                        [],
                    ),
                ],
                handle: [
                    new RedirectRouteHandle(
                        handler: 'handler',
                        statusCode: 301,
                        headers: [
                            'Location' => ['https://versio.com'],
                        ],
                    ),
                ],
            ));

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);

        self::assertNull($result->exception);
        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_REDIRECT, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"domainName": "%s"}',
                $domain,
            ),
            $savedRequest->request_data,
        );

        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->status);
    }

    #[Test]
    public function getRedirectMapperException(): void
    {
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $caddyId = 'caddy-id';

        new CaddyRedirectDeploymentFactory()->for(
            new RedirectDeploymentFactory()->createOne(
                [
                    'source' => $domain,
                    'context_uuid' => $context->toString(),
                ],
            ),
        )->createOne(['caddy_id' => $caddyId]);

        $request = new GetRedirectRequest(
            domainName: $domain,
            context: $context,
        );

        $this->caddyClient
            ->expects(self::once())
            ->method('getRedirect')
            ->with(
                $caddyId,
            )
            ->willReturn(new RedirectRoute(
                id: $caddyId,
                match: [
                    new RedirectRouteMatch(),
                ],
                handle: [
                    new RedirectRouteHandle(
                        handler: 'handler',
                    ),
                ],
            ));

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);

        self::assertNotNull($result->exception);
        self::assertInstanceOf(CaddyMapperException::class, $result->exception);
        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_REDIRECT, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"domainName": "%s"}',
                $domain,
            ),
            $savedRequest->request_data,
        );

        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::FAILED, $result->status);
    }
}
