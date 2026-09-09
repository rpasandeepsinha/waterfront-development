<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Actions;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ssl\Actions\UpdateSslRequestStatusAction;
use Waterfront\Domain\Ssl\Exceptions\SslRequestStatusException;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(UpdateSslRequestStatusAction::class)]
class UpdateSslRequestStatusActionTest extends IntegrationTestCase
{
    private const string DOMAIN = 'testqa137check.nl';

    private const int REQUEST_ID = 2323533016;

    private const int CERTIFICATE_ID = 2323724604;

    private const string CSR = "-----BEGIN CERTIFICATE REQUEST-----\nMIICvDCCAaQCAQAwdzELMAkGA1UEBhMCTkwxEzARBgNVBAgMCk92ZXJpanNzZWwx\nDzANBgNVBAcMBlp3b2xsZTEUMBIGA1UECgwLWW91ckhvc3RpbmcxEDAOBgNVBAsM\nB1N1cHBvcnQxGjAYBgNVBAMMEXRlc3RxYTEzN2NoZWNrLm5sMIIBIjANBgkqhkiG\n9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxne15LcrVXC+xfWOBI8zeDTpqQvTeZvr4UV3\nYZVs+0CQgt6Y3Fjfr3g2/Yl+npG6qG5JM/IcCDkX7WXHlscs+dtiNt0Gz4zIzKdb\n2ZY6BpgADVerRckS98SAPwqGie3Bb+ZGXxt46TxQ9RZqZxgqsJbHZLmAHD9eyjvm\n2rycfaYFVNvryroqx5IYw6bWE36ZCMO7zDZ61xtVOILcGKxtvorK7dYvjrPwtX1v\nY6A89blQjyjEMq0Qdwn1nq7y5RT+Z6gCETR0ypx7V9A+Lb2vaQJq+54fkb1fxdY0\ntCYMft1EfWn06UqsE32GnGDpGprcAgDgSJow+wEMV0+CN7ahLwIDAQABoAAwDQYJ\nKoZIhvcNAQELBQADggEBACTV5rCblSWwO3VKbrDN+T5H6PTaElWyyCmgcG6s8May\n+XjLnLSTsbSgNdtDwapMDJ70bgaBHfZ847Xacw0l0w47u2SxAAR+5lV43FTW6XQ5\nDyVYCCAS9Co45NuqoiNpLYTYZTiuqHglO5+H3XhF3kC8p0eE4mnQ5d6rAUDcnH6Q\nQTgMzMUGntLSsn6Yd0XCmyIYfvHmvS4vvkjuLh9WlRZWvmLq+5ST/GV+8zYHiync\nh+mqwjfDY3Thn/N8JwlTivhiYdX1+talzNiZ7m1yiLdmy/ovP44cbjMkNHHGTTia\nXLf9prwRzeOVUyMCDCfWcKb6mPWoCevWnsdaRpScxT8=\n-----END CERTIFICATE REQUEST-----";

    private Subscription $subscription;

    private SslDeployment $sslDeployment;

    private CsrManager&Stub $csrManager;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = CustomerFactory::new()->createOne();
        $product = ProductFactory::new()->sslSingleDomain()->createOne();

        $dispatcherMock = self::createStub(Dispatcher::class);
        $this->app->bind(Dispatcher::class, fn (): Dispatcher => $dispatcherMock);

        ProductSpecFactory::new()->createOne([
            'product_id' => $product->id,
            'name' => 'ssl.product_id',
            'value' => 'ssl_sectigo',
        ]);

        $this->subscription = SubscriptionFactory::new()
            ->for($customer)
            ->for($product)
            ->createOne([
                'domain' => self::DOMAIN,
                'technical_status' => TechnicalStatus::OK->value,
            ]);

        $this->sslDeployment = SslDeploymentFactory::new()
            ->for(ProviderFactory::new()->sslRtr(), 'provider')
            ->createOne([
                'subscription_uuid' => $this->subscription->uuid,
                'certificate_id' => null,
                'request_id' => self::REQUEST_ID,
            ]);

        $this->csrManager = self::createStub(CsrManager::class);
    }

    #[Test]
    public function executeUpdatesSslDeploymentWhenProcessIsCompletedAndCsrMatches(): void
    {
        $this->subscription->technical_status = TechnicalStatus::FAILED->value;
        $this->subscription->save();

        $this->sslDeployment->certificate_id = 1234;
        $this->sslDeployment->save();

        $this->csrManager->method('getRawCsr')->willReturn(self::CSR);

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->makeProcessResponse('COMPLETED')),
            new Response(200, [], $this->makeCertificatesResponse(self::CSR)),
        ]);

        $this->buildAction($rtrSdk)->execute($this->sslDeployment);

        $this->sslDeployment->refresh();
        $this->subscription->refresh();
        self::assertSame(self::CERTIFICATE_ID, $this->sslDeployment->certificate_id);
        self::assertSame(TechnicalStatus::OK->value, $this->subscription->technical_status);
        self::assertNotNull($this->sslDeployment->last_result_received);
        self::assertStringContainsString('Certificate ready to be downloaded.', (string) $this->sslDeployment->last_result);
    }

    #[Test]
    public function executeThrowsWhenSubscriptionHasNoDomain(): void
    {
        $this->subscription->domain = null;
        $this->subscription->save();

        self::expectException(SslRequestStatusException::class);
        self::expectExceptionMessageIs('No domain for subscription');

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([]))->execute($this->sslDeployment);
    }

    #[Test]
    public function executeThrowsWhenProviderIsNotRealtimeRegister(): void
    {
        $this->sslDeployment->provider()->associate(ProviderFactory::new()->sslOpenProvider()->createOne());
        $this->sslDeployment->save();

        self::expectException(SslRequestStatusException::class);
        self::expectExceptionMessageIs('SSL deployment is not RTR');

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([]))->execute($this->sslDeployment);
    }

    #[Test]
    public function executeThrowsWhenBothCertificateIdAndRequestIdAreNull(): void
    {
        $this->sslDeployment->certificate_id = null;
        $this->sslDeployment->request_id = null;
        $this->sslDeployment->save();

        self::expectException(SslRequestStatusException::class);

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([]))->execute($this->sslDeployment);
    }

    #[Test]
    public function executeThrowsWhenProcessStatusIsNotCompleted(): void
    {
        self::expectException(SslRequestStatusException::class);

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->makeProcessResponse('NEW')),
        ]))->execute($this->sslDeployment);
    }

    #[Test]
    public function executeThrowsWhenCertificateIsNotFoundInRtr(): void
    {
        $emptyCertificatesResponse = (string) json_encode([
            'entities' => [],
            'pagination' => ['total' => 0, 'offset' => 0, 'limit' => 1],
        ]);

        self::expectException(SslRequestStatusException::class);

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->makeProcessResponse('COMPLETED')),
            new Response(200, [], $emptyCertificatesResponse),
        ]))->execute($this->sslDeployment);
    }

    #[Test]
    public function executeThrowsWhenStoredCsrDoesNotMatchRtrCertificateCsr(): void
    {
        $this->csrManager->method('getRawCsr')->willReturn('different-stored-csr');

        $this->sslDeployment->certificate_id = 1234;
        $this->sslDeployment->save();

        self::expectException(SslRequestStatusException::class);
        self::expectExceptionMessageIs('Stored CSR does not match');

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $this->makeProcessResponse('COMPLETED')),
            new Response(200, [], $this->makeCertificatesResponse(self::CSR)),
        ]))->execute($this->sslDeployment);
    }

    #[Test]
    public function executeThrowsWhenRtrClientThrowsException(): void
    {
        self::expectException(SslRequestStatusException::class);

        $this->buildAction(MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(400, [], (string) json_encode(['message' => 'bad request'])),
        ]))->execute($this->sslDeployment);
    }

    private function buildAction(RealtimeRegister $rtrSdk): UpdateSslRequestStatusAction
    {
        return new UpdateSslRequestStatusAction(
            rtrClient: $rtrSdk,
            csrManager: $this->csrManager,
        );
    }

    private function makeProcessResponse(string $status): string
    {
        return (string) json_encode([
            'id' => self::REQUEST_ID,
            'user' => 'user',
            'customer' => 'customer',
            'type' => 'SSL_CERTIFICATE_REQUEST',
            'action' => 'SSL_CERTIFICATE_REQUEST',
            'command' => [],
            'status' => $status,
            'createdDate' => CarbonImmutable::now()->toIso8601String(),
            'updatedDate' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    private function makeCertificatesResponse(string $csr): string
    {
        return (string) json_encode([
            'entities' => [
                [
                    'id' => self::CERTIFICATE_ID,
                    'customer' => 'YHSW',
                    'validationType' => 'DOMAIN_VALIDATION',
                    'certificateType' => 'SINGLE_DOMAIN',
                    'product' => 'ssl_sectigo',
                    'csr' => $csr,
                    'domainName' => self::DOMAIN,
                    'providerId' => '2856501607',
                    'process' => self::REQUEST_ID,
                    'expiryDate' => '2027-03-05T23:59:59Z',
                    'startDate' => '2026-02-26T00:00:00Z',
                    'status' => 'ACTIVE',
                    'publicKeyAlgorithm' => 'RSA',
                    'publicKeySize' => 2048,
                ],
            ],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 1],
        ]);
    }
}
