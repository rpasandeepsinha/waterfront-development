<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Request;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\Notification as RtrNotification;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\PollNotifications;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Enums\EventType;
use Waterfront\Infra\RtrClient\Enums\NotificationType;
use Waterfront\Infra\RtrClient\Job\DownloadCertificate;

#[CoversClass(PollNotifications::class)]
class CertificateRequestTest extends IntegrationTestCase
{
    private const string DOMAIN = 'sandwave.io';
    private const int TEST_CERTIFICATE_ID = 77_665_544;

    private SslDeployment $sslDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $group = new ProductGroupFactory()->ssl()->createOne();
        $product = new ProductFactory()->for($group)->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        $rtrSslProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $rtrSslProvider->id,
            'request_id' => '1234567890',
            'certificate_id' => null,
        ]);
    }

    #[Test]
    public function newPolledCertificateRequestNotificationDispatchMustBeHandled(): void
    {
        $now = CarbonImmutable::now();

        $notification = RtrNotification::fromArray([
            'id' => 1,
            'fireDate' => $now->toIso8601String(),
            'readDate' => $now->toIso8601String(),
            'acknowledgeDate' => null,
            'message' => 'Certificate request completed',
            'reason' => 'Foo',
            'customer' => 'Bar',
            'process' => 1_234_567_890,
            'eventType' => EventType::RequestCertificateEvent->value,
            'notificationType' => NotificationType::SSLCertificateNotification->value,
            'isAsync' => false,
            'payload' => [
                'certificateId' => self::TEST_CERTIFICATE_ID,
                'domainName' => null,
                'transferType' => null,
                'subjectStatus' => null,
            ],
        ]);

        $rtrClient = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: (string) json_encode([
                    'entities' => [$notification->toArray()],
                ]),
            ),
            new Response(
                status: 201,
            ),
        ]);

        $dispatcherMock = self::createMock(Dispatcher::class);
        $dispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(
                ...self::withConsecutive(
                    [self::callback(
                        fn (UpdateSslExpireDate $event) => (
                            $event->sslDeployment->certificate_id === self::TEST_CERTIFICATE_ID
                        ),
                    )],
                    [self::callback(
                        fn (DownloadCertificate $event) => (
                            $event->getSslDeployment()->certificate_id === self::TEST_CERTIFICATE_ID
                        ),
                    )],
                ),
            );

        $this->instance(RealtimeRegister::class, $rtrClient);
        $this->instance(Dispatcher::class, $dispatcherMock);

        $this->artisan(PollNotifications::class);

        $this->sslDeployment->refresh();
        self::assertSame(self::TEST_CERTIFICATE_ID, $this->sslDeployment->certificate_id);
    }

    #[Test]
    public function newPolledCertificateRequestNotificationFailCancelledSubscription(): void
    {
        $now = CarbonImmutable::now();

        $notification = RtrNotification::fromArray([
            'id' => 1,
            'fireDate' => $now->toIso8601String(),
            'readDate' => $now->toIso8601String(),
            'acknowledgeDate' => null,
            'message' => 'Certificate request completed',
            'reason' => 'Foo',
            'customer' => 'Bar',
            'process' => 1_234_567_890,
            'eventType' => EventType::RequestCertificateEvent->value,
            'notificationType' => NotificationType::SSLCertificateNotification->value,
            'isAsync' => false,
            'payload' => [
                'certificateId' => self::TEST_CERTIFICATE_ID,
                'transferType' => null,
                'subjectStatus' => null,
                'domainName' => null,
            ],
        ]);

        $rtrClient = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: (string) json_encode([
                    'entities' => [$notification->toArray()],
                ]),
            ),
            new Response(
                status: 201,
            ),
        ]);

        $dispatcherMock = self::createMock(Dispatcher::class);
        $dispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(
                ...self::withConsecutive(
                    [self::callback(
                        fn (UpdateSslExpireDate $event) => (
                            $event->sslDeployment->certificate_id === self::TEST_CERTIFICATE_ID
                        ),
                    )],
                    [self::callback(
                        fn (DownloadCertificate $event) => (
                            $event->getSslDeployment()->certificate_id === self::TEST_CERTIFICATE_ID
                        ),
                    )],
                ),
            );

        $this->instance(RealtimeRegister::class, $rtrClient);
        $this->instance(Dispatcher::class, $dispatcherMock);

        $this->sslDeployment->delete();
        self::assertTrue($this->sslDeployment->trashed());

        $this->artisan(PollNotifications::class);
    }

    #[Test]
    public function newPolledCertificateRequestCompleted(): void
    {
        $now = CarbonImmutable::now();

        $notification = RtrNotification::fromArray([
            'id' => 2197609151,
            'eventType' => EventType::RequestCertificateEvent->value,
            'notificationType' => NotificationType::SSLCertificateNotification->value,
            'fireDate' => $now->toIso8601String(),
            'acknowledgeDate' => $now->toIso8601String(),
            'message' => 'Certificate request completed',
            'process' => 1234567890,
            'customer' => 'Bar',
            'isAsync' => true,
            'validationType' => 'DOMAIN_VALIDATION',
            'product' => 'ssl_sectigo',
            'providerId' => '2679941832',
            'expiryDate' => $now->toIso8601String(),
            'certificateId' => self::TEST_CERTIFICATE_ID,
            'processType' => 'certificate',
            'processIdentifier' => self::DOMAIN,
        ]);

        $rtrClient = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: (string) json_encode([
                    'entities' => [$notification->toArray()],
                ]),
            ),
            new Response(
                status: 201,
            ),
        ]);

        $dispatcherMock = self::createMock(Dispatcher::class);
        $dispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(
                ...self::withConsecutive(
                    [self::callback(
                        fn (UpdateSslExpireDate $event) => (
                            $event->sslDeployment->certificate_id === self::TEST_CERTIFICATE_ID
                        ),
                    )],
                    [self::callback(
                        fn (DownloadCertificate $event) => (
                            $event->getSslDeployment()->certificate_id === self::TEST_CERTIFICATE_ID
                        ),
                    )],
                ),
            );

        $this->instance(RealtimeRegister::class, $rtrClient);
        $this->instance(Dispatcher::class, $dispatcherMock);

        $this->artisan(PollNotifications::class);
    }

    #[Test]
    public function completedCertificateNotificationRecoversMissingRequestIdFromRtrCertificate(): void
    {
        $now = CarbonImmutable::now();
        $domain = 'testqa137check.nl';
        $rtrProcessId = 2_323_533_016;
        $certificateId = 2_323_724_604;
        $certificateListResponse = (string) file_get_contents(__DIR__
        . '/../../../Domain/Ssl/data/list_certificates_response.json');

        $product = ProductFactory::new()->for($this->sslDeployment->subscription->product->productGroup)->createOne();

        $subscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($product)
            ->createOne([
                'domain' => $domain,
                'technical_status' => TechnicalStatus::PENDING->value,
            ]);

        $provider = ProviderFactory::new()->sslRtr()->createOne();
        $sslDeployment = SslDeploymentFactory::new()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
            'request_id' => null,
            'certificate_id' => null,
        ]);

        $notification = RtrNotification::fromArray([
            'id' => 2522399005,
            'eventType' => EventType::RequestCertificateEvent->value,
            'notificationType' => NotificationType::SSLCertificateNotification->value,
            'fireDate' => $now->toIso8601String(),
            'message' => 'Certificate request completed',
            'process' => $rtrProcessId,
            'customer' => 'yourhostingsw',
            'isAsync' => true,
            'validationType' => 'DOMAIN_VALIDATION',
            'product' => 'ssl_sectigo',
            'providerId' => '3175824012',
            'expiryDate' => $now->addYear()->toIso8601String(),
            'certificateId' => $certificateId,
            'processType' => 'certificate',
            'processIdentifier' => $domain,
        ]);

        $rtrClient = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: (string) json_encode([
                    'entities' => [$notification->toArray()],
                ]),
            ),
            new Response(
                status: 200,
                body: $certificateListResponse,
            ),
            new Response(status: 201),
        ]);

        $dispatcherMock = self::createMock(Dispatcher::class);
        $dispatcherMock
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(
                ...self::withConsecutive(
                    [self::callback(
                        fn (UpdateSslExpireDate $event): bool => (
                            $event->sslDeployment->certificate_id === $certificateId
                        ),
                    )],
                    [self::callback(
                        fn (DownloadCertificate $event): bool => (
                            $event->getSslDeployment()->certificate_id === $certificateId
                        ),
                    )],
                ),
            );

        $this->instance(RealtimeRegister::class, $rtrClient);
        $this->instance(Dispatcher::class, $dispatcherMock);

        $this->artisan(PollNotifications::class);

        $sslDeployment->refresh();
        $subscription->refresh();

        self::assertSame($rtrProcessId, $sslDeployment->request_id);
        self::assertSame($certificateId, $sslDeployment->certificate_id);
        self::assertSame(TechnicalStatus::OK->value, $subscription->technical_status);
    }
}
