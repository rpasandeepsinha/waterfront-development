<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;

#[CoversNothing]
class OpenproviderClientTest extends IntegrationTestCase
{
    private const string DOMAIN = 'yourhosting.nl';

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();

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

        $subscription = new SubscriptionFactory()
            ->for($productNl)
            ->for($customer)
            ->createOne([
                'domain' => self::DOMAIN,
            ]);

        new DomainDeploymentFactory()
            ->for($provider)
            ->for($subscription)
            ->createOne();

        $domain = $subscription->domain;
        assert(is_string($domain));
        $dnsSubscription = SubscriptionFactory::new()
            ->withCustomer()
            ->for($dnsProduct)
            ->forDomain($domain)
            ->parentSubscription($subscription)
            ->createOne();

        new DnsDeploymentFactory()->for($dnsSubscription)->createOne();
    }

    #[Test]
    public function retrieveDomainDoesNotReturnEmptyArrayForIp(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/data/openprovider_retrieve_response_empty_ip.xml');
        $mock = new MockHandler([
            new Response(
                status: 200,
                body: $xml,
            ),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $openproviderClient = new OpenproviderClient(
            httpClient: $client,
            connection: self::createStub(OpenProviderConnectionInterface::class),
            jobDispatcher: self::createStub(Dispatcher::class),
        );
        $this->app->bind(Client::class, fn () => $client);

        $result = $openproviderClient->retrieveDomain(self::DOMAIN);
        self::assertNotNull($result->getNameServers());
        self::assertCount(1, $result->getNameServers());
        self::assertArrayHasKey(0, $result->getNameServers());
        self::assertNull($result->getNameServers()[0]['ip']);
        self::assertNull($result->getNameServers()[0]['ip6']);
    }
}
