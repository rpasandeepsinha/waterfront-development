<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Services;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ssl\Clients\RemoteSslServiceClient;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Pipelines\CreateCertificate\GenerateCsrStep;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;

#[CoversClass(CustomerSharedSslService::class)]
class CustomerSharedSslServiceUnitTest extends IntegrationTestCase
{
    private SslDeployment $sslDeployment;

    public function setUp(): void
    {
        parent::setUp();

        $productGroup = new ProductGroupFactory()->hosting()->createOne();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $sslProvider = ProviderFactory::new()->domainRtr()->createOne();
        $subscription = new SubscriptionFactory()
            ->for($product)
            ->for(new CustomerFactory()->withAddress())
            ->createOne([
                'domain' => 'testdomain.nl',
                'contract_period' => 12,
            ]);

        $this->sslDeployment = new SslDeploymentFactory()->createOne([
            'provider_id' => $sslProvider->id,
            'subscription_uuid' => $subscription->uuid,
        ]);
    }

    #[Test]
    public function renewWithoutExistingCsr(): void
    {
        $remoteSslServiceClient = self::createMock(RemoteSslServiceClient::class);

        $remoteSslServiceClient->expects(self::once())->method('csrExistsForDomain')->willReturn(false);
        $remoteSslServiceClient->expects(self::once())->method('retrieve')->willReturn(new Result());
        $remoteSslServiceClient->expects(self::once())->method('create')->willReturn(new Result());

        $sslService = new CustomerSharedSslService(
            $remoteSslServiceClient,
            self::createStub(CsrManager::class),
            self::createStub(LoggerInterface::class),
            self::createStub(GenerateCsrStep::class),
        );

        $sslService->renew($this->sslDeployment);
    }

    #[Test]
    public function renewWithExistingCsr(): void
    {
        $remoteSslServiceClient = self::createMock(RemoteSslServiceClient::class);
        $remoteSslServiceClient->expects(self::once())->method('csrExistsForDomain')->willReturn(true);
        $remoteSslServiceClient
            ->expects(self::once())
            ->method('retrieve')
            ->willReturn(
                Result::create([
                    'isCertificateActive' => true,
                ]),
            );
        $remoteSslServiceClient->expects(self::once())->method('renew')->willReturn(new Result());

        $sslService = new CustomerSharedSslService(
            $remoteSslServiceClient,
            self::createStub(CsrManager::class),
            self::createStub(LoggerInterface::class),
            self::createStub(GenerateCsrStep::class),
        );

        $sslService->renew($this->sslDeployment);
    }

    #[Test]
    public function renewWithoutOriginalCertificateIdShouldOrderNewCertificate(): void
    {
        $remoteSslServiceClient = self::createMock(RemoteSslServiceClient::class);
        $remoteSslServiceClient->expects(self::once())->method('csrExistsForDomain')->willReturn(true);

        $remoteSslServiceClient
            ->expects(self::once())
            ->method('retrieve')
            ->willThrowException(
                new LogicException('cannot retrieve certificate without id'),
            );

        $remoteSslServiceClient->expects(self::never())->method('renew');

        $remoteSslServiceClient
            ->expects(self::once())
            ->method('create')
            ->with(
                12,
                self::callback(
                    fn (SslDeployment $sslDeployment) => (
                        $sslDeployment->id === $this->sslDeployment->id
                        && $sslDeployment->request_id === null
                    ),
                ),
                null,
            );

        $sslService = new CustomerSharedSslService(
            $remoteSslServiceClient,
            self::createStub(CsrManager::class),
            self::createStub(LoggerInterface::class),
            self::createStub(GenerateCsrStep::class),
        );

        $sslService->renew($this->sslDeployment);
    }

    #[Test]
    public function reissueWithNewCsr(): void
    {
        $remoteSslServiceClient = self::createMock(RemoteSslServiceClient::class);
        $remoteSslServiceClient->expects(self::once())->method('csrExistsForDomain')->willReturn(true);
        $remoteSslServiceClient
            ->expects(self::once())
            ->method('retrieve')
            ->willReturn(
                Result::create([
                    'isCertificateActive' => true,
                ]),
            );
        $remoteSslServiceClient->expects(self::once())->method('reissue')->willReturn(new Result());

        $domain = $this->sslDeployment->subscription->domain;
        $generateCsrStep = self::createMock(GenerateCsrStep::class);
        $generateCsrStep
            ->expects(self::once())
            ->method('execute')
            ->with($this->sslDeployment->subscription->customer->load('address')->toArray(), $domain)
            ->willReturn('-----BEGIN CERTIFICATE REQUEST-----
MIIC5DCCAcwCAQAwgYExCzAJBgNVBAYTAlVTMQ8wDQYDVQQIDAZUZXhhczEQMA4G
A1UEBwwHSG91c3RvbjEZMBcGA1UECgwQRmFrZSBDb21wYW55IEx0ZDEYMBYGA1UE
CwwPSVQgRGVwYXJ0bWVudDEZMBcGA1UEAwwQd3d3LmV4YW1wbGUuY29tMIIBIjAN
BgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvFakeCSRExampleData1234567890
abcdefGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxYZFakeCSR
MoreFakeBase64DataForTestingOnlyDoNotUseInProductionEnvironment1234567
890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0987654321==
-----END CERTIFICATE REQUEST-----');

        $remoteSslServiceClient->expects(self::once())->method('reissue')->willReturn(new Result());

        $sslService = new CustomerSharedSslService(
            $remoteSslServiceClient,
            self::createStub(CsrManager::class),
            self::createStub(LoggerInterface::class),
            $generateCsrStep,
        );

        $sslService->reissue($this->sslDeployment);
    }

    #[Test]
    public function reissueWithoutOriginalCertificateIdShouldOrderNewCertificate(): void
    {
        $remoteSslServiceClient = self::createMock(RemoteSslServiceClient::class);
        $remoteSslServiceClient->expects(self::once())->method('csrExistsForDomain')->willReturn(true);

        $remoteSslServiceClient
            ->expects(self::once())
            ->method('retrieve')
            ->willThrowException(
                new LogicException('cannot retrieve certificate without id'),
            );

        $remoteSslServiceClient->expects(self::never())->method('reissue');

        $remoteSslServiceClient
            ->expects(self::once())
            ->method('create')
            ->with(
                12,
                self::callback(
                    fn (SslDeployment $sslDeployment) => (
                        $sslDeployment->id === $this->sslDeployment->id
                        && $sslDeployment->request_id === null
                    ),
                ),
                null,
            );

        $sslService = new CustomerSharedSslService(
            $remoteSslServiceClient,
            self::createStub(CsrManager::class),
            self::createStub(LoggerInterface::class),
            self::resolve(GenerateCsrStep::class),
        );

        $sslService->reissue($this->sslDeployment);
    }
}
