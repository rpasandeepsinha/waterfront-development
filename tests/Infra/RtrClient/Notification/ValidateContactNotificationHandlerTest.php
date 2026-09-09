<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Notification;

use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\Notification;
use Tests\TestCase;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Job\UpdateValidatedDomainStatusJob;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\ValidateContactNotificationHandler;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(ValidateContactNotificationHandler::class)]
class ValidateContactNotificationHandlerTest extends TestCase
{
    #[Test]
    public function handleDispatchesIncludedDomainsUnchanged(): void
    {
        $includedDomains = [
            'validate-contact-5.com',
            'xn--validate-contact-6-5ec.com',
        ];
        $dispatchedDomainNames = [];
        $notification = Notification::fromArray([
            'id' => 2_551_463_873,
            'fireDate' => '2026-07-10T12:54:42Z',
            'message' => "Validating contact 'contact-handle' completed",
            'payload' => [
                'includedDomains' => $includedDomains,
            ],
            'eventType' => EventType::VALIDATE_CONTACT_EVENT->value,
            'notificationType' => NotificationType::NOTIFICATION->value,
            'isAsync' => true,
        ]);

        $dispatcher = self::createStub(Dispatcher::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(
                static function (UpdateValidatedDomainStatusJob $job) use (&$dispatchedDomainNames): void {
                    $dispatchedDomainNames[] = $job->domainName;
                },
            );

        $handler = new ValidateContactNotificationHandler(
            busDispatcher: $dispatcher,
            logger: self::createStub(LoggerInterface::class),
        );

        $handler->handle($notification, new RtrResponseLog());

        self::assertSame($includedDomains, $dispatchedDomainNames);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('missingOrEmptyIncludedDomainsDataProvider')]
    #[Test]
    public function handleSkipsNotificationWhenIncludedDomainsAreMissingOrEmpty(
        array $payload,
    ): void {
        $notification = Notification::fromArray([
            'id' => 2_551_463_873,
            'fireDate' => '2026-07-10T12:54:42Z',
            'message' => "Validating contact 'contact-handle' completed",
            'payload' => $payload,
            'eventType' => EventType::VALIDATE_CONTACT_EVENT->value,
            'notificationType' => NotificationType::NOTIFICATION->value,
            'isAsync' => true,
        ]);

        $dispatcher = self::createMock(Dispatcher::class);
        $dispatcher->expects(self::never())
            ->method('dispatch');

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'RTR contact validation skipped: included domains missing',
                [
                    LoggingContextKeys::META => [
                        'rtr_notification_id' => $notification->id,
                    ],
                ],
            );

        $handler = new ValidateContactNotificationHandler(
            busDispatcher: $dispatcher,
            logger: $logger,
        );

        $handler->handle($notification, new RtrResponseLog());
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function missingOrEmptyIncludedDomainsDataProvider(): array
    {
        return [
            'missing property' => [[]],
            'empty list' => [['includedDomains' => []]],
        ];
    }

    #[Test]
    public function handleSkipsInvalidCandidatesAndDispatchesValidDomains(): void
    {
        $validDomainName = 'validate-contact-5.com';
        $dispatchedDomainNames = [];
        $notification = Notification::fromArray([
            'id' => 2_551_463_873,
            'fireDate' => '2026-07-10T12:54:42Z',
            'message' => "Validating contact 'contact-handle' completed",
            'payload' => [
                'includedDomains' => [
                    null,
                    '',
                    $validDomainName,
                ],
            ],
            'eventType' => EventType::VALIDATE_CONTACT_EVENT->value,
            'notificationType' => NotificationType::NOTIFICATION->value,
            'isAsync' => true,
        ]);

        $dispatcher = self::createStub(Dispatcher::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(
                static function (UpdateValidatedDomainStatusJob $job) use (&$dispatchedDomainNames): void {
                    $dispatchedDomainNames[] = $job->domainName;
                },
            );

        $handler = new ValidateContactNotificationHandler(
            busDispatcher: $dispatcher,
            logger: self::createStub(LoggerInterface::class),
        );

        $handler->handle($notification, new RtrResponseLog());

        self::assertSame([$validDomainName], $dispatchedDomainNames);
    }
}
