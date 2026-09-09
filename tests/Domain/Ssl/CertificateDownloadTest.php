<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl;

use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\SslController;
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

#[CoversClass(SslController::class)]
class CertificateDownloadTest extends IntegrationTestCase
{
    private const string DOMAIN = 'sandwaveio.testing';

    /**
     * @var array<string, string|array<string, string>>
     */
    private const array DEFAULT_CUSTOMER_DATA = [
        'name'       => 'Sandwaveio',
        'department' => 'Team Aquatic',
        'address'    => [
            'city'         => 'Vlissingen',
            'province'     => 'Zeeland',
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

        $sslFilesystem = Storage::fake($sslDisk);
        Storage::fake($this->localTestDisk);

        $this->app->singleton(function () use ($sslFilesystem): CsrManager {
            $cryptoKey = $this->getConfiguration()->getAsString('app.ssl_crypto');

            return new CsrManager(
                self::resolve(OpenSslExtensionStrategy::class),
                new KeyCloud($cryptoKey, $sslFilesystem),
                new LocalDisk(
                    $this->app->storagePath('framework/testing/disks/' . $this->localTestDisk)
                )
            );
        });

        $this->app->singleton(CertificateManager::class, fn (): CertificateManager => new CertificateManager(
            new CertificateCloud($sslFilesystem)
        ));

        $this->sslProvider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::OPEN_PROVIDER, 'enabled' => true, 'default' => true]);

        $productGroup = new ProductGroupFactory()->ssl()->createOne();

        $this->product = new ProductFactory()->for($productGroup)->createOne([
            'name' => 'Single Domain',
            'slug' => 'ssl_single_domain',
        ]);

        $this->customer = new CustomerFactory()->createOne();

        $this->subscription = new SubscriptionFactory()->for($this->product)->for($this->customer)->createOne([
            'domain' => self::DOMAIN,
            'gross_price' => 100,
            'net_price' => 100,
            'technical_status' => DomainStatus::ACTIVE->value,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'provider_id' => $this->sslProvider->id,
        ]);

        $rtrMock = self::mock(RtrSslService::class);
        $rtrMock->shouldReceive('getSslCnameRecord')
            ->andReturn(null);
        $this->app->bind(RtrSslService::class, fn () => $rtrMock);

        $this->saveMainCertificate();
        $this->savePrivateKey();
        $this->saveRootKey();
        $this->saveIntermediateKey();
    }

    /**
     * Test that /partners/api/v1/services DOES return with status code 200.
     */
    #[Test]
    public function servicesEndpointIsAnOkResponse(): void
    {
        $this->makeServicesRequest()->assertOk();
    }

    /**
     * Test that /partners/api/v1/services DOES return a subscription with (crt|key) certificate download links.
     */
    #[Test]
    public function servicesEndpointContainsCertificateLinks(): void
    {
        $downloadLinks = $this->getCertificateDownloadLinks();

        self::assertCount(5, $downloadLinks);

        self::assertStringContainsString('ssl/download?type=csr', $downloadLinks['CSR']);
        self::assertStringContainsString('ssl/download?type=key', $downloadLinks['Private key']);
        self::assertStringContainsString('ssl/download?type=root', $downloadLinks['Root']);
        self::assertStringContainsString('ssl/download?type=intermediate', $downloadLinks['Intermediate']);
        self::assertStringContainsString('ssl/download?type=crt', $downloadLinks['Certificate']);
    }

    /**
     * Test that /partners/api/v1/ssl/download?type=key&uuid={uuid} CAN download the *.key file.
     */
    #[Test]
    public function canDownloadPrivateKey(): void
    {
        $downloadLinks = $this->getCertificateDownloadLinks();
        $keyDownloadLink = $downloadLinks['Private key'];

        $response = $this
            ->actingAsCustomer($this->customer)
            ->get($keyDownloadLink)
            ->assertOk();

        self::assertStringStartsWith('-----BEGIN PRIVATE KEY-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END PRIVATE KEY-----' . PHP_EOL, strval($response->getContent()));
    }

    /**
     * Test that /partners/api/v1/ssl/download?type=key&uuid={uuid} CAN download the *.crt file.
     */
    #[Test]
    public function canDownloadRootCertificate(): void
    {
        $downloadLinks = $this->getCertificateDownloadLinks();
        $rootDownloadLink = $downloadLinks['Root'];

        $response = $this
            ->actingAsCustomer($this->customer)
            ->get($rootDownloadLink)
            ->assertOk();

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END CERTIFICATE-----', strval($response->getContent()));
    }

    /**
     * Test that /partners/api/v1/ssl/download?type=key&uuid={uuid} CAN download the *.crt file.
     */
    #[Test]
    public function canDownloadIntermediateCertificate(): void
    {
        $downloadLinks = $this->getCertificateDownloadLinks();
        $intermediateDownloadLink = $downloadLinks['Intermediate'];

        $response = $this
            ->actingAsCustomer($this->customer)
            ->get($intermediateDownloadLink)
            ->assertOk();

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END CERTIFICATE-----', strval($response->getContent()));
    }

    /**
     * Test that /partners/api/v1/ssl/download?type=crt&uuid={uuid} CAN download the main.crt file.
     */
    #[Test]
    public function canDownloadCertificate(): void
    {
        $downloadLinks = $this->getCertificateDownloadLinks();
        $crtDownloadLink = $downloadLinks['Certificate'];

        $response = $this
            ->actingAsCustomer($this->customer)
            ->get($crtDownloadLink)
            ->assertOk();

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', strval($response->getContent()));
        self::assertStringEndsWith('-----END CERTIFICATE-----', strval($response->getContent()));
    }

    /**
     * Test that a user CAN NOT download *.crt files from another user
     * Let's test this with a user that DOES NOT have certificates.
     */
    #[Test]
    public function anotherUserIsUnauthorizedForCertificateDownload(): void
    {
        $evilCustomer = new CustomerFactory()->createOne();

        $notMyCertificates = $this->getCertificateDownloadLinks();
        $notMyCrtDownloadLink = $notMyCertificates['Certificate'];

        $evilResponse = $this
            ->actingAsCustomer($evilCustomer)
            ->get($notMyCrtDownloadLink);
        $evilResponse->assertNotFound();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIs('The response is not a streamed response.');
        $evilResponse->streamedContent();
    }

    /**
     * Test that a user CAN NOT download *.key files from another user
     * Let's test this with a user that DOES have certificates.
     */
    #[Test]
    public function anotherUserIsUnauthorizedForKeyDownload(): void
    {
        $evilCustomer = new CustomerFactory()->createOne();

        // This user should not be able to register an SSL certificate for another clients' domain!!!
        // So let's have this user registers another domain:
        $evilDomain = 'evil-' . self::DOMAIN;

        $subscription = new SubscriptionFactory()->for($this->product)->for($evilCustomer)->createOne([
            'domain' => $evilDomain,
            'gross_price' => 100,
            'net_price' => 100,
            'technical_status' => DomainStatus::ACTIVE->value,
            'contract_period' => 12,
            'billing_period' => 12,
        ]);

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->sslProvider->id,
        ]);

        $myServicesResponse = $this->actingAsCustomer($evilCustomer)
            ->getJson($this->generateRoute('partners.ssl.deployment', ['sslDeployment' => $this->subscription->uuid]));

        $myServicesResponse->assertForbidden();
    }

    /**
     * Make a request to /partners/api/v1/services.
     */
    private function makeServicesRequest(): TestResponse
    {
        return $this
            ->actingAsCustomer($this->customer)
            ->json('get', $this->generateRoute('partners.ssl.deployment', ['sslDeployment' => $this->subscription->uuid]));
    }

    /**
     * @return string[]
     */
    private function getCertificateDownloadLinks(): array
    {
        $servicesResponse = $this->makeServicesRequest();

        return $this->getReceivedCertificates($servicesResponse);
    }

    /**
     * @return string[]
     */
    private function getReceivedCertificates(TestResponse $response): array
    {
        /** @var string[] $json */
        $json = (array) $response->json('certificates');
        return $json;
    }

    private function savePrivateKey(): void
    {
        $csrManager = self::resolve(CsrManager::class);
        $csrManager->create(self::DEFAULT_CUSTOMER_DATA, self::DOMAIN);
    }

    private function saveMainCertificate(): void
    {
        $certificate = require __DIR__ . '/data/main_certificate.php';
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveMainCertificate(self::DOMAIN, $certificate);
    }

    private function saveRootKey(): void
    {
        $certificate = require __DIR__ . '/data/root_certificate.php';
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveRootCertificate(self::DOMAIN, $certificate);
    }

    private function saveIntermediateKey(): void
    {
        $certificate = require __DIR__ . '/data/intermediate_certificate.php';
        $certificateManager = self::resolve(CertificateManager::class);
        $certificateManager->saveIntermediateCertificate(self::DOMAIN, $certificate);
    }
}
