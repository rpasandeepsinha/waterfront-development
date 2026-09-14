<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SslDeploymentController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\CsrPersistanceStrategies\OpenSslExtensionStrategy;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\Storage\CertificateCloud;
use Waterfront\Domain\Ssl\Storage\KeyCloud;
use Waterfront\Domain\Ssl\Storage\LocalDisk;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrSslService;

#[CoversClass(SslDeploymentController::class)]
class SslControllerTest extends IntegrationTestCase
{
    private const string DOMAIN = 'sandwaveio.testing';

    /**
     * @var array<string, string|array<string, string>>
     */
    private const array DEFAULT_CUSTOMER_DATA = [
        'name' => 'Sandwaveio',
        'department' => 'Team Aquatic',
        'address' => [
            'city' => 'Vlissingen',
            'province' => 'Zeeland',
            'country_code' => 'NL',
        ],
    ];

    private Customer $customer;

    private Product $product;

    private Subscription $subscription;

    private Provider $sslProvider;

    private string $localTestDisk = 'certificate_downloader_test';

    public function setUp(): void
    {
        parent::setUp();

        $sslDisk = $this->getConfiguration()->getAsString('filesystems.cloud');

        $sslDisk = Storage::fake($sslDisk);
        Storage::fake($this->localTestDisk);

        $this->app->singleton(function () use ($sslDisk): CsrManager {
            $cryptoKey = $this->getConfiguration()->getAsString('app.ssl_crypto');

            return new CsrManager(
                self::resolve(OpenSslExtensionStrategy::class),
                new KeyCloud($cryptoKey, $sslDisk),
                new LocalDisk(
                    $this->app->storagePath('framework/testing/disks/' . $this->localTestDisk),
                ),
            );
        });

        $this->app->singleton(CertificateManager::class, fn (): CertificateManager => new CertificateManager(
            new CertificateCloud($sslDisk),
        ));

        $this->sslProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $productGroup = new ProductGroupFactory()->ssl()->createOne();

        $this->product = new ProductFactory()->for($productGroup)->createOne();

        $this->customer = new CustomerFactory()->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'provider_id' => $this->sslProvider->id,
        ]);

        $rtrMock = self::mock(RtrSslService::class);
        $rtrMock->shouldReceive('getSslCnameRecord')->andReturn(null);
        $this->app->bind(RtrSslService::class, fn () => $rtrMock);

        $this->saveMainCertificate();
        $this->savePrivateKey();
        $this->saveRootKey();
        $this->saveIntermediateKey();
    }

    #[Test]
    public function adminCanDownloadPrivateKey(): void
    {
        $response = $this->actingAsEmployee()->get($this->downloadRoute('key', $this->subscription->uuid))->assertOk();

        self::assertStringStartsWith('-----BEGIN PRIVATE KEY-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END PRIVATE KEY-----' . PHP_EOL, strval($response->getContent()));
        $response->assertHeader('Content-Type', 'application/pkcs8');
        $response->assertHeader('Content-Disposition', sprintf('attachment; filename="%s.key"', self::DOMAIN));
    }

    #[Test]
    public function adminCanDownloadCertificate(): void
    {
        $response = $this->actingAsEmployee()->get($this->downloadRoute('crt', $this->subscription->uuid))->assertOk();

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END CERTIFICATE-----', strval($response->getContent()));
        $response->assertHeader('Content-Type', 'application/x-x509-user-cert');
        $response->assertHeader('Content-Disposition', sprintf('attachment; filename="%s.crt"', self::DOMAIN));
    }

    #[Test]
    public function adminCanDownloadRootCertificate(): void
    {
        $response = $this->actingAsEmployee()->get($this->downloadRoute('root', $this->subscription->uuid))->assertOk();

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END CERTIFICATE-----', strval($response->getContent()));
    }

    #[Test]
    public function adminCanDownloadIntermediateCertificate(): void
    {
        $response = $this->actingAsEmployee()
            ->get($this->downloadRoute('intermediate', $this->subscription->uuid))
            ->assertOk();

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END CERTIFICATE-----', strval($response->getContent()));
    }

    #[Test]
    public function returnsNotFoundWhenSubscriptionHasNoSslDeployment(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'domain' => 'no-deployment.' . self::DOMAIN,
                'technical_status' => DomainStatus::ACTIVE->value,
            ]);

        $this->actingAsEmployee()->get($this->downloadRoute('crt', $subscription->uuid))->assertNotFound();
    }

    #[Test]
    public function returnsNotFoundWhenCertificateTypeUnavailable(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne([
                'domain' => 'no-certs.' . self::DOMAIN,
                'technical_status' => DomainStatus::ACTIVE->value,
            ]);

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->sslProvider->id,
        ]);

        $this->actingAsEmployee()->get($this->downloadRoute('crt', $subscription->uuid))->assertNotFound();
    }

    #[Test]
    public function returnsNotFoundWhenSubscriptionAdministrativelyEnded(): void
    {
        $subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->administrativeStatusArchived()
            ->createOne([
                'domain' => 'archived.' . self::DOMAIN,
                'technical_status' => DomainStatus::ACTIVE->value,
            ]);

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->sslProvider->id,
        ]);

        $this->actingAsEmployee()->get($this->downloadRoute('crt', $subscription->uuid))->assertNotFound();
    }

    #[Test]
    public function unauthenticatedRequestIsRejected(): void
    {
        $this->getJson($this->downloadRoute('crt', $this->subscription->uuid))->assertUnauthorized();
    }

    private function downloadRoute(string $type, string $uuid): string
    {
        return $this->generateRoute('admin.ssl.download', ['type' => $type, 'uuid' => $uuid, 'sslDeployment' => $uuid]);
    }

    private function savePrivateKey(): void
    {
        $csrManager = self::resolve(CsrManager::class);
        $csrManager->create(self::DEFAULT_CUSTOMER_DATA, self::DOMAIN);
    }

    private function saveMainCertificate(): void
    {
        $certificate = require __DIR__ . '/../../../../Domain/Ssl/data/main_certificate.php';
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveMainCertificate(self::DOMAIN, $certificate);
    }

    private function saveRootKey(): void
    {
        $certificate = require __DIR__ . '/../../../../Domain/Ssl/data/root_certificate.php';
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveRootCertificate(self::DOMAIN, $certificate);
    }

    private function saveIntermediateKey(): void
    {
        $certificate = require __DIR__ . '/../../../../Domain/Ssl/data/intermediate_certificate.php';
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveIntermediateCertificate(self::DOMAIN, $certificate);
    }
}
