<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Notification;

use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Facades\Bus;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Enum\ProcessStatusEnum;
use RealtimeRegister\Domain\Notification as RtrNotification;
use RealtimeRegister\Domain\Process;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\RtrResponseLogFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Apps\Console\Commands\PollNotifications;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\DTO\DomainDetailsDTO;
use Waterfront\Domain\Domains\Enums\DomainStatus as LocalDomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainDoesNotExistException;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\DomainProviderHistory;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Provision\DNS\Actions\ValidateDnsPropagationAndRetry;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Enums\RtrValidationError;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Job\SetNameserversForDomainJob;
use Waterfront\Infra\RtrClient\Services\Enums\DomainStatus;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\DomainNotificationHandler;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(DomainNotificationHandler::class)]
class DomainNotificationHandlerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave.io';

    private Subscription $subscription;

    public function setUp(): void
    {
        parent::setUp();

        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne();
        ProviderFactory::new()->createOne(
            [
                'type' => ProviderType::DOMAIN,
                'slug' => ProviderSlug::REALTIME_REGISTER,
                'default' => true,
                'enabled' => true,
            ]
        );

        $this->subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne([
            'domain' => self::DOMAIN,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::PENDING->value,
        ]);

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne(['subscription_uuid' => $this->subscription->uuid]);
    }

    #[Test]
    public function retryDnsOnRegistryRequirementError(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => sprintf(
                'The DNS configuration of the domain \'%s\' does not meet the requirements set by the registry. Until the requirements are met the domain will remain suspended.',
                self::DOMAIN
            ),
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => null,
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $rtrNotification = new RtrResponseLogFactory()->createOne();

        $mockRtrErrorParseService = self::createMock(RtrErrorParseService::class);
        $mockValidateDnsPropagationAndRetry = self::createMock(ValidateDnsPropagationAndRetry::class);

        $handler = new DomainNotificationHandler(
            subscriptionService: self::resolve(SubscriptionService::class),
            rtrService: self::resolve(RtrService::class),
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: $mockRtrErrorParseService,
            dnsRetryAction: $mockValidateDnsPropagationAndRetry,
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $mockRtrErrorParseService->expects(self::once())
            ->method('getRtrErrorFromMessage')
            ->with($notification->message)
            ->willReturn(RtrValidationError::REGISTRY_REQUIREMENTS_NOT_MET);

        $mockValidateDnsPropagationAndRetry->expects(self::once())
            ->method('execute')
            ->with(self::DOMAIN);

        $handler->handle($notification, $rtrNotification);
    }

    #[Test]
    public function renewDomainEvent(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::RenewDomainEvent->value,
            'notificationType' => NotificationType::RenewDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => 'Create domain message failed',
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => null,
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $rtrNotification = new RtrResponseLogFactory()->createOne();

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())
            ->method('updateSubscription');

        $handler = new DomainNotificationHandler(
            subscriptionService: $subscriptionService,
            rtrService: self::resolve(RtrService::class),
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: self::resolve(RtrErrorParseService::class),
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );
        $handler->handle($notification, $rtrNotification);
    }

    #[Test]
    public function inactiveDomainDispatchesSetNameserversJobAndDoesNotSendMail(): void
    {
        Bus::fake();

        $this->subscription->domainDeployment?->update([
            'domain_status' => DomainStatus::PENDING_VALIDATION,
        ]);

        $dnsProduct = ProductFactory::new()
            ->freeDns()
            ->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->for($this->subscription->customer)
            ->for($dnsProduct)
            ->parentSubscription($this->subscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($dnsSubscription)
            ->createOne();

        $dnsDeployment->dnsNameservers()->save(
            DnsNameserverFactory::new()
                ->for(DnsRegionFactory::new())
                ->createOne(['nameserver' => 'ns1.example.nl']),
        );

        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => 'Domain created but inactive',
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => null,
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $rtrResponseLog = new RtrResponseLogFactory()->createOne();

        $mockRtrService = self::createMock(RtrService::class);
        $mockRtrErrorParseService = self::createMock(RtrErrorParseService::class);
        $mockSubscriptionService = self::createMock(SubscriptionService::class);
        $mockLogger = self::createMock(LoggerInterface::class);

        $mockRtrErrorParseService->expects(self::once())
            ->method('getRtrErrorFromMessage')
            ->with($notification->message)
            ->willReturn(null);

        $mockRtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN)
            ->willReturn(new DomainDetailsDTO(
                domainName: self::DOMAIN,
                registrant: 'johndoe',
                status: [DomainStatus::INACTIVE->value],
                autoRenew: true,
                autoRenewPeriod: 12,
                ns: [],
                premium: false,
            ));

        $mockRtrService->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([DomainStatus::INACTIVE->value])
            ->willReturn(TechnicalStatus::OK->value);

        $mockRtrService->method('getPrimaryDomainStatusFromDomainStatusList')
            ->willReturn(DomainStatus::INACTIVE);

        $mockSubscriptionService->expects(self::once())
            ->method('updateSubscriptionStatus')
            ->with(
                self::DOMAIN,
                TechnicalStatus::PENDING->value,
                'Domain created but inactive',
                false,
            );

        $mockLogger->expects(self::exactly(2))
            ->method('info')
            ->with(...self::withConsecutive(
                [
                    'RTR prevalidation domain completed',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                        LoggingContextKeys::META => [
                            'domain_status' => DomainStatus::INACTIVE->value,
                        ],
                    ],
                ],
                [
                    'Dispatching RTR nameserver update',
                    [
                        LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                        LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                        LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
                    ],
                ],
            ));

        $handler = new DomainNotificationHandler(
            subscriptionService: $mockSubscriptionService,
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: $mockRtrErrorParseService,
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: $mockLogger,
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        Bus::assertDispatched(SetNameserversForDomainJob::class, fn (SetNameserversForDomainJob $job): bool => $job->domain === self::DOMAIN);
        self::assertSame(DomainStatus::INACTIVE, $this->subscription->domainDeployment?->refresh()->domain_status);
    }

    #[Test]
    public function createDomainNotificationDoesNotUsePrevalidationFallbackWhenDomainIsMissing(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain created',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();
        $fetchException = new DomainDoesNotExistException();

        $mockRtrService = self::createMock(RtrService::class);
        $mockRtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN)
            ->willThrowException($fetchException);
        $mockRtrService->expects(self::never())
            ->method('findOpenPrevalidationProcessForDomain');

        $handler = new DomainNotificationHandler(
            subscriptionService: self::resolve(SubscriptionService::class),
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: self::createStub(RtrErrorParseService::class),
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        self::expectExceptionObject($fetchException);
        $handler->handle($notification, $rtrResponseLog);
    }

    #[Test]
    public function nonCreateNotificationUsesOpenPrevalidationFallbackWhenDomainIsMissing(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::UpdateDomainEvent->value,
            'notificationType' => NotificationType::UpdateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain is waiting for prevalidation',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();
        $mockRtrService = self::createMock(RtrService::class);
        $mockRtrService->method('fetchDomain')
            ->willThrowException(new DomainDoesNotExistException());
        $mockRtrService->expects(self::once())
            ->method('findOpenPrevalidationProcessForDomain')
            ->with(self::DOMAIN)
            ->willReturn(Process::fromArray([
                'id' => 1234,
                'user' => 'test-user',
                'customer' => 'versiosandwave',
                'status' => ProcessStatusEnum::STATUS_RUNNING,
                'createdDate' => '2026-03-23T12:00:00Z',
                'type' => 'domain',
                'action' => 'create',
                'command' => [],
            ]));
        $mockSubscriptionService = self::createMock(SubscriptionService::class);
        $mockSubscriptionService->expects(self::once())
            ->method('updateSubscriptionStatus')
            ->with(
                self::DOMAIN,
                TechnicalStatus::PENDING->value,
                $notification->message,
                false,
            );

        $handler = new DomainNotificationHandler(
            subscriptionService: $mockSubscriptionService,
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: self::createStub(RtrErrorParseService::class),
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        self::assertSame(
            DomainStatus::PENDING_VALIDATION,
            $this->subscription->domainDeployment?->refresh()->domain_status,
        );
    }

    #[Test]
    public function createDomainNotificationThrowsWhenRemoteStatusIsUnknown(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain created',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();
        $rtrServiceStub = self::createStub(RtrService::class);
        $rtrServiceStub->method('fetchDomain')
            ->willReturn(new DomainDetailsDTO(
                domainName: self::DOMAIN,
                registrant: 'johndoe',
                status: ['NEW_PROVIDER_STATUS'],
                autoRenew: true,
                autoRenewPeriod: 12,
                ns: [],
                premium: false,
            ));
        $rtrServiceStub->method('getPrimaryDomainStatusFromDomainStatusList')
            ->willReturn(null);

        $handler = new DomainNotificationHandler(
            subscriptionService: self::resolve(SubscriptionService::class),
            rtrService: $rtrServiceStub,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: self::createStub(RtrErrorParseService::class),
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        self::expectException(UnexpectedValueException::class);
        $handler->handle($notification, $rtrResponseLog);
    }

    #[Test]
    public function failedCreateDomainNotificationMarksSubscriptionFailed(): void
    {
        self::assertEmailsSend([
            MailDomainCreationFailed::class,
        ]);

        $message = sprintf(
            "Creating domain '%s' cancelled, registrant validation failed",
            self::DOMAIN,
        );
        $notification = RtrNotification::fromArray([
            'id' => 2523326744,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-06-27T21:58:26Z',
            'message' => $message,
            'process' => 2517557112,
            'customer' => 'yourhostingsw',
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'subjectStatus' => 'FAILED',
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();

        $mockRtrService = self::createMock(RtrService::class);
        $mockRtrService->expects(self::never())
            ->method('fetchDomain');

        $handler = new DomainNotificationHandler(
            subscriptionService: self::resolve(SubscriptionService::class),
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: self::createStub(RtrErrorParseService::class),
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        self::assertSame(TechnicalStatus::FAILED->value, $this->subscription->refresh()->technical_status);
    }

    #[Test]
    public function validatedPrevalidationDomainWithOkStatusDispatchesNameserverUpdateWhenNameserversDiffer(): void
    {
        Bus::fake();

        $this->subscription->technical_status = TechnicalStatus::PENDING->value;
        $this->subscription->save();
        $this->subscription->domainDeployment?->update([
            'domain_status' => DomainStatus::PENDING_VALIDATION,
        ]);

        $dnsProduct = ProductFactory::new()
            ->freeDns()
            ->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->for($this->subscription->customer)
            ->for($dnsProduct)
            ->parentSubscription($this->subscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($dnsSubscription)
            ->createOne();

        $dnsDeployment->dnsNameservers()->save(
            DnsNameserverFactory::new()
                ->for(DnsRegionFactory::new())
                ->createOne(['nameserver' => 'ns1.example.nl']),
        );

        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain created',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();

        $mockRtrService = self::createMock(RtrService::class);
        $mockRtrErrorParseService = self::createMock(RtrErrorParseService::class);
        $subscriptionServiceStub = self::createStub(SubscriptionService::class);

        $mockRtrErrorParseService->expects(self::once())
            ->method('getRtrErrorFromMessage')
            ->with($notification->message)
            ->willReturn(null);

        $mockRtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN)
            ->willReturn(new DomainDetailsDTO(
                domainName: self::DOMAIN,
                registrant: 'johndoe',
                status: [DomainStatus::OK->value],
                autoRenew: true,
                autoRenewPeriod: 12,
                ns: ['ns2.example.nl'],
                premium: false,
            ));

        $mockRtrService->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([DomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $mockRtrService->method('getPrimaryDomainStatusFromDomainStatusList')
            ->willReturn(DomainStatus::OK);

        $handler = new DomainNotificationHandler(
            subscriptionService: $subscriptionServiceStub,
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: $mockRtrErrorParseService,
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        Bus::assertDispatched(SetNameserversForDomainJob::class, fn (SetNameserversForDomainJob $job): bool => $job->domain === self::DOMAIN);
        self::assertSame(DomainStatus::OK, $this->subscription->domainDeployment?->refresh()->domain_status);
    }

    #[Test]
    public function validatedPrevalidationDomainWithOkStatusDoesNotDispatchNameserverUpdateWhenNameserversMatch(): void
    {
        Bus::fake();

        $this->subscription->technical_status = TechnicalStatus::PENDING->value;
        $this->subscription->save();
        $this->subscription->domainDeployment?->update([
            'domain_status' => DomainStatus::PENDING_VALIDATION,
        ]);

        $dnsProduct = ProductFactory::new()
            ->freeDns()
            ->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->for($this->subscription->customer)
            ->for($dnsProduct)
            ->parentSubscription($this->subscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($dnsSubscription)
            ->createOne();

        $dnsDeployment->dnsNameservers()->save(
            DnsNameserverFactory::new()
                ->for(DnsRegionFactory::new())
                ->createOne(['nameserver' => 'ns1.example.nl']),
        );

        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain created',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();

        $mockRtrService = self::createMock(RtrService::class);
        $rtrErrorParseServiceStub = self::createStub(RtrErrorParseService::class);
        $subscriptionServiceStub = self::createStub(SubscriptionService::class);

        $rtrErrorParseServiceStub->method('getRtrErrorFromMessage')
            ->willReturn(null);

        $mockRtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN)
            ->willReturn(new DomainDetailsDTO(
                domainName: self::DOMAIN,
                registrant: 'johndoe',
                status: [DomainStatus::OK->value],
                autoRenew: true,
                autoRenewPeriod: 12,
                ns: ['NS1.EXAMPLE.NL.'],
                premium: false,
            ));

        $mockRtrService->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([DomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $mockRtrService->method('getPrimaryDomainStatusFromDomainStatusList')
            ->willReturn(DomainStatus::OK);

        $handler = new DomainNotificationHandler(
            subscriptionService: $subscriptionServiceStub,
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: $rtrErrorParseServiceStub,
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        Bus::assertNotDispatched(SetNameserversForDomainJob::class);
        self::assertSame(DomainStatus::OK, $this->subscription->domainDeployment?->refresh()->domain_status);
    }

    #[Test]
    public function okNotificationForNonRequestedDomainDoesNotDispatchNameserverUpdate(): void
    {
        Bus::fake();

        $dnsProduct = ProductFactory::new()
            ->freeDns()
            ->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->for($this->subscription->customer)
            ->for($dnsProduct)
            ->parentSubscription($this->subscription)
            ->createOne();

        $dnsDeployment = DnsDeploymentFactory::new()
            ->for($dnsSubscription)
            ->createOne();

        $dnsDeployment->dnsNameservers()->save(
            DnsNameserverFactory::new()
                ->for(DnsRegionFactory::new())
                ->createOne(['nameserver' => 'ns1.example.nl']),
        );

        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain already active',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();

        $mockRtrService = self::createMock(RtrService::class);
        $rtrErrorParseServiceStub = self::createStub(RtrErrorParseService::class);
        $subscriptionServiceStub = self::createStub(SubscriptionService::class);

        $rtrErrorParseServiceStub->method('getRtrErrorFromMessage')
            ->willReturn(null);

        $mockRtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN)
            ->willReturn(new DomainDetailsDTO(
                domainName: self::DOMAIN,
                registrant: 'johndoe',
                status: [DomainStatus::OK->value],
                autoRenew: true,
                autoRenewPeriod: 12,
                ns: ['ns2.example.nl'],
                premium: false,
            ));

        $mockRtrService->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([DomainStatus::OK->value])
            ->willReturn(TechnicalStatus::OK->value);

        $mockRtrService->method('getPrimaryDomainStatusFromDomainStatusList')
            ->willReturn(DomainStatus::OK);

        $handler = new DomainNotificationHandler(
            subscriptionService: $subscriptionServiceStub,
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: $rtrErrorParseServiceStub,
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: self::resolve(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        Bus::assertNotDispatched(SetNameserversForDomainJob::class);
    }

    #[Test]
    public function inactiveDomainDoesNotDispatchSetNameserversJobWhenNoNameserversExist(): void
    {
        Bus::fake();

        $notification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::CreateDomainEvent->value,
            'notificationType' => NotificationType::CreateDomainNotification->value,
            'fireDate' => '2026-05-08T11:01:10Z',
            'message' => 'Domain created but inactive',
            'isAsync' => false,
            'domainName' => self::DOMAIN,
        ]);
        $rtrResponseLog = new RtrResponseLogFactory()->createOne();

        $mockRtrService = self::createMock(RtrService::class);
        $rtrErrorParseServiceStub = self::createStub(RtrErrorParseService::class);
        $mockSubscriptionService = self::createMock(SubscriptionService::class);
        $logger = self::createMock(LoggerInterface::class);

        $rtrErrorParseServiceStub->method('getRtrErrorFromMessage')
            ->willReturn(null);

        $mockRtrService->expects(self::once())
            ->method('fetchDomain')
            ->with(self::DOMAIN)
            ->willReturn(new DomainDetailsDTO(
                domainName: self::DOMAIN,
                registrant: 'johndoe',
                status: [DomainStatus::INACTIVE->value],
                autoRenew: true,
                autoRenewPeriod: 12,
                ns: [],
                premium: false,
            ));

        $mockRtrService->expects(self::once())
            ->method('getTechnicalStatusFromDomainStatusList')
            ->with([DomainStatus::INACTIVE->value])
            ->willReturn(TechnicalStatus::OK->value);

        $mockRtrService->method('getPrimaryDomainStatusFromDomainStatusList')
            ->willReturn(DomainStatus::INACTIVE);

        $mockSubscriptionService->expects(self::once())
            ->method('updateSubscriptionStatus')
            ->with(self::DOMAIN, TechnicalStatus::PENDING->value, 'Domain created but inactive', false);
        $logger->expects(self::once())
            ->method('warning')
            ->with('RTR domain inactive without local nameservers', [
                LoggingContextKeys::DOMAIN_NAME => self::DOMAIN,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::RTR,
            ]);

        $handler = new DomainNotificationHandler(
            subscriptionService: $mockSubscriptionService,
            rtrService: $mockRtrService,
            domainProviderHistory: self::resolve(DomainProviderHistory::class),
            rtrErrorParseService: $rtrErrorParseServiceStub,
            dnsRetryAction: self::resolve(ValidateDnsPropagationAndRetry::class),
            logger: $logger,
            notificationHelper: self::resolve(NotificationHelper::class),
            busDispatcher: self::resolve(Dispatcher::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            dnsDeploymentRepository: self::resolve(DnsDeploymentRepository::class),
            domainDeploymentRepository: self::resolve(DomainDeploymentRepository::class),
        );

        $handler->handle($notification, $rtrResponseLog);

        Bus::assertNotDispatched(SetNameserversForDomainJob::class);
    }

    #[DataProvider('successDataProvider')]
    #[Test]
    public function domainSuccessNotification(
        RtrNotification $notification,
        string $fileLocation,
        string $expectedTechnicalStatus,
    ): void {
        $rtrClient = $this->createTestRtrSdk($notification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame($expectedTechnicalStatus, $this->subscription->technical_status);
    }

    #[DataProvider('pendingDataProvider')]
    #[Test]
    public function domainPendingNotification(RtrNotification $notification, string $fileLocation): void
    {
        $mailer = self::createMock(Mailer::class);
        $mailer->expects(self::never())->method('send');
        $this->app->bind(Mailer::class, fn () => $mailer);

        $rtrClient = $this->createTestRtrSdk($notification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->technical_status);
        self::assertSame(
            DomainStatus::PENDING_VALIDATION,
            $this->subscription->domainDeployment?->refresh()->domain_status,
        );
    }

    #[DataProvider('failedDataProvider')]
    #[Test]
    public function domainCreateNotificationFailed(RtrNotification $notification, string $fileLocation): void
    {
        self::assertEmailsSend([
            MailDomainCreationFailed::class,
        ]);
        $rtrClient = $this->createTestRtrSdk($notification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::FAILED->value, $this->subscription->technical_status);
    }

    public static function successDataProvider(): Iterator
    {
        yield 'createDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::CreateDomainEvent->value,
                'notificationType' => NotificationType::CreateDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'domainName' => self::DOMAIN,
                    'subjectStatus' => 'OK',
                ],
            ]),
            __DIR__ . '/../data/domain_details_valid_only_notification_required.php',
            LocalDomainStatus::ACTIVE->value,
        ];

        yield 'updateDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::UpdateDomainEvent->value,
                'notificationType' => NotificationType::UpdateDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'OK',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_valid_only_notification_required.php',
            TechnicalStatus::OK->value,
        ];

        yield 'deleteDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::DeleteDomainEvent->value,
                'notificationType' => NotificationType::DeleteDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'OK',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_valid_only_notification_required.php',
            TechnicalStatus::OK->value,
        ];
    }

    public static function pendingDataProvider(): Iterator
    {
        yield [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::CreateDomainEvent->value,
                'notificationType' => NotificationType::CreateDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'OK',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_pending_only_required.php',
        ];

        yield 'updateDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::UpdateDomainEvent->value,
                'notificationType' => NotificationType::UpdateDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'OK',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_pending_only_required.php',
        ];

        yield 'deleteDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::DeleteDomainEvent->value,
                'notificationType' => NotificationType::DeleteDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'OK',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_pending_only_required.php',
        ];
    }

    public static function failedDataProvider(): Iterator
    {
        yield 'createDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::CreateDomainEvent->value,
                'notificationType' => NotificationType::CreateDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'FAI',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_failed_only_required.php',
        ];

        yield 'updateDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::UpdateDomainEvent->value,
                'notificationType' => NotificationType::UpdateDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'FAI',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_failed_only_required.php',
        ];

        yield 'deleteDomainevent' => [
            RtrNotification::fromArray([
                'id' => 123,
                'eventType' => EventType::DeleteDomainEvent->value,
                'notificationType' => NotificationType::DeleteDomainNotification->value,
                'fireDate' => '2023-09-08T11:01:10Z',
                'message' => 'Create domain message failed',
                'process' => 5,
                'customer' => 'versiosandwave',
                'isAsync' => false,
                'payload' => [
                    'transferType' => null,
                    'subjectStatus' => 'FAI',
                    'domainName' => self::DOMAIN,
                ],
            ]),
            __DIR__ . '/../data/domain_details_failed_only_required.php',
        ];
    }

    private function createTestRtrSdk(
        RtrNotification $rtrNotification,
        string $transferInfoFileLocation
    ): RealtimeRegister {
        return MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: (string) json_encode([
                    'entities' => [$rtrNotification->toArray()],
                ])
            ),
            new Response(
                status: 200,
                body: json_encode(include $transferInfoFileLocation, JSON_THROW_ON_ERROR)
            ),
            new Response(
                status: 201
            ),
        ]);
    }
}
