<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification as RtrNotification;
use RuntimeException;
use Tests\Factories\RtrResponseLogFactory;
use Tests\IntegrationTestCase;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Event\NewNotificationReceived;
use Waterfront\Infra\RtrClient\Repositories\RtrResponseLogRepository;
use Waterfront\Infra\RtrClient\Services\RtrNotificationProcessor;
use Waterfront\Infra\RtrClient\Services\RtrResponseLogService;

#[CoversClass(RtrNotificationProcessor::class)]
class RtrNotificationProcessorTest extends IntegrationTestCase
{
    private RtrResponseLogRepository $rtrResponseLogRepository;

    private RtrResponseLogService $rtrResponseLogService;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rtrResponseLogRepository = self::resolve(RtrResponseLogRepository::class);
        $this->rtrResponseLogService = self::resolve(RtrResponseLogService::class);
        $this->logger = self::createStub(LoggerInterface::class);
    }

    #[Test]
    public function processStoresDispatchesAndMarksNotificationAsProcessed(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 1234,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'test.com' was approved by the current domain name holder",
            'process' => 1398322046,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'OUT',
                'subjectStatus' => 'OK',
                'domainName' => 'test.com',
            ],
        ]);

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (NewNotificationReceived $event) use ($notification): bool {
                self::assertSame($notification, $event->getNotification());
                self::assertSame($notification->id, $event->getRtrResponseLog()->rtr_notification_id);

                return true;
            }));

        $notificationProcessor = new RtrNotificationProcessor(
            rtrResponseLogRepository: $this->rtrResponseLogRepository,
            rtrResponseLogService: $this->rtrResponseLogService,
            eventDispatcher: $dispatcher,
            logger: $this->logger,
        );

        $notificationProcessor->process($notification);

        self::assertDatabaseHas('rtr_response_log', [
            'source' => RtrResponseSource::NOTIFICATION->value,
            'rtr_notification_id' => $notification->id,
            'processed_at' => CarbonImmutable::now(),
            'failed_at' => null,
        ]);
    }

    #[Test]
    public function processMarksNotificationAsFailedWhenDispatchFails(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 1235,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'test.com' was approved by the current domain name holder",
            'process' => 1398322046,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'OUT',
                'subjectStatus' => 'OK',
                'domainName' => 'test.com',
            ],
        ]);
        $exception = new RuntimeException('Dispatch failed');

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->willThrowException($exception);

        $notificationProcessor = new RtrNotificationProcessor(
            rtrResponseLogRepository: $this->rtrResponseLogRepository,
            rtrResponseLogService: $this->rtrResponseLogService,
            eventDispatcher: $dispatcher,
            logger: $this->logger,
        );

        self::expectExceptionObject($exception);

        try {
            $notificationProcessor->process($notification);
        } finally {
            self::assertDatabaseHas('rtr_response_log', [
                'source' => RtrResponseSource::NOTIFICATION->value,
                'rtr_notification_id' => $notification->id,
                'processed_at' => null,
                'failed_at' => CarbonImmutable::now(),
            ]);
        }
    }

    #[Test]
    public function processSkipsAlreadyProcessedNotification(): void
    {
        $notification = RtrNotification::fromArray([
            'id' => 1236,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'test.com' was approved by the current domain name holder",
            'process' => 1398322046,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'OUT',
                'subjectStatus' => 'OK',
                'domainName' => 'test.com',
            ],
        ]);

        RtrResponseLogFactory::new()->createOne([
            'source' => RtrResponseSource::NOTIFICATION,
            'rtr_notification_id' => $notification->id,
        ]);

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::never())
            ->method('dispatch');

        $notificationProcessor = new RtrNotificationProcessor(
            rtrResponseLogRepository: $this->rtrResponseLogRepository,
            rtrResponseLogService: $this->rtrResponseLogService,
            eventDispatcher: $dispatcher,
            logger: $this->logger,
        );

        $notificationProcessor->process($notification);

        self::assertDatabaseCount('rtr_response_log', 1);
    }
}
