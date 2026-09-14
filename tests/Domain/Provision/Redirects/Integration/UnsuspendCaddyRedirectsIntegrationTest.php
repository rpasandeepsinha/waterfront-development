<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\Request\RequestException;
use Tests\Factories\CaddyContextFactory;
use Tests\Factories\CaddyRedirectDeploymentFactory;
use Tests\Factories\RedirectDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Redirects\Requests\UnsuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Infra\CaddyClient\CaddyClient;
use Waterfront\Infra\CaddyClient\Enums\RedirectType as CaddyRedirectType;

#[CoversClass(UnsuspendRedirectRequest::class)]
#[CoversClass(CaddyProvisionService::class)]
class UnsuspendCaddyRedirectsIntegrationTest extends IntegrationTestCase
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
    public function unsuspendRedirectsSuccessful(): void
    {
        $domain = 'yourhosting.nl';
        $destination = 'https://versio.nl';
        $context = Uuid::uuid4();
        $restoredCaddyId = 'restored-caddy-id';

        $caddyContext = new CaddyContextFactory()->createOne([
            'context_uuid' => $context->toString(),
            'host' => $domain,
        ]);

        $redirectDeployment = new RedirectDeploymentFactory()->createOne([
            'source' => $domain,
            'destination' => $destination,
            'context_uuid' => $context->toString(),
        ]);

        $caddyRedirectDeployment = new CaddyRedirectDeploymentFactory()->for($redirectDeployment)->createOne([
            'caddy_id' => 'old-caddy-id',
        ]);

        $caddyRedirectDeployment->forceDelete();

        $request = new UnsuspendRedirectRequest(
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
            )
            ->willReturn($restoredCaddyId);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        self::assertDatabaseMissing('redirect_deployments_caddy', [
            'id' => $caddyRedirectDeployment->id,
        ]);

        self::assertDatabaseHas('redirect_deployments_caddy', [
            'redirect_deployment_id' => $redirectDeployment->id,
            'caddy_id' => $restoredCaddyId,
        ]);

        self::assertNotSoftDeleted('redirect_deployments', [
            'id' => $redirectDeployment->id,
        ]);

        self::assertNotSoftDeleted('redirects_context_caddy', [
            'id' => $caddyContext->id,
        ]);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UNSUSPEND_REDIRECT, $savedRequest->request_name);
        self::assertSame('[]', $savedRequest->request_data);
        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($context->toString(), $savedRequest->tag->toString());

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->status);
    }

    #[Test]
    public function unsuspendRedirectsThrowsException(): void
    {
        $domain = 'yourhosting.nl';
        $destination = 'https://versio.nl';
        $context = Uuid::uuid4();

        $caddyContext = new CaddyContextFactory()->createOne([
            'context_uuid' => $context->toString(),
            'host' => $domain,
        ]);

        $redirectDeployment = new RedirectDeploymentFactory()->createOne([
            'source' => $domain,
            'destination' => $destination,
            'context_uuid' => $context->toString(),
        ]);

        $caddyRedirectDeployment = new CaddyRedirectDeploymentFactory()->for($redirectDeployment)->createOne([
            'caddy_id' => 'old-caddy-id',
        ]);

        $caddyRedirectDeployment->delete();

        $request = new UnsuspendRedirectRequest(
            context: $context,
        );

        $expectedException = self::createStub(RequestException::class);

        $this->caddyClient
            ->expects(self::once())
            ->method('createRedirect')
            ->with(
                $domain,
                $destination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
            )
            ->willThrowException($expectedException);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($expectedException, $result->exception);

        self::assertDatabaseMissing('redirect_deployments_caddy', [
            'redirect_deployment_id' => $redirectDeployment->id,
            'deleted_at' => null,
        ]);

        self::assertNotSoftDeleted('redirect_deployments', [
            'id' => $redirectDeployment->id,
        ]);

        self::assertNotSoftDeleted('redirects_context_caddy', [
            'id' => $caddyContext->id,
        ]);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UNSUSPEND_REDIRECT, $savedRequest->request_name);
        self::assertSame('[]', $savedRequest->request_data);
        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($context->toString(), $savedRequest->tag->toString());

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::FAILED, $result->status);
    }
}
