<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Services;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use RuntimeException;
use Spatie\SslCertificate\Downloader;
use Spatie\SslCertificate\SslCertificate;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Domain\Ssl\Services\CertificateManager;
use Waterfront\Domain\Ssl\Services\CertificateService;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Support\Enums\LoggingContextKeys;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(CertificateService::class)]
class CertificateServiceTest extends IntegrationTestCase
{
    private const string DOMAIN_NAME = 'yourhosting.nl';

    private const string EXPIRE_DATE_FROM_JSON = '2024-08-16T13:37:20Z';

    private const int EXPIRE_DATE_TIMESTAMP = 1723808240;

    private const int RTR_PROCESS_ID = 2323533016;

    private const string RTR_LIST_CERT_EXPIRE_DATE = '2027-03-05T23:59:59Z';

    private const int RTR_LIST_CERTIFICATE_ID = 2323724604;

    private const string RTR_LIST_CERT_DOMAIN = 'testqa137check.nl';

    private CertificateService $service;

    private Downloader&MockInterface $certificateDownloader;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $json = (string) file_get_contents(__DIR__ . '/../data/get_certificate_by_id_response.json');

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $json),
        ], static function (RequestInterface $request) use (&$rtrRequests): void {
            $rtrRequests[] = $request;
        });

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $this->service = new CertificateService(
            csrManager: self::createMock(CsrManager::class),
            certificateManager: self::createMock(CertificateManager::class),
            realtimeRegister: self::resolve(RealtimeRegister::class),
            certificateDownloader: $this->certificateDownloader = self::mock(Downloader::class),
            logger: $this->logger = self::createMock(LoggerInterface::class),
            sslDeploymentRepository: self::createStub(DeploymentRepository::class),
        );
    }

    #[Test]
    public function updateSslExpireDateForRtrSslDeployment(): void
    {
        $expireDate = CarbonImmutable::createFromTimeString(self::EXPIRE_DATE_FROM_JSON);
        $sslDeployment = SslDeploymentFactory::new()->for(
            ProviderFactory::new()->sslRtr()->createOne(),
        )->createOne(
            [
                'certificate_id' => 1337,
                'expire_date' => null,
            ],
        );

        $this->service->updateSslExpireDate($sslDeployment);
        $sslDeployment->refresh();

        self::assertNotNull($sslDeployment->expire_date);
        self::assertSame(
            $expireDate->format(DateTimeFormat::DATE),
            $sslDeployment->expire_date->format(DateTimeFormat::DATE),
        );
    }

    #[Test]
    public function updateSslExpireDateForRtrSslDeploymentWithoutCertificateId(): void
    {
        $expireDate = CarbonImmutable::createFromTimestampUTC(self::EXPIRE_DATE_TIMESTAMP);
        $sslDeployment = SslDeploymentFactory::new()->for(
            SubscriptionFactory::new()
                ->withCustomer()
                ->for(
                    ProductFactory::new()->sslSingleDomain()->createOne(),
                )
                ->createOne(['domain' => self::DOMAIN_NAME]),
        )->for(
            ProviderFactory::new()->sslRtr(),
        )->createOne(
            [
                'certificate_id' => null,
                'expire_date' => null,
                'request_id' => null,
            ],
        );

        $this->certificateDownloader
            ->expects('downloadCertificateFromUrl')
            ->once()
            ->with(self::DOMAIN_NAME)
            ->andReturn(
                new SslCertificate([
                    'validTo_time_t' => self::EXPIRE_DATE_TIMESTAMP,
                ]),
            );

        $this->service->updateSslExpireDate($sslDeployment);
        $sslDeployment->refresh();

        self::assertNotNull($sslDeployment->expire_date);
        self::assertSame(
            $expireDate->format(DateTimeFormat::DATE),
            $sslDeployment->expire_date->format(DateTimeFormat::DATE),
        );
    }

    #[Test]
    public function updateSslExpireDateForPlaceholderSslDeployment(): void
    {
        $expireDate = CarbonImmutable::createFromTimestampUTC(self::EXPIRE_DATE_TIMESTAMP);
        $sslDeployment = SslDeploymentFactory::new()->for(
            SubscriptionFactory::new()
                ->withCustomer()
                ->for(
                    ProductFactory::new()->sslSingleDomain()->createOne(),
                )
                ->createOne(['domain' => self::DOMAIN_NAME]),
        )->for(
            ProviderFactory::new()->sslPlaceholder(),
        )->createOne(
            [
                'certificate_id' => null,
                'expire_date' => null,
            ],
        );

        $this->certificateDownloader
            ->expects('downloadCertificateFromUrl')
            ->once()
            ->with(self::DOMAIN_NAME)
            ->andReturn(
                new SslCertificate([
                    'validTo_time_t' => self::EXPIRE_DATE_TIMESTAMP,
                ]),
            );

        $this->service->updateSslExpireDate($sslDeployment);
        $sslDeployment->refresh();

        self::assertNotNull($sslDeployment->expire_date);
        self::assertSame(
            $expireDate->format(DateTimeFormat::DATE),
            $sslDeployment->expire_date->format(DateTimeFormat::DATE),
        );
    }

    #[Test]
    public function updateSslExpireDateForPlaceholderSslDeploymentWithoutDomain(): void
    {
        $sslDeployment = SslDeploymentFactory::new()->for(
            SubscriptionFactory::new()
                ->withCustomer()
                ->for(
                    ProductFactory::new()->sslSingleDomain()->createOne(),
                )
                ->createOne(['domain' => null]),
        )->for(
            ProviderFactory::new()->sslPlaceholder(),
        )->createOne(
            [
                'certificate_id' => null,
                'expire_date' => null,
            ],
        );

        $this->certificateDownloader->expects('downloadCertificateFromUrl')->never();

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to fetch ssl expire date because domain is null.',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SSL,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $sslDeployment->provider->slug,
                    LoggingContextKeys::PROVISIONING_ID => $sslDeployment->id,
                ],
            );

        self::expectException(RuntimeException::class);
        $this->service->updateSslExpireDate($sslDeployment);
        $sslDeployment->refresh();

        self::assertNull($sslDeployment->expire_date);
    }

    #[Test]
    public function updateSslExpireDateForRtrSslDeploymentWithoutCertificateIdBackfillsFromRtr(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/../data/list_certificates_response.json');

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $json),
        ]);

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $deploymentRepository = self::createMock(DeploymentRepository::class);
        $deploymentRepository
            ->expects(self::once())
            ->method('backfillCertificateId')
            ->with(
                sslDeploymentId: self::isInt(),
                certificateId: self::equalTo(self::RTR_LIST_CERTIFICATE_ID),
            )
            ->willReturn(true);

        $this->service = new CertificateService(
            csrManager: self::createMock(CsrManager::class),
            certificateManager: self::createMock(CertificateManager::class),
            realtimeRegister: self::resolve(RealtimeRegister::class),
            certificateDownloader: $this->certificateDownloader,
            logger: $this->logger,
            sslDeploymentRepository: $deploymentRepository,
        );

        $expireDate = CarbonImmutable::createFromTimeString(self::RTR_LIST_CERT_EXPIRE_DATE);

        $sslDeployment = SslDeploymentFactory::new()->for(
            SubscriptionFactory::new()
                ->withCustomer()
                ->for(ProductFactory::new()->sslSingleDomain()->createOne())
                ->createOne(['domain' => self::RTR_LIST_CERT_DOMAIN]),
        )->for(ProviderFactory::new()->sslRtr())->createOne([
            'certificate_id' => null,
            'request_id' => self::RTR_PROCESS_ID,
            'expire_date' => null,
        ]);

        $this->certificateDownloader->expects('downloadCertificateFromUrl')->never();

        $this->service->updateSslExpireDate($sslDeployment);
        $sslDeployment->refresh();

        self::assertNotNull($sslDeployment->expire_date);
        self::assertSame(
            $expireDate->format(DateTimeFormat::DATE),
            $sslDeployment->expire_date->format(DateTimeFormat::DATE),
        );
    }

    #[Test]
    public function updateSslExpireDateForRtrSslDeploymentBackfillDomainMismatchFallsBackToHost(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/../data/list_certificates_response.json');

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $json),
        ]);

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $deploymentRepository = self::createMock(DeploymentRepository::class);
        $deploymentRepository->expects(self::never())->method('backfillCertificateId');

        $this->logger = self::createMock(LoggerInterface::class);
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::equalTo('RTR certificate_id backfill: domain mismatch'),
                self::callback(function (array $context): bool {
                    self::assertSame(ProvisionType::SSL, $context[LoggingContextKeys::PROVISIONING_TYPE]);
                    self::assertArrayHasKey(LoggingContextKeys::DOMAIN_NAME, $context);
                    self::assertArrayHasKey(LoggingContextKeys::META, $context);

                    $meta = $context[LoggingContextKeys::META];

                    self::assertArrayHasKey('request_id', $meta);
                    self::assertSame(self::RTR_PROCESS_ID, $meta['request_id']);

                    self::assertArrayHasKey('certificate_id', $meta);
                    self::assertSame(self::RTR_LIST_CERTIFICATE_ID, $meta['certificate_id']);

                    self::assertArrayHasKey('rtr_domain', $meta);
                    self::assertSame(self::RTR_LIST_CERT_DOMAIN, $meta['rtr_domain']);

                    return true;
                }),
            );

        $this->service = new CertificateService(
            csrManager: self::createMock(CsrManager::class),
            certificateManager: self::createMock(CertificateManager::class),
            realtimeRegister: self::resolve(RealtimeRegister::class),
            certificateDownloader: $this->certificateDownloader,
            logger: $this->logger,
            sslDeploymentRepository: $deploymentRepository,
        );

        $domain = 'mismatch.example';
        $expireDate = CarbonImmutable::createFromTimestampUTC(self::EXPIRE_DATE_TIMESTAMP);

        $sslDeployment = SslDeploymentFactory::new()->for(
            SubscriptionFactory::new()
                ->withCustomer()
                ->for(ProductFactory::new()->sslSingleDomain()->createOne())
                ->createOne(['domain' => $domain]),
        )->for(ProviderFactory::new()->sslRtr())->createOne([
            'certificate_id' => null,
            'request_id' => self::RTR_PROCESS_ID,
            'expire_date' => null,
        ]);

        $this->certificateDownloader
            ->expects('downloadCertificateFromUrl')
            ->once()
            ->with($domain)
            ->andReturn(new SslCertificate([
                'validTo_time_t' => self::EXPIRE_DATE_TIMESTAMP,
            ]));

        $this->service->updateSslExpireDate($sslDeployment);
        $sslDeployment->refresh();

        self::assertNotNull($sslDeployment->expire_date);
        self::assertSame(
            $expireDate->format(DateTimeFormat::DATE),
            $sslDeployment->expire_date->format(DateTimeFormat::DATE),
        );
    }
}
