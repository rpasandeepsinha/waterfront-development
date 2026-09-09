<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use Spatie\SslCertificate\Downloader;
use Spatie\SslCertificate\SslCertificate;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\SslMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CertificateService;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(SslMigrationController::class)]
class SslMigrationControllerTest extends IntegrationTestCase
{
    private const string TEST_DOMAIN = 'ssl-test-domain.testing';

    private Customer $customer;

    private Subscription $sslSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->customer = CustomerFactory::new()->createOne();

        $extensionSslGroup = ProductGroupFactory::new()->ssl()->createOne();

        $sslProduct = ProductFactory::new()->for($extensionSslGroup)->createOne([
            'slug' => 'ssl_single_domain',
        ]);

        $this->sslSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($sslProduct)
            ->technicalStatusOk()
            ->createOne();

        ProviderFactory::new()->sslRtr()->createOne(['default' => true]);

        $sslPlaceholderProvider = ProviderFactory::new()->sslPlaceholder()->createOne();

        SslDeploymentFactory::new()->createOne([
            'provider_id' => $sslPlaceholderProvider->id,
            'subscription_uuid' => $this->sslSubscription->uuid,
            'certificate_id' => null,
        ]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne(['reference_subscription_id' => 'sub_1337_1']);
        $this->sslSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($this->customer);
    }

    #[Test]
    public function migrateSslSubscriptions(): void
    {
        $expireTimestamp = 1723808240;
        $expireDate = CarbonImmutable::createFromTimestampUTC($expireTimestamp);

        $payload = require __DIR__ . '/data/list_certificates_response.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new GuzzleResponse(200, [], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $this->app->bind(RealtimeRegister::class, static fn (): RealtimeRegister => $rtrSdk);

        $certificateService = new CertificateService(
            csrManager: self::createStub(CsrManager::class),
            certificateManager: self::createStub(CertificateManager::class),
            realtimeRegister: self::resolve(RealtimeRegister::class),
            certificateDownloader: $downloader = self::mock(Downloader::class),
            logger: self::createStub(LoggerInterface::class),
            sslDeploymentRepository: self::createStub(DeploymentRepository::class)
        );
        $downloader->expects('downloadCertificateFromUrl')
            ->once()
            ->with($this->sslSubscription->domain)
            ->andReturn(
                new SslCertificate([
                    'validTo_time_t' => $expireTimestamp,
                ])
            );

        $this->app->bind(CertificateService::class, fn (): CertificateService => $certificateService);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_ssl', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'message' => 'Created jobs to migrate ssl for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [
                                $this->sslSubscription->id,
                            ]),
                        ],
                        'baseParameters' => [],
                    ],
                ],
            ]);

        $this->sslSubscription->refresh();

        self::assertSame(ProviderSlug::REALTIME_REGISTER, $this->sslSubscription->sslDeployment?->provider->slug);
        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->sslSubscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->sslSubscription->technical_status);
        self::assertNull($this->sslSubscription->sslDeployment?->certificate_id, 'Certificate id should be null');
        self::assertSame($expireDate->format(DateTimeFormat::DATE), $this->sslSubscription->sslDeployment?->expire_date?->format(DateTimeFormat::DATE));

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function migrateInvalidSslSubscriptions(): void
    {
        $this->sslSubscription->sslDeployment()->delete();

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_ssl', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            )->assertStatus(Response::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Ssl migration step not allowed for subscription: No Ssl Deployment could be found for this subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->sslSubscription->id,
                        ],
                    ],
                ],
                'success' => [],
            ]);
    }
}
