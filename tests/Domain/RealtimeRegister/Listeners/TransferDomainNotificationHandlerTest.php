<?php

declare(strict_types=1);

namespace Tests\Domain\RealtimeRegister\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\DomainTransferStatus;
use RealtimeRegister\Domain\Notification;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Services\DomainProviderHistory;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;
use Waterfront\Infra\RtrClient\Action\ParseRtrTransferStatusToWfStatusAction;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Enums\TransferTypeType;
use Waterfront\Infra\RtrClient\Helpers\NotificationHelper;
use Waterfront\Infra\RtrClient\Services\NotificationHandlers\TransferDomainNotificationHandler;
use Waterfront\Infra\RtrClient\Services\RtrResponseLogService;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(TransferDomainNotificationHandler::class)]
class TransferDomainNotificationHandlerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'sandwave.testing';

    private Subscription $subscription;

    private TransferDomainNotificationHandler $transferDomainNotificationHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $extensionProduct = ProductFactory::new()->nlDomain()->createOne();

        $this->subscription = SubscriptionFactory::new()
            ->forDomain(self::DOMAIN)
            ->for(CustomerFactory::new())
            ->for($extensionProduct)
            ->administrativeStatusActive()
            ->technicalStatusPending()
            ->createOne();

        $transferStatus = DomainTransferStatus::fromArray([
            'domainName' => self::DOMAIN,
            'status' => 'completed',
            'requestedDate' => '2026-03-10T15:28:44Z',
            'type' => 'IN',
            'processId' => 1_234_567_890,
        ]);

        $rtrService = self::createStub(RtrService::class);
        $rtrService->method('transferInfo')->willReturn($transferStatus);

        $this->transferDomainNotificationHandler = new TransferDomainNotificationHandler(
            subscriptionService: self::resolve(SubscriptionService::class),
            subscriptionTerminateService: self::createStub(SubscriptionTerminateService::class),
            domainProviderHistory: self::createStub(DomainProviderHistory::class),
            rtrService: $rtrService,
            rtrResponseLogPersister: self::createStub(RtrResponseLogService::class),
            parseRtrTransferStatusToWfStatusAction: self::resolve(ParseRtrTransferStatusToWfStatusAction::class),
            logger: self::createStub(LoggerInterface::class),
            notificationHelper: self::resolve(NotificationHelper::class)
        );
    }

    #[Test]
    public function completedEuTldTransfer(): void
    {
        $notification = Notification::fromArray([
            'id' => 1,
            'fireDate' => '2026-03-10T15:28:44Z',
            'message' => sprintf("Transfer domain '%s' is completed", self::DOMAIN),
            'customer' => 'Sandwave',
            'process' => 1_234_567_890,
            'eventType' => EventType::TransferDomainEvent->value,
            'statusDetail' => 'Transfer completed',
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'isAsync' => true,
            'domainName' => self::DOMAIN,
            'subjectStatus' => 'OK',
            'expiryDate' => '2027-08-27T22:00:00Z',
            'transferType' => TransferTypeType::IN_INTERNAL->value,
            'processIdentifier' => self::DOMAIN,
            'processType' => 'domain',
        ]);

        $this->transferDomainNotificationHandler->handle($notification);

        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }
}
