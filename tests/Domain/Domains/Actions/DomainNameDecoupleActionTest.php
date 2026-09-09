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
use Waterfront\Domain\Domains\Actions\DomainNameDecoupleAction;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameDecoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameDecoupleResult;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Exceptions\UnknownHostingProviderException;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainNameDecoupleAction::class)]
class DomainNameDecoupleActionTest extends IntegrationTestCase
{
    private ProvisionGateway&MockInterface $mockGateway;

    private LoggerInterface&MockInterface $mockLogger;

    private DomainNameDecoupleAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGateway = self::mock(ProvisionGateway::class);
        $this->mockLogger = self::mock(LoggerInterface::class);

        $this->action = new DomainNameDecoupleAction(
            $this->mockGateway,
            $this->mockLogger,
        );
    }

    #[Test]
    public function decoupleDomainAction(): void
    {
        $domain = 'example.com';

        $subscription = new Subscription();
        $subscription->domain = 'hosting-subscription.com';
        $subscription->uuid = Uuid::uuid4()->toString();

        $provisioningResult = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        DomainNameCoupleDeploymentFactory::new()
            ->hostingCoupling()
            ->createOne(['origin_provisioning_request_id' => $provisioningResult->request_id]);

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

        $result = self::createStub(DomainNameDecoupleResult::class);
        $result->provisionStatus = ProvisionStatus::SUCCESS;

        $this->mockGateway->shouldReceive('fetch')
            ->once()
            ->withArgs(fn (ProvisioningResultQueryFilters $filters, int $limit) => $filters->tag?->toString() === $subscription->uuid && $limit === 1)
            ->andReturn(new Collection([$provisioningFilteredResult]));

        $this->mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(fn (DomainNameDecoupleRequest $request) => $request->domain === $domain && $request->requestUuid->equals($provisioningFilteredResult->requestUuid))
            ->andReturn($result);

        $this->mockLogger->shouldNotReceive('warning');

        $this->action->execute($domain, $subscription);
    }

    #[Test]
    public function decoupleThrowsExceptionWhenProvisionFailed(): void
    {
        $domain = 'example.com';

        $subscription = new Subscription();
        $subscription->domain = 'hosting-subscription.com';
        $subscription->uuid = Uuid::uuid4()->toString();

        $this->mockGateway
            ->shouldReceive('fetch')
            ->once()
            ->withArgs(
                fn (ProvisioningResultQueryFilters $filters, int $limit) => $filters->tag?->toString() === $subscription->uuid && $limit === 1
            )
            ->andReturn(new Collection([]));

        $this->mockLogger
            ->shouldReceive('warning')
        ->once()
        ->with(
            'No provisioning data found during domain decoupling for subscription {subscription.id} with UUID {subscription.uuid}.',
            [
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            ]
        );

        $this->expectException(DomainNameDecoupleActionException::class);
        $this->expectExceptionMessageIs(sprintf('No provisioning data found for subscription %s', $subscription->uuid));

        $this->action->execute($domain, $subscription);
    }

    #[Test]
    public function shouldThrowExceptionIfDecoupleFailed(): void
    {
        $domain = 'example.com';

        $subscription = new Subscription();
        $subscription->domain = 'hosting-subscription.com';
        $subscription->uuid = Uuid::uuid4()->toString();

        $provisioningResult = ProvisioningResultFactory::new()
            ->for(ProvisioningRequestFactory::new()->hosting())
            ->success()
            ->createOne();

        DomainNameCoupleDeploymentFactory::new()
            ->hostingCoupling()
            ->createOne(['origin_provisioning_request_id' => $provisioningResult->request_id]);

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

        $exceptionDuringDecouple = new UnknownHostingProviderException(ProvisionProvider::CLOUDSTACK);

        $result = self::mock(DomainNameDecoupleResult::class);
        $result->provisionStatus = ProvisionStatus::FAILED;
        $result->exception = $exceptionDuringDecouple;

        $this->mockGateway->shouldReceive('fetch')
            ->once()
            ->withArgs(fn (ProvisioningResultQueryFilters $filters, int $limit) => $filters->tag?->toString() === $subscription->uuid && $limit === 1)
            ->andReturn(new Collection([$provisioningFilteredResult]));

        $this->mockGateway
            ->shouldReceive('request')
            ->once()
            ->withArgs(fn (DomainNameDecoupleRequest $request) => $request->domain === $domain && $request->requestUuid->equals($provisioningFilteredResult->requestUuid))
            ->andReturn($result);

        $this->mockLogger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'Domain decouple action domain {domain.name} failed.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'couple_request_uuid' => $provisioningResult->provisioningRequest->uuid,
                    ],
                    LoggingContextKeys::EXCEPTION => $exceptionDuringDecouple,
                ]
            );

        $this->expectException(DomainNameDecoupleActionException::class);
        $this->expectExceptionMessageIs(sprintf('Domain couple action failed for domain [%s]', $domain));

        $this->action->execute($domain, $subscription);
    }
}
