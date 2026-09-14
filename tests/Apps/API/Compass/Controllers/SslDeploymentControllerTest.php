<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Exception;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SslDeploymentController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Ssl\Jobs\RetrySslJob;
use Waterfront\Domain\Ssl\Jobs\SetSslDnsVerifyRecordJob;
use Waterfront\Domain\Ssl\Services\CertificateRetriever;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;

#[CoversClass(SslDeploymentController::class)]
class SslDeploymentControllerTest extends IntegrationTestCase
{
    public const string DOMAIN = 'test.nl';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function showSslDeployment(): void
    {
        $certificateRetriever = self::createMock(CertificateRetriever::class);
        $certificateRetriever->expects(self::once())->method('getAvailableCertificateTypes')->willReturn([]);
        $this->app->bind(CertificateRetriever::class, fn () => $certificateRetriever);

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $deployment = new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.ssl', ['domain' => self::DOMAIN]))
            ->assertOk();

        $content = $response->json();

        self::assertIsArray($content);
        self::assertSame($deployment->id, $content['id']);
        self::assertSame($subscription->uuid, $content['administrative_subscription_uuid']);
        self::assertSame(self::DOMAIN, $content['domain']);
        self::assertSame(ProviderSlug::REALTIME_REGISTER->value, $content['provider']);
        self::assertSame($deployment->status, $content['last_status']);
        self::assertSame([], $content['certificates']);
    }

    #[Test]
    public function showSslDeploymentCertificateUrlsPointAtAdminDownload(): void
    {
        $certificateRetriever = self::createMock(CertificateRetriever::class);
        $certificateRetriever
            ->expects(self::once())
            ->method('getAvailableCertificateTypes')
            ->willReturn(['Certificate' => 'crt']);
        $this->app->bind(CertificateRetriever::class, fn () => $certificateRetriever);

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.ssl', ['domain' => self::DOMAIN]))
            ->assertOk();

        $certificates = $response->json('certificates');

        self::assertIsArray($certificates);

        $certificateUrl = $certificates['Certificate'];
        self::assertIsString($certificateUrl);
        self::assertStringContainsString('/download?type=crt', $certificateUrl);
    }

    #[Test]
    public function showSslDeploymentNotFound(): void
    {
        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.domain.ssl', ['domain' => self::DOMAIN]))
            ->assertServerError()
            ->assertExactJson(['message' => 'The deployment could not be found']);
    }

    #[Test]
    public function retrySslDeploymentDispatchesJobAndReturnsNoContent(): void
    {
        Queue::fake();

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.ssl.retry', ['sslDeployment' => $subscription->uuid]))
            ->assertNoContent();

        Queue::assertPushed(RetrySslJob::class);
    }

    #[Test]
    public function retrySslDeploymentWithInvalidCsrReturnsUnprocessable(): void
    {
        Queue::fake();

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.ssl.retry', ['sslDeployment' => $subscription->uuid]), [
                'csr' => 'this-is-not-a-valid-csr',
            ])
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function setSslDnsVerifyRecordDispatchesJobAndReturnsNoContent(): void
    {
        Queue::fake();

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.ssl.set-dns-verify-record', [
                'sslDeployment' => $subscription->uuid,
            ]))
            ->assertNoContent();

        Queue::assertPushed(SetSslDnsVerifyRecordJob::class);
    }

    #[Test]
    public function updateProviderUpdatesTheSslDeploymentProvider(): void
    {
        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $currentProvider = new ProviderFactory()->sslRtr()->createOne();
        $newProvider = new ProviderFactory()->sslOpenProvider()->createOne();

        $deployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $currentProvider->id,
        ]);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.ssl.update-provider', [
                'sslDeployment' => $subscription->uuid,
                'provider' => $newProvider->id,
            ]))
            ->assertOk()
            ->assertExactJson(['message' => 'SSL provider updated successfully']);

        self::assertSame($newProvider->id, $deployment->refresh()->provider_id);
    }

    #[Test]
    public function updateProviderReturnsUnprocessableWhenProviderIsNotAnSslProvider(): void
    {
        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $currentProvider = new ProviderFactory()->sslRtr()->createOne();
        $nonSslProvider = new ProviderFactory()->hostingDirectAdmin()->createOne();

        new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $currentProvider->id,
        ]);

        $this->actingAsEmployee()
            ->putJson($this->generateRoute('admin.ssl.update-provider', [
                'sslDeployment' => $subscription->uuid,
                'provider' => $nonSslProvider->id,
            ]))
            ->assertUnprocessable()
            ->assertJson(['message' => 'Provider is not an SSL provider']);
    }

    #[Test]
    public function downloadCertificateFromRtrSucceeds(): void
    {
        $certificateDownloader = self::createMock(CertificateDownloader::class);
        $certificateDownloader->expects(self::once())->method('downloadForSslDeployment');
        $this->app->bind(CertificateDownloader::class, fn () => $certificateDownloader);

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $deployment = new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.ssl.ssl.sync-certificate-from-rtr', [
                'sslDeployment' => $deployment->subscription_uuid,
            ]))
            ->assertOk()
            ->assertJson(['message' => 'Certificate downloaded successfully.']);
    }

    #[Test]
    public function downloadCertificateFromRtrLogsAndRethrowsException(): void
    {
        $thrownException = new Exception('RTR connection failed');

        $certificateDownloader = self::createMock(CertificateDownloader::class);
        $certificateDownloader
            ->expects(self::once())
            ->method('downloadForSslDeployment')
            ->willThrowException($thrownException);
        $this->app->bind(CertificateDownloader::class, fn () => $certificateDownloader);

        $sslProduct = new ProductFactory()->sslSingleDomain()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($sslProduct)
            ->createOne(['domain' => self::DOMAIN]);

        $deployment = new SslDeploymentFactory()
            ->rtrProvider()
            ->createOne([
                'subscription_uuid' => $subscription->uuid,
            ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.ssl.ssl.sync-certificate-from-rtr', [
                'sslDeployment' => $deployment->subscription_uuid,
            ]))
            ->assertServerError();
    }
}
