<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Job;

use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Ssl\Jobs\InstallCertificate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Job\DownloadCertificate;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateDownloader;

#[CoversClass(DownloadCertificate::class)]
class DownloadCertificateTest extends IntegrationTestCase
{
    private const string TEST_CERT = <<<CERT_WRAP
-----BEGIN CERTIFICATE-----
not a main intermediate certificate
-----END CERTIFICATE-----
CERT_WRAP;

    private const string TEST_INTERMEDIATE = <<<CERT_WRAP
-----BEGIN CERTIFICATE-----
not a real intermediate certificate
-----END CERTIFICATE-----
CERT_WRAP;

    private const string TEST_ROOT = <<<CERT_WRAP
-----BEGIN CERTIFICATE-----
not a real root certificate
-----END CERTIFICATE-----
CERT_WRAP;

    private SslDeployment $sslDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne([
            'domain' => 'sandwave.io',
        ]);

        $rtrProvider = ProviderFactory::new()->createOne(['type' => ProviderType::SSL, 'slug' => ProviderSlug::REALTIME_REGISTER, 'enabled' => true, 'default' => true]);

        $this->sslDeployment = new SslDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $rtrProvider->getKey(),
            'request_id' => '1234567890',
            'certificate_id' => 77_665_544,
        ]);
    }

    #[Test]
    public function downloadJobMustStoreTheCertificates(): void
    {
        $downloadJob = new DownloadCertificate($this->sslDeployment);

        $rtrClient = MockedClientFactory::makeSdkWithMultipleReponses([
            // Certiticate download
            new Response(
                status: 200,
                body: base64_encode(self::TEST_CERT)
            ),
            // Certificate CA bundle download
            new Response(
                status: 200,
                body: base64_encode(self::TEST_INTERMEDIATE . "\n" . self::TEST_ROOT)
            ),
        ]);
        $this->instance(RealtimeRegister::class, $rtrClient);

        $dispatcherMock = self::createMock(Dispatcher::class);
        $dispatcherMock->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(
                    fn ($argument) =>
                        $argument instanceof InstallCertificate
                        && $argument->getSslDeployment()->certificate_id === 77_665_544
                )
            );
        $this->instance(Dispatcher::class, $dispatcherMock);

        $downloadJob->handle(
            self::resolve(CertificateDownloader::class),
            self::resolve(Dispatcher::class),
            self::resolve(MailerInterface::class)
        );

        $certificateManager = self::resolve(CertificateManager::class);
        self::assertSame($certificateManager->getMainCertificate('sandwave.io'), trim(self::TEST_CERT));
        self::assertSame($certificateManager->getIntermediateCertificate('sandwave.io'), trim(self::TEST_INTERMEDIATE));
        self::assertSame($certificateManager->getRootCertificate('sandwave.io'), trim(self::TEST_ROOT));

        $this->sslDeployment->refresh();
        self::assertSame(TechnicalStatus::OK->value, $this->sslDeployment->subscription->technical_status);
    }
}
