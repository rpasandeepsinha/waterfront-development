<?php

declare(strict_types=1);

namespace Tests\Domain\Ssl\Job;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SslDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Services\Ssl\SslDnsService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Jobs\UpdateDns;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;

#[CoversClass(UpdateDns::class)]
#[AllowMockObjectsWithoutExpectations]
class UpdateDnsTest extends integrationTestCase
{
    private const string DOMAIN = 'ssl.test';
    private const string SUBDOMAIN_DOMAIN = 'subdomain.ssl.test';

    private Result $sslRetrieveResult;

    private Provider $sslProvider;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sslProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::SSL,
            'slug' => ProviderSlug::REALTIME_REGISTER,
        ]);
        $this->customer = CustomerFactory::new()->createOne();
        $productGroup = ProductGroupFactory::new()->ssl()->createOne();
        $this->product = ProductFactory::new()->for($productGroup)->createOne([
            'slug' => 'ssl_single_domain',
        ]);

        $this->sslRetrieveResult = new Result();
        $this->sslRetrieveResult->setIsCertificateActive(true);
        $this->sslRetrieveResult->setStatus(Result::STATUS_WAITING);
        $this->sslRetrieveResult->setCertificateStatus(DomainStatus::REQUESTED->value);
        $this->sslRetrieveResult->setRequestId(69);
        $this->sslRetrieveResult->setCertificateId(777);
    }

    #[DataProvider('updateDnsProvider')]
    #[Test]
    public function updateDns(string $domain, string $expectedDomain): void
    {
        $this->sslRetrieveResult->setDnsRecord("_990def0862163bf175976daceb192883.$domain");

        $domainSslSubscription = SubscriptionFactory::new()
            ->for($this->product)
            ->for($this->customer)
            ->forDomain($domain)
            ->createOne();

        SslDeploymentFactory::new()->for($this->sslProvider)->for($domainSslSubscription)->createOne();

        $retrieveSslClient = self::createStub(OpenproviderClient::class);
        $retrieveSslClient->method('retrieveSsl')->willReturn($this->sslRetrieveResult);

        $openProviderClientFactory = self::createMock(OpenproviderClientFactory::class);
        $openProviderClientFactory->expects(self::once())->method('create')->willReturn($retrieveSslClient);

        $sslDnsService = self::createMock(SslDnsService::class);
        $sslDnsService->expects(self::once())->method('updateDns')->with($this->sslRetrieveResult, $expectedDomain);

        $job = new UpdateDns(
            domain: $domain,
            recursive: true,
        );

        $job->handle(
            sslDnsService: $sslDnsService,
            openproviderClientFactory: $openProviderClientFactory,
            rules: self::resolve(PublicSuffixList::class),
        );
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    public static function updateDnsProvider(): iterable
    {
        yield 'Domain without subdomain' => [
            'domain' => self::DOMAIN,
            'expectedDomain' => self::DOMAIN,
        ];

        yield 'Domain with subdomain' => [
            'domain' => self::SUBDOMAIN_DOMAIN,
            'expectedDomain' => self::DOMAIN,
        ];
    }
}
