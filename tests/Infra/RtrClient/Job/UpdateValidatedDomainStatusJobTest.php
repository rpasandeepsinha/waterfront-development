<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Job;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrResponseLogFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\DomainProviderHistory;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Job\UpdateValidatedDomainStatusJob;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus as RtrDomainStatus;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(UpdateValidatedDomainStatusJob::class)]
class UpdateValidatedDomainStatusJobTest extends IntegrationTestCase
{
    private const string DOMAIN = 'validate-contact.com';

    private DomainDeployment $domainDeployment;

    private DomainDeploymentRepository $domainDeploymentRepository;

    private DomainProviderHistory $domainProviderHistory;

    private SubscriptionRepository $subscriptionRepository;

    private Subscription $subscription;

    private RtrResponseLog $rtrResponseLog;

    private UpdateValidatedDomainStatusJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for(ProductFactory::new()->nlDomain())
            ->forDomain(self::DOMAIN)
            ->administrativeStatusActive()
            ->technicalStatusPending()
            ->createOne();

        $this->domainDeployment = DomainDeploymentFactory::new()
            ->withRtrProvider()
            ->withRtrDomainStatus(RtrDomainStatus::PENDING_VALIDATION)
            ->createOne([
                'subscription_uuid' => $this->subscription->uuid,
            ]);

        $this->rtrResponseLog = RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
        ]);

        $this->domainDeploymentRepository = self::resolve(DomainDeploymentRepository::class);
        $this->domainProviderHistory = self::resolve(DomainProviderHistory::class);
        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);

        $this->job = new UpdateValidatedDomainStatusJob(
            domainName: self::DOMAIN,
            message: 'Contact validation completed',
            rtrResponseLog: $this->rtrResponseLog,
        );
    }

    #[Test]
    public function handleCompletesPendingValidatedDomainAndStoresHistory(): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('notice')
            ->with('RTR domain validation completed', $this->expectedLogContext(
                domainName: self::DOMAIN,
                domainDeployment: $this->domainDeployment,
                expectedDomainStatus: RtrDomainStatus::OK->value,
                expectedTechnicalStatus: DomainStatus::ACTIVE->value,
            ));

        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $logger,
        );

        self::assertSame(RtrDomainStatus::OK, $this->domainDeployment->refresh()->domain_status);
        self::assertSame(DomainStatus::ACTIVE->value, $this->subscription->refresh()->technical_status);
        self::assertDatabaseHas('domain_provider_status', [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
            'domain_deployment_id' => $this->domainDeployment->id,
            'status' => DomainStatus::ACTIVE->value,
            'received_result' => 'Contact validation completed',
        ]);
    }

    #[Test]
    public function handleDoesNotDuplicateHistoryForAlreadyValidatedDomain(): void
    {
        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: self::createStub(LoggerInterface::class),
        );

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('RTR validation skipped: domain already validated', $this->expectedLogContext(
                domainName: self::DOMAIN,
                domainDeployment: $this->domainDeployment,
                expectedDomainStatus: RtrDomainStatus::OK->value,
                expectedTechnicalStatus: DomainStatus::ACTIVE->value,
            ));

        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $logger,
        );

        self::assertDatabaseCount('domain_provider_status', 1);
    }

    #[Test]
    public function handleUpdatesDeploymentWithoutChangingActiveSubscription(): void
    {
        $this->subscription->technical_status = DomainStatus::ACTIVE->value;
        $this->subscription->save();

        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: self::createStub(LoggerInterface::class),
        );

        self::assertSame(RtrDomainStatus::OK, $this->domainDeployment->refresh()->domain_status);
        self::assertSame(DomainStatus::ACTIVE->value, $this->subscription->refresh()->technical_status);
        self::assertDatabaseHas('domain_provider_status', [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
            'domain_deployment_id' => $this->domainDeployment->id,
            'status' => DomainStatus::ACTIVE->value,
            'received_result' => 'Contact validation completed',
        ]);
    }

    #[DataProvider('incompatibleSubscriptionTechnicalStatusDataProvider')]
    #[Test]
    public function handleSkipsSubscriptionWithIncompatibleTechnicalStatus(string $technicalStatus): void
    {
        $this->subscription->technical_status = $technicalStatus;
        $this->subscription->save();
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('RTR validation skipped: subscription status cannot be completed', $this->expectedLogContext(
                domainName: self::DOMAIN,
                domainDeployment: $this->domainDeployment,
                expectedDomainStatus: RtrDomainStatus::PENDING_VALIDATION->value,
                expectedTechnicalStatus: $technicalStatus,
            ));

        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $logger,
        );

        self::assertSame($technicalStatus, $this->subscription->refresh()->technical_status);
        self::assertDatabaseMissing('domain_provider_status', [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
            'domain_deployment_id' => $this->domainDeployment->id,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function incompatibleSubscriptionTechnicalStatusDataProvider(): array
    {
        return [
            'domain pending' => [DomainStatus::PENDING->value],
            'generic ok' => [TechnicalStatus::OK->value],
            'generic failed' => [TechnicalStatus::FAILED->value],
            'domain failed' => [DomainStatus::FAILED->value],
        ];
    }

    /**
     * @param 'info'|'warning' $logLevel
     */
    #[DataProvider('nonPendingValidationRtrDomainStatusDataProvider')]
    #[Test]
    public function handleSkipsDomainWithoutPendingValidationStatus(
        RtrDomainStatus $domainStatus,
        string $logLevel,
        string $logMessage,
    ): void {
        $this->domainDeployment->domain_status = $domainStatus;
        $this->domainDeployment->save();
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method($logLevel)
            ->with($logMessage, $this->expectedLogContext(
                domainName: self::DOMAIN,
                domainDeployment: $this->domainDeployment,
                expectedDomainStatus: $domainStatus->value,
                expectedTechnicalStatus: TechnicalStatus::PENDING->value,
            ));

        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $logger,
        );

        self::assertSame($domainStatus, $this->domainDeployment->refresh()->domain_status);
        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->refresh()->technical_status);
        self::assertDatabaseMissing('domain_provider_status', [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
            'domain_deployment_id' => $this->domainDeployment->id,
        ]);
    }

    /**
     * @return array<string, array{RtrDomainStatus, string, string}>
     */
    public static function nonPendingValidationRtrDomainStatusDataProvider(): array
    {
        return [
            'inactive' => [RtrDomainStatus::INACTIVE, 'info', 'RTR validation skipped: domain requires nameserver activation'],
            'client hold' => [RtrDomainStatus::CLIENT_HOLD, 'warning', 'RTR validation skipped: domain is not pending validation'],
        ];
    }

    #[Test]
    public function handleSkipsUnknownDomain(): void
    {
        $domainName = 'unknown-validate-contact.com';
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('RTR validation skipped: active domain deployment not found', $this->expectedLogContext(
                domainName: $domainName,
                domainDeployment: null,
                expectedDomainStatus: null,
                expectedTechnicalStatus: null,
            ));

        new UpdateValidatedDomainStatusJob(
            domainName: $domainName,
            message: 'Contact validation completed',
            rtrResponseLog: $this->rtrResponseLog,
        )->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $logger,
        );

        self::assertDatabaseMissing('domain_provider_status', [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
        ]);
    }

    #[Test]
    public function handleSkipsNonRtrDomain(): void
    {
        $this->domainDeployment->provider()->associate(
            ProviderFactory::new()->domainOpenProvider()->createOne(),
        );
        $this->domainDeployment->save();
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('RTR validation skipped: domain provider is not RTR', $this->expectedLogContext(
                domainName: self::DOMAIN,
                domainDeployment: $this->domainDeployment,
                expectedDomainStatus: RtrDomainStatus::PENDING_VALIDATION->value,
                expectedTechnicalStatus: TechnicalStatus::PENDING->value,
            ));

        $this->job->handle(
            domainDeploymentRepository: $this->domainDeploymentRepository,
            domainProviderHistory: $this->domainProviderHistory,
            subscriptionRepository: $this->subscriptionRepository,
            logger: $logger,
        );

        self::assertDatabaseMissing('domain_provider_status', [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
            'domain_deployment_id' => $this->domainDeployment->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedLogContext(
        string $domainName,
        ?DomainDeployment $domainDeployment,
        ?string $expectedDomainStatus,
        ?string $expectedTechnicalStatus,
    ): array {
        $meta = [
            'rtr_response_log_id' => $this->rtrResponseLog->id,
        ];

        $context = [
            LoggingContextKeys::DOMAIN_NAME => $domainName,
            LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
            LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
        ];

        if ($domainDeployment !== null) {
            $subscription = $domainDeployment->subscription;

            $context[LoggingContextKeys::PROVISIONING_ID] = $domainDeployment->id;
            $context[LoggingContextKeys::SUBSCRIPTION_ID] = $subscription->id;
            $context[LoggingContextKeys::SUBSCRIPTION_UUID] = $subscription->uuid;
            $meta['domain.provider'] = $domainDeployment->provider->slug->value;
            $meta['domain.status'] = $expectedDomainStatus;
            $meta['subscription.technical_status'] = $expectedTechnicalStatus;
        }

        $context[LoggingContextKeys::META] = $meta;

        return $context;
    }
}
