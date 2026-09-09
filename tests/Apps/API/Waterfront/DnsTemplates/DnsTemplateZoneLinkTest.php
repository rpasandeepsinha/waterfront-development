<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DnsTemplates;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\ValueObjects\Fqdn;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversNothing]
#[AllowMockObjectsWithoutExpectations]
class DnsTemplateZoneLinkTest extends IntegrationTestCase
{
    private DnsCustomerTemplate $dnsCustomerTemplate;

    private Customer $customer;

    private Subscription $subscription;

    private Subscription $subscription2;

    private DomainDeployment $domainDeployment;

    private Provider $provider;

    private Product $dnsProduct;

    private Product $product;

    private DnsService&MockObject $mockDnsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->provider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::PLACEHOLDER, 'enabled' => true, 'default' => false]);

        //PHPStan does not seem to like create, using new as alternative.
        $this->dnsCustomerTemplate = new DnsCustomerTemplate([
            'name' => 'example',
            'customer_id' => $this->customer->id,
        ]);
        $this->dnsCustomerTemplate->save();

        $this->product = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();

        $this->dnsProduct = new ProductFactory()->for(new ProductGroupFactory()->dns())->createOne();

        $this->subscription = new SubscriptionFactory()
            ->forDomain('domain.com')
            ->for($this->product)
            ->for($this->customer)
            ->createOne();

        new SubscriptionFactory()
            ->forDomain('domain.com')
            ->for($this->customer)
            ->for($this->dnsProduct)
            ->createOne();

        $this->domainDeployment = new DomainDeploymentFactory()
            ->for($this->subscription)
            ->for($this->dnsCustomerTemplate, 'template')
            ->for($this->provider, 'provider')
            ->createOne();

        $this->subscription2 = new SubscriptionFactory()
            ->for($this->customer)
            ->forDomain('domain2.com')
            ->for($this->product)
            ->createOne();

        new DomainDeploymentFactory()
            ->for($this->subscription2)
            ->for($this->provider, 'provider')
            ->createOne();

        $this->mockDnsService = self::createMock(DnsService::class);
        $this->app->bind(DnsService::class, fn (): DnsService => $this->mockDnsService);
    }

    #[Test]
    public function linkToDomain(): void
    {
        $this->mockDnsService->expects(self::exactly(2))
            ->method('applyTemplate');

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.dns.templates.link', ['template' => $this->dnsCustomerTemplate->id]),
            [
                'domains' => [
                    ['domain' => 'domain.com'],
                    ['domain' => 'domain2.com'],
                ],
            ]
        )
            ->assertOk()
            ->assertExactJson(['message' => self::resolve(TranslatorInterface::class)->translate('dns-template.link-domain-success')]);
    }

    #[Test]
    public function linkToDomainValidationFailed(): void
    {
        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.dns.templates.link', ['template' => $this->dnsCustomerTemplate->id]),
            [
                'domains' => [
                    ['domain' => 'domainfail.com'],
                    ['domain' => 'domain2.com'],
                ],
            ]
        )
            ->assertUnprocessable();
    }

    #[Test]
    public function linkToDomainPdnsQueryFailed(): void
    {
        $this->mockDnsService->method('getDnsZone')->willThrowException(new DnsZoneNotFoundException());

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.dns.templates.link', ['template' => $this->dnsCustomerTemplate->id]),
            [
                'domains' => [
                    ['domain' => $this->subscription->domain],
                    ['domain' => $this->subscription2->domain],
                ],
            ]
        )
            ->assertNotFound()
            ->assertJson([
                'message' => 'Zone domain.com not found',
            ]);
    }

    #[Test]
    public function linkableDomains(): void
    {
        $this->domainDeployment->update(['template_id' => null]);
        $this->domainDeployment->refresh();

        self::assertNotNull($this->subscription2->domain);

        new SubscriptionFactory()->for($this->customer)->forDomain($this->subscription2->domain)->for($this->dnsProduct)->createOne();

        $subscription3 = new SubscriptionFactory()->forDomain('zzzz.com')->for($this->product)->for($this->customer)->createOne();

        new DomainDeploymentFactory()
            ->for($subscription3)
            ->for($this->provider, 'provider')
            ->createOne();

        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.dns.templates.available_domains', ['template' => $this->dnsCustomerTemplate->id])
        )->assertOk()->assertJsonFragment([
            'domain' => $this->subscription->domain,
            'available' => true,
            'linked' => false,
        ])
        ->assertJsonFragment([
            'domain' => $subscription3->domain,
            'available' => false,
            'linked' => false,
        ])
        ->assertJsonFragment([
        'domain' => $this->subscription2->domain,
        'available' => true,
        'linked' => false,
        ]);
    }

    #[Test]
    public function linkableDomainsNoneFound(): void
    {
        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.dns.templates.available_domains', ['template' => $this->dnsCustomerTemplate->id])
        )
            ->assertOk()
            ->assertJson(['domains' => []]);
    }

    #[Test]
    public function unLinkFromDomain(): void
    {
        $this->mockDnsService->expects(self::once())
            ->method('getDnsZone')
            ->willReturn(new DnsZone(new Fqdn('domain.com')));

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.dns.templates.unlink', ['template' => $this->dnsCustomerTemplate->id]),
            [
                'domains' => [
                    ['domain' => $this->subscription->domain],
                ],
            ]
        )->assertOk();
    }
}
