<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Services;

use GuzzleHttp\Psr7\Response;
use Illuminate\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\Domain\Enum\StatusEnum;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerAddressFactory;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Services\CsrManager;
use Waterfront\Domain\Ssl\Services\CustomerSharedSslService;
use Waterfront\Infra\RtrClient\Services\Ssl\CertificateRequester;

#[CoversClass(CustomerSharedSslService::class)]
class CustomerSharedSslServiceIntegrationTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $sslProduct;

    private CsrManager $csrManager;

    private Provider $sslProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        new CustomerAddressFactory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $sslGroup = new ProductGroupFactory()->createOne([
            'slug' => 'ssl',
            'name' => 'SSL',
        ]);

        $this->sslProduct = new ProductFactory()->createOne([
            'product_group_id' => $sslGroup->id,
            'name' => 'Single domain',
            'slug' => 'single-domain',
        ]);

        new ProductSpecFactory()->for($this->sslProduct)->createOne([
            'name' => 'ssl.product_id',
            'value' => 'ssl_sectigo',
        ]);

        $this->sslProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->csrManager = self::resolve(CsrManager::class);
    }

    #[Test]
    public function retrieve(): void
    {
        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $this->sslProduct->uuid,
            'domain' => 'domain.com',
        ]);

        $subscription
            ->sslDeployment()
            ->create([
                'certificate_id' => 123,
                'provider_id' => $this->sslProvider->id,
            ]);

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(
                    201,
                    [],
                    (string) json_encode([
                        'id' => 12,
                        'process' => 1,
                        'status' => StatusEnum::STATUS_ACTIVE,
                        'certificateType' => 'SINGLE_DOMAIN',
                        'publicKeyAlgorithm' => 'RSA',
                        'organization' => 'Sandwave',
                        'department' => 'Sandwave department',
                        'address' => 'Street 1',
                        'domain' => 'test',
                        'product' => 'test',
                        'domainName' => 'test',
                        'validationType' => 'DOMAIN_VALIDATION',
                        'startDate' => 'now',
                        'expiryDate' => 'tomorrow',
                        'approver' => 'admin@local.testing',
                        'postalCode' => '1234XD',
                        'city' => 'Vlissingen',
                        'coc' => '1234567890',
                        'firstName' => 'Eerste',
                        'lastName' => 'Laatste',
                        'voice' => '+31.401234567',
                        'csr' => '',
                    ]),
                ),
            ],
        );

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $service = self::resolve(CustomerSharedSslService::class);
        self::assertInstanceOf(SslDeployment::class, $subscription->sslDeployment);

        $result = $service->retrieve($subscription->sslDeployment);

        self::assertSame(Result::STATUS_OK, $result->getStatus());
        self::assertSame(StatusEnum::STATUS_ACTIVE, $result->getCertificateStatus());
    }

    /**
     * Tests renewal by creating the SSL certificate again. (instead of renewing existing).
     */
    #[Test]
    public function renewWithCreate(): void
    {
        $certificateId = 1337;
        $dispatcherMock = self::createMock(Dispatcher::class);
        $dispatcherMock
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                fn (UpdateSslExpireDate $event) => $event->sslDeployment->certificate_id === $certificateId,
            ));
        $this->app->bind(Dispatcher::class, fn (): Dispatcher => $dispatcherMock);

        $this->app->bind(DnsService::class, fn (): DnsService => self::createStub(DnsService::class));

        $subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $this->sslProduct->uuid,
            'domain' => 'domain.com',
        ]);

        $subscription
            ->sslDeployment()
            ->create([
                'provider_id' => $this->sslProvider->id,
            ]);

        $domain = $subscription->domain;

        self::assertNotNull($domain);
        self::assertInstanceOf(SslDeployment::class, $subscription->sslDeployment);

        $result = new Result();
        $result->setIsCertificateActive(false);

        $certificateRequester = self::createMock(CertificateRequester::class);
        $certificateRequester->expects(self::once())->method('retrieve')->willReturn($result);
        $this->app->bind(CertificateRequester::class, fn () => $certificateRequester);

        $service = self::resolve(CustomerSharedSslService::class);
        $firstResult = $service->create(12, $subscription->sslDeployment, null);

        $firstCsr = $this->csrManager->getRawCsr($domain);

        // After a `create()` step for a certificate a request_id would be stored on the sslDeployment.
        // We mimic it here after the `create()` step, so that we actually test against this situation
        // in the `renew()` step.
        $subscription->sslDeployment->certificate_id = $certificateId;
        $subscription->sslDeployment->request_id = 123455;
        $subscription->sslDeployment->save();

        $secondResult = $service->renew($subscription->sslDeployment);
        $secondCsr = $this->csrManager->getRawCsr($domain);

        self::assertSame(Result::STATUS_WAITING, $firstResult->getStatus());
        self::assertSame(Result::STATUS_WAITING, $secondResult->getStatus());
        self::assertNotSame($firstCsr, $secondCsr);
    }
}
