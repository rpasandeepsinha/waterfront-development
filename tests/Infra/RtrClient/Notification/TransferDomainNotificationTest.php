<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Notification;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\Notification as RtrNotification;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\PollNotifications;
use Waterfront\Domain\Domains\Mailers\MailDomainCreationFailed;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;

#[CoversClass(PollNotifications::class)]
class TransferDomainNotificationTest extends IntegrationTestCase
{
    public const string DOMAIN = 'sandwave.io';

    private Subscription $subscription;

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne();

        $this->subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne([
            'domain' => self::DOMAIN,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        new TemplateFactory()->createOne([
            'slug' => MailDomainCreationFailed::getTemplateSlug(),
        ]);

        new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne(['subscription_uuid' => $this->subscription->uuid]);
    }

    #[Test]
    public function outTransferDomainNotification(): void
    {
        $rtrNotification = RtrNotification::fromArray([
            'id' => 1398330132,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'sandwave.io' was approved by the current domain name holder",
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'OUT',
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $fileLocation = __DIR__ . '/../data/domain_transfer_out_status.php';
        $rtrClient = $this->createRtrFdk($rtrNotification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function outInternalTransferDomainNotification(): void
    {
        $rtrNotification = RtrNotification::fromArray([
            'id' => 1398330132,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'sandwave.io' was approved by the current domain name holder",
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'OUT_INTERNAL',
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $fileLocation = __DIR__ . '/../data/domain_transfer_out_status.php';
        $rtrClient = $this->createRtrFdk($rtrNotification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }

    #[Test]
    public function outTransferDomainNotificationFailed(): void
    {
        $rtrNotification = RtrNotification::fromArray([
            'id' => 1398330132,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'sandwave.io' was approved by the current domain name holder",
            'process' => 1398322046,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'OUT',
                'domainName' => self::DOMAIN,
                'subjectStatus' => 'Failed',
            ],
        ]);

        $fileLocation = __DIR__ . '/../data/domain_transfer_out_status_failed.php';
        $rtrClient = $this->createRtrFdk($rtrNotification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[Test]
    public function incomingTransferNotification(): void
    {
        $rtrNotification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => "Transfer of 'sandwave.io' was completed",
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'IN',
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $fileLocation = __DIR__ . '/../data/domain_transfer_in_status.php';
        $rtrClient = $this->createRtrFdk($rtrNotification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
    }

    #[DataProvider('transferPendingDataProvider')]
    #[Test]
    public function pendingTransferNotification(string $fileLocation): void
    {
        self::assertEmailsSend([
            MailDomainCreationFailed::class,
        ]);
        $rtrNotification = RtrNotification::fromArray([
            'id' => 123,
            'eventType' => EventType::TransferDomainEvent->value,
            'notificationType' => NotificationType::TransferDomainNotification->value,
            'fireDate' => '2023-09-08T11:01:10Z',
            'message' => 'Transfer is pending approval from authorized contact',
            'process' => 5,
            'customer' => 'versiosandwave',
            'isAsync' => false,
            'payload' => [
                'transferType' => 'IN',
                'subjectStatus' => 'OK',
                'domainName' => self::DOMAIN,
            ],
        ]);

        $rtrClient = $this->createRtrFdk($rtrNotification, $fileLocation);

        $this->instance(RealtimeRegister::class, $rtrClient);

        $this->artisan(PollNotifications::class);
        $this->subscription->refresh();

        self::assertSame(TechnicalStatus::PENDING->value, $this->subscription->technical_status);
    }

    public static function transferPendingDataProvider(): Iterator
    {
        yield [__DIR__ . '/../data/domain_transfer_pending_status.php'];
        yield [__DIR__ . '/../data/domain_transfer_pendingfoa_status.php'];
        yield [__DIR__ . '/../data/domain_transfer_pendingwhois_status.php'];
        yield [__DIR__ . '/../data/domain_transfer_pendingvalidation_status.php'];
    }

    private function createRtrFdk(RtrNotification $rtrNotification, string $transferInfoFileLocation): RealtimeRegister
    {
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
