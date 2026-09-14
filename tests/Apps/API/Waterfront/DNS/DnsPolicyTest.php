<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\DNS;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\DnsController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Products\Enums\ProductGroupType;

#[CoversClass(DnsController::class)]
class DnsPolicyTest extends integrationTestCase
{
    use PowerDnsMockHelper;

    private DnsService $dnsClass;

    private Customer $customer;

    private string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dnsClass = self::resolve(DnsService::class);
        $this->customer = new CustomerFactory()->createOne();

        $this->domain = 'test.nl';

        $dnsProduct = new ProductFactory()->for(
            new ProductGroupFactory()->dns(),
        )->createOne([
            'slug' => ProductGroupType::DNS->value,
        ]);

        $extensionProduct = new ProductFactory()->for(new ProductGroupFactory()->extension())->createOne();

        new SubscriptionFactory()
            ->for($this->customer)
            ->forDomain($this->domain)
            ->for($dnsProduct)
            ->administrativeStatusActive()
            ->createOne();

        new SubscriptionFactory()
            ->withCustomer()
            ->for($extensionProduct)
            ->forDomain($this->domain)
            ->administrativeStatusActive()
            ->createOne();

        Http::fake();

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(200, [], $this->getMockedZoneResponseBody($this->domain)),
        ]);

        $this->pdns($pdnsMock);
    }

    #[Test]
    public function dnsPolicyIndexAuthorizeSuccessfully(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.dns.index', [
                    'domain' => $this->domain,
                    'ServiceDns' => $this->dnsClass,
                ]),
            )
            ->assertOk();
    }

    #[Test]
    public function dnsPolicyCustomerCanNotManageDns(): void
    {
        $randomCustomer = new CustomerFactory()->createOne();

        $this->actingAsCustomer($randomCustomer)
            ->getJson(
                $this->generateRoute('partners.dns.index', [
                    'domain' => $this->domain,
                    'ServiceDns' => $this->dnsClass,
                ]),
            )
            ->assertForbidden();
    }

    #[Test]
    public function dnsControllerPolicyOnIndexFailsNoDnsSubscriptionAvailable(): void
    {
        $this->actingAsCustomer($this->customer)
            ->getJson(
                $this->generateRoute('partners.dns.index', ['domain' => 'random.nl', 'ServiceDns' => $this->dnsClass]),
            )
            ->assertForbidden();
    }
}
