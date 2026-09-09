<?php

declare(strict_types=1);

namespace Tests\Infra\OpenproviderClient;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\OpenproviderClient\Messages\Connection;
use Waterfront\Infra\OpenproviderClient\Messages\DomainRetrieveRequest;
use Waterfront\Infra\OpenproviderClient\OpenproviderClient;
use Webmozart\Assert\Assert;

#[CoversClass(OpenproviderClient::class)]
class DomainRetrieveTest extends IntegrationTestCase
{
    private Provider $domainProvider;

    private ProductGroup $productGroup;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = new CustomerFactory()->createOne();

        $this->domainProvider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'enabled' => true, 'default' => true, 'slug' => ProviderSlug::OPEN_PROVIDER]);
        $this->productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::EXTENSION,
            'slug' => ProductGroupType::EXTENSION,
        ]);

        new ProductFactory()->createOne([
            'product_group_id' => $this->productGroup->id,
            'name' => '.nl',
        ]);
    }

    #[Test]
    public function createXml(): void
    {
        $request = new DomainRetrieveRequest(
            new Client(),
            new Connection('https://test.nl', 'test-user', 'password'),
            'example.org'
        );

        $requestXml = (string) file_get_contents(__DIR__ . '/data/openprovider_retrieve_request.xml');
        $responseXml = $request->getXml();

        self::assertXmlStringEqualsXmlString($requestXml, $responseXml);
    }

    #[Test]
    public function processResponseXml(): void
    {
        $domain = 'example.org';
        $domainClient = self::resolve(OpenproviderClient::class);

        $product = new ProductFactory()->for($this->productGroup)->createOne();
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'customer_id'         => $this->customer->id,
            'domain'              => $domain,
            'start_date'          => CarbonImmutable::now(),
            'contract_period'     => 12,
            'end_date'            => null,
            'cancel_date'         => null,
        ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'last_result' => json_encode([]),
            'last_result_received' => CarbonImmutable::now(),
            'provider_id' => $this->domainProvider->id,
        ]);

        $result = $domainClient->retrieveDomain($domain);

        $subscription = Subscription::query()->whereProductGroupType(ProductGroupType::EXTENSION)->where('domain', $domain)->firstOrFail();
        $domainSub = $subscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainSub);

        assert(is_string($domainSub->last_result));

        $lastResult = json_decode($domainSub->last_result, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($lastResult));

        $lastResultDomain = $lastResult['domain'];
        Assert::isArray($lastResultDomain);
        Assert::string($lastResultDomain['name']);
        Assert::string($lastResultDomain['extension']);

        self::assertJson($domainSub->last_result);
        self::assertSame($domain, $lastResultDomain['name'] . '.' . $lastResultDomain['extension']);
        self::assertNotNull($domainSub->last_result_received);

        self::assertSame($domain, (string) $result->getDomain());
        self::assertSame('2019-06-13 16:22:21', $result->getOrderDate());
        self::assertSame('2019-06-13 16:22:22', $result->getActiveDate());
        self::assertSame('2020-06-13 14:22:22', $result->getExpirationDate());
        self::assertSame('2020-06-13 14:22:22', $result->getExpirationDateOpenprovider());
        self::assertSame(
            [
            'owner'   => 'FL969344-NL',
            'admin'   => 'FL969344-NL',
            'tech'    => 'FL969344-NL',
            'billing' => 'HANDLE-WITH-CARE',
            ],
            $result->getHandles()?->toArray()
        );
        self::assertSame('externaltemplate', $result->getNsGroup());
        self::assertSame(
            [
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
            $result->getNameServers()
        );
        self::assertSame('x7SMn%$7%Xn9', $result->getAuthCode());
        self::assertSame(DomainStatus::ACTIVE->value, $result->getStatus());
        self::assertNull($result->getAutoRenew());
        self::assertTrue($result->getIsLocked());
    }
}
