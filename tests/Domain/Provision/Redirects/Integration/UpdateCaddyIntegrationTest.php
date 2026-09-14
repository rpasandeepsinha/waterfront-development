<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Redirects\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Saloon\Exceptions\SaloonException;
use Tests\Factories\CaddyContextFactory;
use Tests\Factories\CaddyRedirectDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\RedirectDeploymentFactory;
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
use Waterfront\Domain\Provision\Redirects\Requests\UpdateRedirectRequest;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Provision\Redirects\Services\CaddyProvisionService;
use Waterfront\Infra\CaddyClient\CaddyClient;
use Waterfront\Infra\CaddyClient\Enums\RedirectType as CaddyRedirectType;

#[CoversClass(UpdateRedirectRequest::class)]
#[CoversClass(CaddyProvisionService::class)]
class UpdateCaddyIntegrationTest extends IntegrationTestCase
{
    private CaddyClient&MockObject $caddyClient;

    private ProvisionGateway $gateway;

    private string $domain;

    private string $originalDestination;

    private string $existingCaddyId;

    private UuidInterface $context;

    private RedirectDeployment $existingRedirectDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $this->caddyClient = self::createMock(CaddyClient::class);
        $this->app->bind(CaddyClient::class, fn () => $this->caddyClient);

        $this->gateway = self::resolve(ProvisionGateway::class);

        $this->domain = 'yourhosting.com';
        $this->originalDestination = 'old-destination.com';
        $this->existingCaddyId = 'redirect:existing-caddy-id';
        $this->context = Uuid::uuid4();

        $createProvisioningRequest = new ProvisioningRequestFactory()
            ->redirect()
            ->createOne([
                'request_name' => ProvisionRequestName::CREATE_REDIRECT,
            ]);

        $redirectContext = CaddyContextFactory::new()->createOne([
            'context_uuid' => $this->context->toString(),
            'host' => $this->domain,
        ]);

        $this->existingRedirectDeployment = RedirectDeploymentFactory::new()->withTemporary()->createOne([
            'origin_provisioning_request_id' => $createProvisioningRequest->id,
            'source' => $this->domain,
            'destination' => $this->originalDestination,
            'context_uuid' => $redirectContext->context_uuid,
        ]);

        CaddyRedirectDeploymentFactory::new()->for($this->existingRedirectDeployment)->createOne([
            'caddy_id' => $this->existingCaddyId,
        ]);
    }

    #[Test]
    public function updateRedirectRequestSuccessful(): void
    {
        $newSource = 'donkey.yourhosting.com';
        $updatedDestination = 'versio.com';
        $redirectType = RedirectType::PERMANENT;

        $request = new UpdateRedirectRequest(
            oldSource: $this->domain,
            newSource: $newSource,
            destinationUrl: $updatedDestination,
            redirectType: $redirectType,
            context: $this->context,
        );

        $this->caddyClient
            ->expects(self::once())
            ->method('updateRedirect')
            ->with(
                $this->existingCaddyId,
                $newSource,
                $updatedDestination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
            )
            ->willReturn($this->existingCaddyId);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(RedirectDeployment::class, 1);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);
        self::assertDatabaseCount(RedirectDeployment::class, 1);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 1);

        self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);

        /** @var ProvisioningRequest|null $savedRequest */
        $savedRequest = ProvisioningRequest::query()->find($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UPDATE_REDIRECT, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"newSource": "%s", "oldSource": "%s", "redirectType": "%s", "destinationUrl": "%s"}',
                $newSource,
                $this->domain,
                $redirectType->value,
                $updatedDestination,
            ),
            $savedRequest->request_data,
        );

        self::assertSame($this->context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($this->context->toString(), $savedRequest->tag->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::SUCCESS, $result->status);

        $redirectDeployment = $savedRequest->deployment;
        self::assertInstanceOf(RedirectDeployment::class, $redirectDeployment);

        self::assertSame($this->existingRedirectDeployment->id, $redirectDeployment->id);
        self::assertSame($newSource, $redirectDeployment->source);
        self::assertSame($updatedDestination, $redirectDeployment->destination);
        self::assertSame(RedirectType::PERMANENT, $redirectDeployment->type);

        self::assertNotNull($redirectDeployment->caddyRedirectDeployment);
        self::assertSame($this->existingCaddyId, $redirectDeployment->caddyRedirectDeployment->caddy_id);
    }

    #[Test]
    public function updateRedirectRequestThrowsRequestException(): void
    {
        $newSource = 'donkey.yourhosting.com';
        $updatedDestination = 'versio.com';
        $redirectType = RedirectType::PERMANENT;

        $request = new UpdateRedirectRequest(
            oldSource: $this->domain,
            newSource: $newSource,
            destinationUrl: $updatedDestination,
            redirectType: $redirectType,
            context: $this->context,
        );

        $requestException = new SaloonException('Failed to update redirect');
        $this->caddyClient
            ->expects(self::once())
            ->method('updateRedirect')
            ->with(
                $this->existingCaddyId,
                $newSource,
                $updatedDestination,
                CaddyRedirectType::MOVED_PERMANENTLY,
                null,
            )
            ->willThrowException($requestException);

        self::assertDatabaseCount(ProvisioningResult::class, 0);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);
        self::assertDatabaseCount(RedirectDeployment::class, 1);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);
        self::assertDatabaseCount(RedirectDeployment::class, 1);
        self::assertDatabaseCount(CaddyRedirectDeployment::class, 1);

        //self::assertInstanceOf(RedirectResult::class, $result);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertSame($requestException, $result->exception);

        /** @var ProvisioningRequest|null $savedRequest */
        $savedRequest = ProvisioningRequest::query()->find($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertSame(ProvisionType::REDIRECT, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UPDATE_REDIRECT, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"newSource": "%s", "oldSource": "%s", "redirectType": "%s", "destinationUrl": "%s"}',
                $newSource,
                $this->domain,
                $redirectType->value,
                $updatedDestination,
            ),
            $savedRequest->request_data,
        );

        self::assertSame($this->context->toString(), $savedRequest->context_uuid?->toString());
        self::assertSame($this->context->toString(), $savedRequest->tag->toString());
        self::assertSame(ProvisionProvider::CADDY, $savedRequest->provision_provider);

        $result = $savedRequest->result;

        self::assertNotNull($result);
        self::assertSame(ProvisionStatus::FAILED, $result->status);

        self::assertNull($savedRequest->deployment);

        /** @var RedirectDeployment|null $persistedDeployment */
        $persistedDeployment = RedirectDeployment::query()
            ->where('source', $this->domain)
            ->where('context_uuid', $this->context->toString())
            ->first();

        self::assertInstanceOf(RedirectDeployment::class, $persistedDeployment);
        self::assertSame($this->existingRedirectDeployment->id, $persistedDeployment->id);
        self::assertSame($this->originalDestination, $persistedDeployment->destination);
        self::assertSame(RedirectType::TEMPORARY, $persistedDeployment->type);

        self::assertNotNull($persistedDeployment->caddyRedirectDeployment);
        self::assertSame($this->existingCaddyId, $persistedDeployment->caddyRedirectDeployment->caddy_id);
    }
}
