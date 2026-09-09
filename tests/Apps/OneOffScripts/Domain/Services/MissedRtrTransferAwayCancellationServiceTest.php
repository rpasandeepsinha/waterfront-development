<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Domain\Services;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\DomainTransferStatus;
use RealtimeRegister\Domain\Notification;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Tests\Factories\ProductFactory;
use Tests\Factories\RtrResponseLogFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Domain\NovaCancelMissedRtrTransferAwaySubscriptionsAction;
use Waterfront\Apps\OneOffScripts\Domain\Services\MissedRtrTransferAwayCancellationService;
use Waterfront\Domain\Subscriptions\Actions\CancelSubscriptionsAction;
use Waterfront\Domain\Subscriptions\DTO\Cancellation;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Enums\TransferTypeType;
use Waterfront\Infra\RtrClient\Repositories\RtrResponseLogRepository;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(MissedRtrTransferAwayCancellationService::class)]
#[CoversClass(RtrResponseLogRepository::class)]
class MissedRtrTransferAwayCancellationServiceTest extends IntegrationTestCase
{
    private const string DOMAIN = 'example.nl';

    private const int PROCESS_ID = 12345;

    private const string NOTIFICATION_FIRE_DATE = '2026-05-06T10:00:00Z';

    private const string TRANSFER_REQUESTED_DATE = '2026-05-05T12:00:00Z';

    private MockObject&RtrService $rtrService;

    private MockObject&CancelSubscriptionsAction $cancelSubscriptionsAction;

    private MissedRtrTransferAwayCancellationService $missedRtrTransferAwayCancellationService;

    private Subscription $subscription;

    private CarbonImmutable $startDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startDate = CarbonImmutable::parse('2026-03-01')->startOfDay();
        $this->rtrService = self::createMock(RtrService::class);
        $this->cancelSubscriptionsAction = self::createMock(CancelSubscriptionsAction::class);
        $domainProduct = ProductFactory::new()->nlDomain()->createOne();

        $this->subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($domainProduct)
            ->forDomain(self::DOMAIN)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne([
                'start_date' => '2025-04-24',
                'next_billing_date' => '2027-04-24',
                'end_date' => '2027-04-24',
            ]);

        $this->missedRtrTransferAwayCancellationService = new MissedRtrTransferAwayCancellationService(
            rtrService: $this->rtrService,
            cancelSubscriptionsAction: $this->cancelSubscriptionsAction,
            rtrResponseLogRepository: self::resolve(RtrResponseLogRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            logger: self::createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function dryRunConfirmsCandidatesButDoesNotCancel(): void
    {
        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $this->rtrService
            ->expects(self::once())
            ->method('transferInfo')
            ->with(self::DOMAIN, self::PROCESS_ID)
            ->willReturn(DomainTransferStatus::fromArray([
                'domainName' => self::DOMAIN,
                'status' => 'completed',
                'requestedDate' => self::TRANSFER_REQUESTED_DATE,
                'type' => TransferTypeType::OUT->value,
                'processId' => self::PROCESS_ID,
            ]));

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: true,
        );

        self::assertSame(1, $processed);
    }

    #[Test]
    public function nonTransferNotificationLogsAreIgnoredBeforeRtrConfirmation(): void
    {
        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::RenewDomainEvent->value,
            'notificationType' => NotificationType::RenewDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $this->rtrService
            ->expects(self::never())
            ->method('transferInfo');

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(0, $processed);
    }

    #[Test]
    public function executeBuildsExpectedCancellationDto(): void
    {
        $rtrLogCreatedAt = $this->startDate->addDay();
        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => null,
            'payload' => [
                'domainName' => self::DOMAIN,
            ],
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $rtrLogCreatedAt,
        ]);

        $transferStatus = DomainTransferStatus::fromArray([
            'domainName' => self::DOMAIN,
            'status' => 'completed',
            'requestedDate' => self::TRANSFER_REQUESTED_DATE,
            'type' => TransferTypeType::OUT->value,
            'processId' => self::PROCESS_ID,
        ]);
        $transferStatus->type = TransferTypeType::OUT_INTERNAL->value;

        $this->rtrService
            ->expects(self::once())
            ->method('transferInfo')
            ->with(self::DOMAIN, self::PROCESS_ID)
            ->willReturn($transferStatus);

        $this->cancelSubscriptionsAction
            ->expects(self::once())
            ->method('execute')
            ->with(self::callback(function (Cancellation $cancellation) use ($rtrLogCreatedAt): bool {
                self::assertSame([$this->subscription->id], $cancellation->getSubscriptions()->pluck('id')->all());
                self::assertSame(SubscriptionCancelReason::REASON_DOMAIN_TRANSFERRED_AWAY, $cancellation->getCancelReason());
                self::assertNull($cancellation->getCancelReasonOther());
                self::assertSame(SubscriptionCancelType::CANCEL_OTHER, $cancellation->getCancelType());
                self::assertSame(
                    $rtrLogCreatedAt->toDateTimeString(),
                    $cancellation->getSelectedCancellationEndDate()?->toDateTimeString()
                );
                self::assertFalse($cancellation->shouldCreditRelatedInvoices());

                return true;
            }));

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(1, $processed);
    }

    #[Test]
    public function rtrConfirmationFailureSkipsCancellation(): void
    {
        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $this->rtrService
            ->expects(self::once())
            ->method('transferInfo')
            ->with(self::DOMAIN, self::PROCESS_ID)
            ->willThrowException(new RealtimeRegisterClientException('RTR unavailable'));

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(0, $processed);
    }

    #[Test]
    public function processIdentifierFallbackIsUsed(): void
    {
        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => null,
            'payload' => [
                'domainName' => null,
            ],
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $this->rtrService
            ->expects(self::once())
            ->method('transferInfo')
            ->with(self::DOMAIN, self::PROCESS_ID)
            ->willReturn(DomainTransferStatus::fromArray([
                'domainName' => self::DOMAIN,
                'status' => 'completed',
                'requestedDate' => self::TRANSFER_REQUESTED_DATE,
                'type' => TransferTypeType::OUT->value,
                'processId' => self::PROCESS_ID,
            ]));

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: true,
        );

        self::assertSame(1, $processed);
    }

    #[Test]
    public function notificationWithoutDomainDataSkipsSafely(): void
    {
        $rtrLog = RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode([
                'id' => 987,
                'eventType' => EventType::TransferDomainEvent->value,
                'notificationType' => NotificationType::TransferDomainNotification->value,
                'message' => "Transfer of 'example.nl' was approved by the current domain name holder",
                'customer' => 'sandwave',
                'process' => self::PROCESS_ID,
                'payload' => [
                    'subjectStatus' => 'OK',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping missed RTR transfer-away notification because domain name is missing.',
                self::callback(function (array $context) use ($rtrLog): bool {
                    self::assertSame(
                        NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        $context[LoggingContextKeys::ONE_OFF_SCRIPT]
                    );
                    self::assertArrayNotHasKey(LoggingContextKeys::DOMAIN_NAME, $context);
                    self::assertSame([
                        'dry_run' => false,
                        'rtr_response_log_id' => $rtrLog->id,
                        'notification_id' => 987,
                    ], $context[LoggingContextKeys::META]);

                    return true;
                }),
            );
        $this->missedRtrTransferAwayCancellationService = new MissedRtrTransferAwayCancellationService(
            rtrService: $this->rtrService,
            cancelSubscriptionsAction: $this->cancelSubscriptionsAction,
            rtrResponseLogRepository: self::resolve(RtrResponseLogRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            logger: $logger,
        );

        $this->rtrService
            ->expects(self::never())
            ->method('transferInfo');

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(0, $processed);
    }

    #[Test]
    public function notificationWithoutProcessSkipsSafely(): void
    {
        $rtrLog = RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode([
                'id' => 988,
                'eventType' => EventType::TransferDomainEvent->value,
                'notificationType' => NotificationType::TransferDomainNotification->value,
                'message' => 'Transfer domain is completed',
                'customer' => 'sandwave',
                'domainName' => self::DOMAIN,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Skipping missed RTR transfer-away notification because process id is missing.',
                self::callback(function (array $context) use ($rtrLog): bool {
                    self::assertSame(
                        NovaCancelMissedRtrTransferAwaySubscriptionsAction::SLUG,
                        $context[LoggingContextKeys::ONE_OFF_SCRIPT]
                    );
                    self::assertSame(self::DOMAIN, $context[LoggingContextKeys::DOMAIN_NAME]);
                    self::assertSame([
                        'dry_run' => false,
                        'rtr_response_log_id' => $rtrLog->id,
                        'notification_id' => 988,
                    ], $context[LoggingContextKeys::META]);

                    return true;
                }),
            );
        $this->missedRtrTransferAwayCancellationService = new MissedRtrTransferAwayCancellationService(
            rtrService: $this->rtrService,
            cancelSubscriptionsAction: $this->cancelSubscriptionsAction,
            rtrResponseLogRepository: self::resolve(RtrResponseLogRepository::class),
            subscriptionRepository: self::resolve(SubscriptionRepository::class),
            logger: $logger,
        );

        $this->rtrService
            ->expects(self::never())
            ->method('transferInfo');

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(0, $processed);
    }

    #[DataProvider('nonCancellableTransferStatusProvider')]
    #[Test]
    public function nonCancellableTransferStatusesSkipCancellation(string $type, string $status): void
    {
        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $this->rtrService
            ->expects(self::once())
            ->method('transferInfo')
            ->with(self::DOMAIN, self::PROCESS_ID)
            ->willReturn(DomainTransferStatus::fromArray([
                'domainName' => self::DOMAIN,
                'status' => $status,
                'requestedDate' => self::TRANSFER_REQUESTED_DATE,
                'type' => $type,
                'processId' => self::PROCESS_ID,
            ]));

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(0, $processed);
    }

    #[Test]
    public function alreadyEndedSubscriptionsSkipSafely(): void
    {
        $this->subscription->update([
            'administrative_status' => AdministrativeStatus::ARCHIVED->value,
        ]);

        $notification = Notification::fromArray([
            'id' => 1,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => self::NOTIFICATION_FIRE_DATE,
            'message' => 'Transfer domain is completed',
            'customer' => 'sandwave',
            'process' => self::PROCESS_ID,
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);
        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'response' => json_encode($notification, JSON_THROW_ON_ERROR),
            'created_at' => $this->startDate->addDay(),
        ]);

        $this->rtrService
            ->expects(self::never())
            ->method('transferInfo');

        $this->cancelSubscriptionsAction
            ->expects(self::never())
            ->method('execute');

        $processed = $this->missedRtrTransferAwayCancellationService->handle(
            startDate: $this->startDate,
            dryRun: false,
        );

        self::assertSame(0, $processed);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function nonCancellableTransferStatusProvider(): array
    {
        return [
            'incoming completed' => [TransferTypeType::IN->value, 'completed'],
            'outgoing pending' => [TransferTypeType::OUT->value, 'pending'],
            'outgoing failed' => [TransferTypeType::OUT->value, 'failed'],
        ];
    }
}
