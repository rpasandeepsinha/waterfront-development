<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Actions;

use Illuminate\Support\Collection;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Tests\Factories\DomainNameCoupleDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Actions\DomainNameCoupleAction;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameCoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameCoupleResult;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\UnknownProvisionTypeException;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainNameCoupleAction::class)]
class DomainNameCoupleActionTest extends IntegrationTestCase
{
    private ProvisionGateway&MockInterface $mockGateway;

    private LoggerInterface&MockInterface $mockLogger;

    private DomainNameCoupleAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGateway = self::mock(ProvisionGateway::class);
        $this->mockLogger = self::mock(LoggerInterface::class);

        $this->action = new DomainNameCoupleAction(
            $this->mockGateway,
            $this->mockLogger,
        );
    }

    #[Test]
    public function coupleDomainAction(): void
    {
        $domain = 'example.com';

        $subscription = new Subscription();
        $subscription->domain = $domain;
        $subscription->uuid = Uuid::uuid4()->toString();

        $provisioningResult = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        $provisioningFilteredResult = new ProvisioningFilteredResult(
            resultId: $provisioningResult->id,
            uuid: Uuid::uuid4(),
            tag: Uuid::fromString($subscription->uuid),
            context: null,
            response: '{}',
            status: ProvisionStatus::SUCCESS,
            createdAt: null,
            requestCreatedAt: null,
            requestUpdatedAt: null,
            requestData: '{}',
            requestUuid: $provisioningResult->provisioningRequest->uuid,
            requestName: ProvisionRequestName::CREATE_HOSTING,
            requestType: ProvisionType::HOSTING,
            provider: ProvisionProvider::DIRECTADMIN,
        );

        $expectedDeploymentUuid = Uuid::uuid4();

        DomainNameCoupleDeploymentFactory::new()->hostingCoupling()->createOne([
            'domain' => $domain,
            'origin_provisioning_request_id' => $provisioningResult->request_id,
            'deployment_uuid' => $expectedDeploymentUuid,
        ]);

        $result = new DomainNameCoupleResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
        );

        $this->mockGateway
            ->shouldReceive('fetch')
            ->once()
            ->withArgs(
                fn (ProvisioningResultQueryFilters $filters, int $limit) => (
                    $filters->tag?->toString() === $subscription->uuid
                    && $limit === 1
                ),
            )
            ->andReturn(new Collection([$provisioningFilteredResult]));

        $this->mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(
                fn (DomainNameCoupleRequest $request) => (
                    $request->domain === $domain
                    && $request->requestUuid->equals($provisioningFilteredResult->requestUuid)
                ),
            )
            ->andReturn($result);

        $this->mockLogger->shouldNotReceive('warning');

        $this->action->execute($domain, $subscription);
    }

    #[Test]
    public function executeThrowsExceptionWhenProvisionFailed(): void
    {
        $domain = 'example.com';
        $exception = new UnknownProvisionTypeException(ProvisionType::HOSTING);

        $subscription = new Subscription();
        $subscription->id = 1337;
        $subscription->domain = $domain;
        $subscription->uuid = Uuid::uuid4()->toString();

        $provisioningResult = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        $provisioningFilteredResult = new ProvisioningFilteredResult(
            resultId: $provisioningResult->id,
            uuid: Uuid::uuid4(),
            tag: Uuid::fromString($subscription->uuid),
            context: null,
            response: '{}',
            status: ProvisionStatus::SUCCESS,
            createdAt: null,
            requestCreatedAt: null,
            requestUpdatedAt: null,
            requestData: '{}',
            requestUuid: $provisioningResult->provisioningRequest->uuid,
            requestName: ProvisionRequestName::CREATE_HOSTING,
            requestType: ProvisionType::HOSTING,
            provider: ProvisionProvider::DIRECTADMIN,
        );

        $expectedDeploymentUuid = Uuid::uuid4();

        $domainNameCoupleDeployment = DomainNameCoupleDeploymentFactory::new()->hostingCoupling()->createOne([
            'domain' => $domain,
            'origin_provisioning_request_id' => $provisioningResult->request_id,
            'deployment_uuid' => $expectedDeploymentUuid,
        ]);

        $result = new DomainNameCoupleResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::FAILED,
            exception: $exception,
        );

        $this->mockGateway
            ->shouldReceive('fetch')
            ->once()
            ->withArgs(
                fn (ProvisioningResultQueryFilters $filters, int $limit) => (
                    $filters->tag?->toString() === $subscription->uuid
                    && $limit === 1
                ),
            )
            ->andReturn(new Collection([$provisioningFilteredResult]));

        $this->mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(
                fn (DomainNameCoupleRequest $request) => (
                    $request->domain === $domain
                    && $request->requestUuid->equals($provisioningFilteredResult->requestUuid)
                ),
            )
            ->andReturn($result);

        $this->mockLogger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'Domain couple action domain {domain.name} failed.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'couple_request_uuid' => $domainNameCoupleDeployment->request->uuid->toString(),
                    ],
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

        $this->expectException(DomainNameCoupleActionException::class);
        $this->expectExceptionMessageIs(sprintf('Domain couple action failed for domain [%s]', $domain));

        $this->action->execute($domain, $subscription);
    }

    #[Test]
    public function executeThrowsExceptionWithoutProvisionData(): void
    {
        $domain = 'example.com';
        $subscription = new Subscription();
        $subscription->uuid = Uuid::uuid4()->toString();

        $this->mockGateway
            ->shouldReceive('fetch')
            ->once()
            ->withArgs(
                fn (ProvisioningResultQueryFilters $filters, int $limit) => (
                    $filters->tag?->toString() === $subscription->uuid
                    && $limit === 1
                ),
            )
            ->andReturn(new Collection());

        $this->mockLogger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'No provisioning data found during domain coupling for subscription {subscription.id} with UUID {subscription.uuid}.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ],
            );

        $this->expectException(DomainNameCoupleActionException::class);
        $this->expectExceptionMessageIs(sprintf('No provisioning data found for subscription %s', $subscription->uuid));

        $this->action->execute($domain, $subscription);
    }
}
