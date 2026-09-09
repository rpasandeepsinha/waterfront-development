<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Saloon\Exceptions\SaloonException;
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
use Waterfront\Domain\Provision\Redirects\Requests\SuspendRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Infra\CaddyClient\CaddyClient;

#[CoversClass(SuspendRedirectRequest::class)]
#[CoversClass(CaddyProvisionService::class)]
class SuspendCaddyRedirectsIntegrationTest extends IntegrationTestCase
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
    public function suspendRedirectsSuccessful(): void
    {
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $caddyId = 'caddy-id';

        $caddyContext = new CaddyContextFactory()
            ->createOne([
                'context_uuid' => $context->toString(),
                'host' => $domain,
            ]);

        $redirectDeployment = new RedirectDeploymentFactory()
            ->createOne([
                'source' => $domain,
                'context_uuid' => $context->toString(),
            ]);

        $caddyRedirectDeployment = new CaddyRedirectDeploymentFactory()
            ->for($redirectDeployment)
            ->createOne(['caddy_id' => $caddyId]);

        $request = new SuspendRedirectRequest(
            context: $context,
        );

        $this->caddyClient
            ->expects(self::once())
            ->method('deleteRedirect')
            ->with($caddyId);

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

        self::assertNotSoftDeleted('redirect_deployments', [
            'id' => $redirectDeployment->id,
        ]);

        self::assertNotSoftDeleted('redirects_context_caddy', [
            'id' => $caddyContext->id,
        ]);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::SUSPEND_REDIRECT, $savedRequest->request_name);
        self::assertSame('[]', $savedRequest->request_data);
        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($context->toString(), $savedRequest->tag->toString());

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->status);
    }

    #[Test]
    public function suspendRedirectsThrowsException(): void
    {
        $domain = 'yourhosting.nl';
        $context = Uuid::uuid4();
        $caddyId = 'caddy-id';

        $caddyContext = new CaddyContextFactory()
            ->createOne([
                'context_uuid' => $context->toString(),
                'host' => $domain,
            ]);

        $redirectDeployment = new RedirectDeploymentFactory()
            ->createOne([
                'source' => $domain,
                'context_uuid' => $context->toString(),
            ]);

        $caddyRedirectDeployment = new CaddyRedirectDeploymentFactory()
            ->for($redirectDeployment)
            ->createOne(['caddy_id' => $caddyId]);

        $request = new SuspendRedirectRequest(
            context: $context,
        );

        $expectedMessage = 'Failed to suspend redirect';
        $saloonException = new SaloonException($expectedMessage);

        $this->caddyClient
            ->expects(self::once())
            ->method('deleteRedirect')
            ->willThrowException($saloonException);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);

        self::assertNotNull($result->exception);
        self::assertInstanceOf(SaloonException::class, $result->exception);
        self::assertSame($expectedMessage, $result->exception->getMessage());

        self::assertDatabaseHas('redirect_deployments_caddy', [
            'id' => $caddyRedirectDeployment->id,
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
        self::assertSame(ProvisionRequestName::SUSPEND_REDIRECT, $savedRequest->request_name);
        self::assertSame('[]', $savedRequest->request_data);
        self::assertSame($context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($context->toString(), $savedRequest->tag->toString());

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::FAILED, $result->status);
    }
}
