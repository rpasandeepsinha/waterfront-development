<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\NameServers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\NameServerController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(NameServerController::class)]
class NameServerOpenproviderTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $groupExtension = new ProductGroupFactory()->extension()->createOne();
        $groupDns = new ProductGroupFactory()->dns()->createOne();

        $productNl = new ProductFactory()->for($groupExtension)->createOne([
            'slug' => 'extension_nl',
        ]);

        $dnsProduct = ProductFactory::new()->freeDns($groupDns)->createOne();

        $this->subscription = new SubscriptionFactory()
            ->for($productNl)
            ->for($this->customer)
            ->createOne();

        new DomainDeploymentFactory()->for($provider)->for($this->subscription)->createOne();

        $domain = $this->subscription->domain;
        assert(is_string($domain));
        $dnsSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($dnsProduct)
            ->forDomain($domain)
            ->parentSubscription($this->subscription)
            ->createOne();

        new DnsDeploymentFactory()->for($dnsSubscription)->createOne();
    }

    #[Test]
    public function show(): void
    {
        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.nameservers.show', $this->subscription->uuid)
        )->assertExactJson([
            'data' => [
                'isDefaultNameservers' => false,
                'nameservers'     => [
                    [
                        'id'    => '312592',
                        'seqNr' => '0',
                        'name'  => 'ns1.customserver.nl',
                        'ip'    => '52.57.114.204',
                        'ip6'   => '2a05:d014:0f80:6e00:bde7:af96:9434:75d5',
                    ],
                    [
                        'id'    => '312595',
                        'seqNr' => '1',
                        'name'  => 'ns2.customserver.be',
                        'ip'    => '52.214.115.96',
                        'ip6'   => '2a05:d018:061d:bd00:21bc:c938:d548:dab1',
                    ],
                    [
                        'id'    => '312598',
                        'seqNr' => '2',
                        'name'  => 'ns3.customserver.eu',
                        'ip'    => '52.56.134.244',
                        'ip6'   => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                    ],
                    [
                        'id'    => '312598',
                        'seqNr' => '3',
                        'name'  => 'ns4.customserver.eu',
                        'ip'    => '52.56.134.244',
                        'ip6'   => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                    ],
                    [
                        'id'    => '312598',
                        'seqNr' => '4',
                        'name'  => 'ns5.customserver.eu',
                        'ip'    => '52.56.134.244',
                        'ip6'   => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                    ],
                    [
                        'id'    => '312598',
                        'seqNr' => '5',
                        'name'  => 'ns6.customserver.eu',
                        'ip'    => '52.56.134.244',
                        'ip6'   => '2a05:d01c:0433:e000:e62c:faa3:9834:41e7',
                    ],
                ],
                'nameservergroup' => 'externaltemplate',
            ],
        ]);
    }

    #[Test]
    public function update(): void
    {
        $this->actingAsCustomer($this->customer)->putJson(
            $this->generateRoute('partners.nameservers.update', $this->subscription->uuid),
            [
                'nameServers' => [
                    ['name' => 'newnameserver1.nl', 'ip' => '127.0.0.1', 'ip6' => '::1'],
                    ['name' => 'newnameserver2.nl', 'ip' => '127.0.0.2', 'ip6' => '::2'],
                ],
            ],
        )->assertOk();
    }
}
