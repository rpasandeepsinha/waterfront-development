<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification as RtrNotification;
use RuntimeException;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\PollNotifications;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Services\RtrNotificationProcessor;
use Waterfront\Infra\RtrClient\Services\RtrNotificationsService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(PollNotifications::class)]
class RtrPollNotificationsTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function pollMultipleNotifications(): void
    {
        $sslNotification = RtrNotification::fromArray([
            'id' => 1,
            'fireDate' => '2023-09-08T11:01:10Z',
            'readDate' => '2023-09-08T11:01:10Z',
            'acknowledgeDate' => null,
            'message' => 'Certificate request completed',
            'reason' => 'Foo',
            'customer' => 'Bar',
            'process' => 1_234_567_890,
            'eventType' => EventType::RequestCertificateEvent->value,
            'notificationType' => NotificationType::SSLCertificateNotification->value,
            'isAsync' => false,
            'payload' => [
                'certificateId' => 123211234,
                'transferType' => null,
                'subjectStatus' => null,
                'domainName' => null,
            ],
        ]);

        $transferNotification = RtrNotification::fromArray([
            'id' => 2,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'test.com' was approved by the current domain name holder",
            'process' => 1398322046,
            'customer' => 'versiosandwave',
            'transferType' => 'OUT',
            'isAsync' => false,
            'payload' => [
                'subjectStatus' => 'OK',
                'domainName' => 'test.com',
            ],
        ]);

        $notificationsService = self::createMock(RtrNotificationsService::class);
        $notificationsService->expects(self::once())
            ->method('pollForNextNotifications')
            ->with(10)
            ->willReturn([$sslNotification, $transferNotification]);
        $notificationsService->expects(self::exactly(2))
            ->method('acknowledgeNotification')
            ->with(
                ...self::withConsecutive(
                    [1],
                    [2],
                )
            );

        $notificationProcessor = self::createMock(RtrNotificationProcessor::class);
        $notificationProcessor->expects(self::exactly(2))
            ->method('process')
            ->with(
                ...self::withConsecutive(
                    [$sslNotification],
                    [$transferNotification],
                )
            );

        $this->instance(RtrNotificationsService::class, $notificationsService);
        $this->instance(RtrNotificationProcessor::class, $notificationProcessor);

        $this->artisan(PollNotifications::class);
    }

    #[Test]
    public function pollNoNotifications(): void
    {
        $notificationsService = self::createMock(RtrNotificationsService::class);
        $notificationsService->expects(self::once())
            ->method('pollForNextNotifications')
            ->with(10)
            ->willReturn([]);
        $notificationsService->expects(self::never())
            ->method('acknowledgeNotification');

        $notificationProcessor = self::createMock(RtrNotificationProcessor::class);
        $notificationProcessor->expects(self::never())
            ->method('process');

        $this->instance(RtrNotificationsService::class, $notificationsService);
        $this->instance(RtrNotificationProcessor::class, $notificationProcessor);

        $this->artisan(PollNotifications::class);
    }

    #[Test]
    public function pollContinuesWithNextNotificationWhenProcessorFails(): void
    {
        $transferNotification = RtrNotification::fromArray([
            'id' => 2,
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

        $sslNotification = RtrNotification::fromArray([
            'id' => 3,
            'fireDate' => '2023-09-08T11:01:10Z',
            'readDate' => '2023-09-08T11:01:10Z',
            'acknowledgeDate' => null,
            'message' => 'Certificate request completed',
            'reason' => 'Foo',
            'customer' => 'Bar',
            'process' => 1234567890,
            'eventType' => EventType::RequestCertificateEvent->value,
            'notificationType' => NotificationType::SSLCertificateNotification->value,
            'isAsync' => false,
            'payload' => [
                'certificateId' => 123211234,
                'transferType' => null,
                'subjectStatus' => null,
                'domainName' => null,
            ],
        ]);

        $notificationsService = self::createMock(RtrNotificationsService::class);
        $notificationsService->expects(self::once())
            ->method('pollForNextNotifications')
            ->with(10)
            ->willReturn([$transferNotification, $sslNotification]);
        $notificationsService->expects(self::exactly(2))
            ->method('acknowledgeNotification')
            ->with(
                ...self::withConsecutive(
                    [2],
                    [3],
                )
            );

        $exception = new RuntimeException('Processing failed');
        $expectedLogMeta = [
            'rtr_notification_id' => 2,
            'event_type' => EventType::TransferDomainEvent->value,
            'notification_type' => NotificationType::TransferDomainNotification->value,
            'process_id' => 1_398_322_046,
            'process_identifier' => $transferNotification->processIdentifier,
        ];
        $notificationProcessor = self::createMock(RtrNotificationProcessor::class);
        $notificationProcessor->expects(self::exactly(2))
            ->method('process')
            ->with(
                ...self::withConsecutive(
                    [$transferNotification],
                    [$sslNotification],
                )
            )
            ->willReturnOnConsecutiveCalls(
                self::throwException($exception),
                null,
            );
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'RTR notification processing failed',
                self::callback(function (array $context) use ($exception, $expectedLogMeta): bool {
                    self::assertSame($exception, $context[LoggingContextKeys::EXCEPTION]);
                    self::assertSame($expectedLogMeta, $context[LoggingContextKeys::META]);

                    return true;
                })
            );

        $this->instance(RtrNotificationsService::class, $notificationsService);
        $this->instance(RtrNotificationProcessor::class, $notificationProcessor);
        $this->instance(LoggerInterface::class, $logger);

        $this->artisan(PollNotifications::class);
    }
}
