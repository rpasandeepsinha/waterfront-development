<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\NameServers;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\NameServerController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(NameServerController::class)]
class NameServerRealtimeregisterTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $provider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $groupExtension = new ProductGroupFactory()->extension()->createOne();
        $groupDns = new ProductGroupFactory()->dns()->createOne();

        $productNl = new ProductFactory()->nlDomain()->for($groupExtension)->createOne();
        $dnsProduct = ProductFactory::new()->for($groupDns)->createOne();

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

        $dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();

        $nameserverAssigner = self::resolve(DnsNameserverAssigner::class);
        $nameserverAssigner->assign($dnsDeployment);
    }

    #[Test]
    public function showNameservers(): void
    {
        $response = json_encode(
            include __DIR__ . '/../../../../Infra/RtrClient/data/domain_details_valid.php',
            JSON_THROW_ON_ERROR
        );

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], $response),
        ]);

        $this->app->bind(RealtimeRegister::class, fn (): RealtimeRegister => $sdk);

        $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.nameservers.show', $this->subscription->uuid)
        )->assertExactJson([
            'data' => [
                'isDefaultNameservers' => true,
                'nameservers' => [
                    [
                        'ip' => null,
                        'ip6' => null,
                        'name' => 'ns1.sandwave-test.com',
                    ],
                    [
                        'ip' => null,
                        'ip6' => null,
                        'name' => 'ns02.sandwave-test.com',
                    ],
                ],
                'nameservergroup' => null,
            ],
        ]);
    }

    #[Test]
    public function updateNameservers(): void
    {
        $this->actingAsCustomer($this->customer)->putJson(
            $this->generateRoute('partners.nameservers.update', $this->subscription->uuid),
            [
                'nameServers' => [
                    ['name' => 'newnameserver1.nl'],
                    ['name' => 'newnameserver2.nl'],
                ],
            ]
        )->assertOk();
    }

    #[Test]
    public function updateNameserversWithNull(): void
    {
        $this->actingAsCustomer($this->customer)->putJson(
            $this->generateRoute('partners.nameservers.update', $this->subscription->uuid),
            [
                'nameServers' => [
                    ['name' => 'newnameserver1.nl'],
                    ['name' => 'newnameserver2.nl'],
                    ['name' => 'newnameserver3.nl'],
                    ['name' => 'newnameserver4.nl'],
                    ['name' => null],
                    ['name' => null],
                    ['name' => null],
                    ['name' => null],
                ],
            ]
        )->assertOk();
    }

    #[Test]
    public function updateNameserversWithNullAndDuplicates(): void
    {
        $this->actingAsCustomer($this->customer)->putJson(
            $this->generateRoute('partners.nameservers.update', $this->subscription->uuid),
            [
                'nameServers' => [
                    ['name' => 'newnameserver1.nl'],
                    ['name' => 'newnameserver2.nl'],
                    ['name' => 'newnameserver2.nl'],
                    ['name' => null],
                    ['name' => null],
                    ['name' => null],
                    ['name' => null],
                    ['name' => null],
                ],
            ]
        )->assertUnprocessable()->assertJsonFragment(['message' => 'Dit veld heeft een dubbele waarde.']);
    }

    #[Test]
    public function checkValidationOnUpdateNameservers(): void
    {
        $this->actingAsCustomer($this->customer)->putJson(
            $this->generateRoute('partners.nameservers.update', $this->subscription->uuid),
            [
                'nameServers' => [
                    [
                        'name' => null,
                    ],
                    [
                        'name' => null,
                    ],
                    [
                        'name' => 'optional.nameserver1.nl',
                    ],
                    [
                        'name' => 'optional.nameserver2.nl',
                    ],
                ],
            ]
        )
            ->assertUnprocessable()
            ->assertJson(['errors' => ['nameServers.0.name' => ['Dit veld is verplicht.'], 'nameServers.1.name' => ['Dit veld is verplicht.']]]);
    }
}
