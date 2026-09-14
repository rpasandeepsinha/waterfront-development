<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Integration;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RealtimeRegister\RealtimeRegister;
use RealtimeRegister\Support\AuthorizedClient;
use RealtimeRegister\Support\RealtimeRegisterResponse;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\OrderController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Generators\VanityNameserverGenerator;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\GandiClient\Config\ConnectorConfig;
use Waterfront\Infra\GandiClient\Connectors\GandiConnector;
use Waterfront\Infra\GandiClient\Requests\GetDomainRecordsRequest;
use Waterfront\Infra\Logging\Masker\JsonLogMasker;
use Waterfront\Infra\PowerDnsClient\Clients\InternalPowerDnsClient;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsMetadataType;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\SaloonClient\Config\RetryConfig;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(OrderController::class)]
class OrderDomainIntegrationTest extends IntegrationTestCase
{
    /**
     * This test domain matches the domains in the /data/domain-order.json
     * and the domain being used in the /response/*.json files.
     */
    private const string DOMAIN = 'domain-order-test.nl';

    /**
     * These nameservers match the nameservers in the /response/*.json files.
     *
     * @var string[]
     */
    private const array NAMESERVERS = [
        'ns1.test-ns.nl',
        'ns2.test-ns.nl',
        'ns3.test-ns.nl',
    ];

    /**
     * These nameservers match the nameservers in the /response/*-premium.json files.
     *
     * @var string[]
     */
    private const array VANITY_NAMESERVERS = [
        'ns210.vanity-test.nl',
        'ns97.vanity-test.de',
        'ns89.vanity-test.be',
    ];

    /**
     * This RTR test handle matches the data in the /response/rtr-*.json.
     */
    private const string TEST_HANDLE = 'test-contact-1';

    /**
     * This DNSSEC public key matches the data in the /response/pdns-*.json.
     */
    private const string DNSSEC_PUBLIC_KEY = '0+fI2D1RnKAgyuqLvVVBUMN1COWyv7Wth/N+vl/FN6Vsle+mmtgjuovHrXNH2nubAvrWG0g7eNfUDPgp0nppgw==';

    /**
     * This transfer code matches the transfer in the /data/domain-order-transfer.json.
     */
    private const string TRANSFER_CODE = 'transfer-code';

    private Customer $customer;

    private AuthorizedClient&MockInterface $rtrClient;

    private ClientInterface&MockInterface $pdnsClient;

    /**
     * This RTR customer matches the data in the /response/rtr-contacts.json.
     */
    private string $rtrCustomer = 'sandwave-ote1';

    private string $orderRoute;

    private Product $nlDomainProduct;

    private Product $freeDnsProduct;

    private LoggerInterface&MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = self::mock(LoggerInterface::class);
        $this->logger->shouldIgnoreMissing(true);

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne(['id' => 1001]);

        $rtrProvider = ProviderFactory::new()->createOne(
            [
                'type' => ProviderType::DOMAIN,
                'slug' => ProviderSlug::REALTIME_REGISTER,
                'enabled' => true,
                'default' => true,
            ],
        );

        $rtrContact = new DomainContactFactory()->for($this->customer)->createOne();

        $rtrContact->providers()->attach($rtrProvider, ['external_contact' => self::TEST_HANDLE]);

        $mockRtr = self::mock(AuthorizedClient::class);
        $this->rtrClient = $mockRtr;

        $this->app->bind(function () use ($mockRtr): RealtimeRegister {
            $externalRtr = new RealtimeRegister('api-key');
            $externalRtr->setClient($mockRtr);

            return $externalRtr;
        });

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () use ($mockRtr) {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($mockRtr);

                return $externalRtr;
            });

        $mockDnsClient = self::mock(ClientInterface::class);
        $mockDns = new InternalPowerDnsClient($mockDnsClient);
        $this->pdnsClient = $mockDnsClient;
        $this->app->bind(InternalPowerDnsClient::class, fn () => $mockDns);

        $this->orderRoute = $this->generateRoute('partners.order.order');

        $this->nlDomainProduct = new ProductFactory()->nlDomain()->createOne();
        new ProductPriceComponentFactory()
            ->for($this->nlDomainProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 99,
            ]);
        new ProductSpecFactory()->for($this->nlDomainProduct)->createOne([
            'name' => ProductSpecName::DOMAIN_DNSSEC_ENABLED,
            'value' => 'yes',
        ]);

        $this->freeDnsProduct = new ProductFactory()->freeDns()->createOne();

        new ProductPriceComponentFactory()
            ->for($this->freeDnsProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 0,
            ]);
    }

    #[Override]
    public function setUpNameservers(): void
    {
        $regions = new DnsRegionFactory()->createMany(3);
        foreach ($regions as $index => $region) {
            $region
                ->dnsNameservers()
                ->save(new DnsNameserverFactory()->makeOne([
                    'nameserver' => self::NAMESERVERS[$index],
                ]));
        }
    }

    #[Test]
    public function orderDomainWithMinimalRegister(): void
    {
        $this->actingAsCustomer($this->customer);
        $orderJson = (string) file_get_contents(__DIR__ . '/data/domain-order.json');
        /** @var array<mixed> $postData */
        $postData = json_decode($orderJson, true);

        self::assertRtrDomainCheckCalled();
        self::assertRtrGetContactsCalled();
        self::assertRtrFetchExistingDomainCalled(times: 2);
        self::assertRtrTldInfoCalled();
        self::assertRtrMinimalRegisterDomainCalled();
        self::assertRtrUpdateDomainCalled();
        self::assertPdnsCheckZoneCalledConsecutive();
        self::assertPdnsCreateZoneCalled();
        self::assertPdnsEnableDnsSecCalled();
        self::assertPdnsGetZoneKeysCalled();

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'Minimal Register Using handles',
                [
                    LoggingContextKeys::META => [
                        'domain.handles' => [
                            [
                                'role' => 'ADMIN',
                                'handle' => self::TEST_HANDLE,
                            ],
                            [
                                'role' => 'BILLING',
                                'handle' => self::TEST_HANDLE,
                            ],
                            [
                                'role' => 'TECH',
                                'handle' => self::TEST_HANDLE,
                            ],
                        ],
                    ],
                ],
            );

        $this->logger
            ->shouldNotReceive('debug')
            ->withArgs(
                fn (string $message): bool => (
                    $message
                    === 'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.'
                ),
            );

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('UpdateDomainRegistrationJob started for domain {domain.name}', true);

        $response = $this->post($this->orderRoute, $postData);
        $response->assertOk();

        $domainSubscription = Subscription::where([
            'customer_id' => $this->customer->id,
            'product_uuid' => $this->nlDomainProduct->uuid,
            'technical_status' => DomainStatus::ACTIVE,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->first();

        self::assertInstanceOf(Subscription::class, $domainSubscription);
        self::assertInstanceOf(DomainDeployment::class, $domainSubscription->domainDeployment);

        $dnsChildSubscription = Subscription::with('dnsDeployment')
            ->where([
                'customer_id' => $this->customer->id,
                'product_uuid' => $this->freeDnsProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'parent_subscription_id' => $domainSubscription->id,
            ])
            ->firstOrFail();

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
        $nameservers = $dnsChildSubscription->dnsDeployment->dnsNameservers;
        foreach ($nameservers as $nameserver) {
            self::assertContains($nameserver->nameserver, self::NAMESERVERS);
        }

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
    }

    #[Test]
    public function orderDomainWithMinimalRegisterPremiumDns(): void
    {
        $premiumDnsProduct = new ProductFactory()
            ->premiumDns($this->freeDnsProduct->productGroup)
            ->createOne();
        new ProductPriceComponentFactory()
            ->for($premiumDnsProduct)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 199,
            ]);
        new ProductSpecFactory()->for($premiumDnsProduct)->createOne([
            'name' => ProductSpecName::DNS_IS_PREMIUM->value,
            'value' => true,
        ]);

        $mockVanityNameserverGenerator = self::createMock(VanityNameserverGenerator::class);
        $this->app->bind(VanityNameserverGenerator::class, fn () => $mockVanityNameserverGenerator);

        $mockVanityNameserverGenerator
            ->expects(self::once())
            ->method('generateVanityNames')
            ->with(self::DOMAIN, ['vanity-test.nl', 'vanity-test.de', 'vanity-test.be'])
            ->willReturn(self::VANITY_NAMESERVERS);

        $this->actingAsCustomer($this->customer);
        $orderJson = (string) file_get_contents(__DIR__ . '/data/domain-order-premium-dns.json');
        /** @var array<mixed> $postData */
        $postData = json_decode($orderJson, true);

        self::assertGandiGetRecordsCalled();
        self::assertRtrDomainCheckCalled();
        self::assertRtrGetContactsCalled();
        self::assertRtrFetchExistingDomainCalled(times: 2);
        self::assertRtrTldInfoCalled();
        self::assertRtrMinimalRegisterDomainCalled();
        self::assertRtrUpdateDomainForPremiumDnsCalled();
        self::assertPdnsCheckPremiumZoneCalledConsecutive();
        self::assertPdnsCreatePremiumZoneCalled();
        self::assertPdnsModifyCalled();
        self::assertPdnsEnableMetaDataCalled(PowerDnsMetadataType::ALLOW_AXFR_FROM);
        self::assertPdnsEnableMetaDataCalled(PowerDnsMetadataType::ALSO_NOTIFY);
        self::assertPdnsEnableMetaSoaEditCalled();
        self::assertPdnsUpdateLiveDnsCalled();
        self::assertPdnsSendNotifyCalled();
        self::assertPdnsEnableDnsSecCalled();
        self::assertPdnsGetZoneKeysCalled();

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'Minimal Register Using handles',
                [
                    LoggingContextKeys::META => [
                        'domain.handles' => [
                            [
                                'role' => 'ADMIN',
                                'handle' => self::TEST_HANDLE,
                            ],
                            [
                                'role' => 'BILLING',
                                'handle' => self::TEST_HANDLE,
                            ],
                            [
                                'role' => 'TECH',
                                'handle' => self::TEST_HANDLE,
                            ],
                        ],
                    ],
                ],
            );

        $this->logger
            ->shouldNotReceive('debug')
            ->withArgs(
                fn (string $message): bool => (
                    $message
                    === 'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.'
                ),
            );

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('UpdateDomainRegistrationJob started for domain {domain.name}', true);

        $response = $this->post($this->orderRoute, $postData);
        $response->assertOk();

        $domainSubscription = Subscription::where([
            'customer_id' => $this->customer->id,
            'product_uuid' => $this->nlDomainProduct->uuid,
            'technical_status' => DomainStatus::ACTIVE,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->first();

        self::assertInstanceOf(Subscription::class, $domainSubscription);
        self::assertInstanceOf(DomainDeployment::class, $domainSubscription->domainDeployment);

        $dnsChildSubscription = Subscription::with('dnsDeployment')
            ->where([
                'customer_id' => $this->customer->id,
                'product_uuid' => $premiumDnsProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'parent_subscription_id' => $domainSubscription->id,
            ])
            ->firstOrFail();

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
        $nameservers = $dnsChildSubscription->dnsDeployment->dnsNameservers;
        foreach ($nameservers as $index => $nameserver) {
            self::assertSame(self::NAMESERVERS[$index], $nameserver->nameserver);
        }

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
    }

    #[Test]
    public function orderDomainWithMinimalTransfer(): void
    {
        $this->actingAsCustomer($this->customer);
        $orderJson = (string) file_get_contents(__DIR__ . '/data/domain-order-transfer.json');
        /** @var array<mixed> $postData */
        $postData = json_decode($orderJson, true);

        self::assertRtrDomainCheckCalled();
        self::assertRtrFetchExistingDomainCalled(times: 2);
        self::assertRtrGetContactsCalled();
        self::assertRtrTldInfoCalled();
        self::assertRtrMinimalTransferDomainCalled();
        self::assertRtrUpdateDomainCalled();
        self::assertPdnsCheckZoneCalledConsecutive();
        self::assertPdnsCreateZoneCalled();
        self::assertPdnsEnableDnsSecCalled();
        self::assertPdnsGetZoneKeysCalled();

        $this->logger
            ->shouldReceive('info')
            ->once()
            // we don't assert the logging context here because we don't know the subscription UUID.
            ->with('Minimal Transfer for domain {domain.name}', true);

        $this->logger
            ->shouldNotReceive('debug')
            ->withArgs(
                fn (string $message): bool => (
                    $message
                    === 'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.'
                ),
            );

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with('UpdateDomainRegistrationJob started for domain {domain.name}', true);

        $response = $this->post($this->orderRoute, $postData);
        $response->assertOk();

        $domainSubscription = Subscription::where([
            'customer_id' => $this->customer->id,
            'product_uuid' => $this->nlDomainProduct->uuid,
            'technical_status' => DomainStatus::ACTIVE->value,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->first();

        self::assertInstanceOf(Subscription::class, $domainSubscription);
        self::assertInstanceOf(DomainDeployment::class, $domainSubscription->domainDeployment);
        self::assertSame(self::TRANSFER_CODE, $domainSubscription->domainDeployment->transfer_secret);

        $dnsChildSubscription = Subscription::with('dnsDeployment')
            ->where([
                'customer_id' => $this->customer->id,
                'product_uuid' => $this->freeDnsProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'parent_subscription_id' => $domainSubscription->id,
            ])
            ->firstOrFail();

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
        $nameservers = $dnsChildSubscription->dnsDeployment->dnsNameservers;
        foreach ($nameservers as $index => $nameserver) {
            self::assertSame(self::NAMESERVERS[$index], $nameserver->nameserver);
        }

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
    }

    #[Test]
    public function orderDomainWithZoneCheck(): void
    {
        new ProductSpecFactory()->for($this->nlDomainProduct)->createOne();

        $this->actingAsCustomer($this->customer);
        $orderJson = (string) file_get_contents(__DIR__ . '/data/domain-order.json');
        /** @var array<mixed> $postData */
        $postData = json_decode($orderJson, true);

        self::assertRtrDomainCheckCalled();
        self::assertRtrGetContactsCalled();
        self::assertRtrTldInfoCalled(__DIR__ . '/response/rtr-tld-metadata-de.json');
        self::assertRtrRegisterDomainCalled();
        self::assertPdnsCheckZoneCalledConsecutive();
        self::assertPdnsCreateZoneCalled();
        self::assertPdnsEnableDnsSecCalled();
        self::assertPdnsGetZoneKeysCalled();

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->withArgs(
                fn (string $message, array $context): bool => (
                    $message
                    === 'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.'
                    && $context[LoggingContextKeys::PRODUCT_SLUG] === $this->nlDomainProduct->slug
                    && $context[LoggingContextKeys::DOMAIN_NAME] === self::DOMAIN
                    && $context[LoggingContextKeys::PROVISIONING_TYPE] === 'domain.registration'
                    && is_string($context[LoggingContextKeys::SUBSCRIPTION_UUID])
                    && $context[LoggingContextKeys::SUBSCRIPTION_UUID] !== ''
                ),
            );

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'RegisterDomainJob started for domain domain-order-test.nl. Registration or transfer: domain.registration',
                true,
            );

        $response = $this->post($this->orderRoute, $postData);
        $response->assertOk();

        $domainSubscription = Subscription::where([
            'customer_id' => $this->customer->id,
            'product_uuid' => $this->nlDomainProduct->uuid,
            'technical_status' => DomainStatus::ACTIVE,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->first();

        self::assertInstanceOf(Subscription::class, $domainSubscription);
        self::assertInstanceOf(DomainDeployment::class, $domainSubscription->domainDeployment);

        $dnsChildSubscription = Subscription::with('dnsDeployment')
            ->where([
                'customer_id' => $this->customer->id,
                'product_uuid' => $this->freeDnsProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'parent_subscription_id' => $domainSubscription->id,
            ])
            ->firstOrFail();

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
        $nameservers = $dnsChildSubscription->dnsDeployment->dnsNameservers;
        foreach ($nameservers as $index => $nameserver) {
            self::assertSame(self::NAMESERVERS[$index], $nameserver->nameserver);
        }

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
    }

    #[Test]
    public function orderDomainWithZoneCheckTransfer(): void
    {
        new ProductSpecFactory()->for($this->nlDomainProduct)->createOne();

        $this->actingAsCustomer($this->customer);
        $orderJson = (string) file_get_contents(__DIR__ . '/data/domain-order-transfer.json');
        /** @var array<mixed> $postData */
        $postData = json_decode($orderJson, true);

        self::assertRtrDomainCheckCalled();
        self::assertRtrGetContactsCalled();
        self::assertRtrTldInfoCalled(__DIR__ . '/response/rtr-tld-metadata-de.json');
        self::assertRtrTransferDomainCalled();
        self::assertPdnsCheckZoneCalledConsecutive();
        self::assertPdnsCreateZoneCalled();
        self::assertPdnsEnableDnsSecCalled();
        self::assertPdnsGetZoneKeysCalled();

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->withArgs(
                fn (string $message, array $context): bool => (
                    $message
                    === 'Domain tld [{product.slug}] has zone check enabled. Skipping minimal transfer/registration.'
                    && $context[LoggingContextKeys::PRODUCT_SLUG] === $this->nlDomainProduct->slug
                    && $context[LoggingContextKeys::DOMAIN_NAME] === self::DOMAIN
                    && $context[LoggingContextKeys::PROVISIONING_TYPE] === 'domain.transfer'
                    && is_string($context[LoggingContextKeys::SUBSCRIPTION_UUID])
                    && $context[LoggingContextKeys::SUBSCRIPTION_UUID] !== ''
                ),
            );

        $this->logger
            ->shouldReceive('info')
            ->once()
            ->with(
                'RegisterDomainJob started for domain domain-order-test.nl. Registration or transfer: domain.transfer',
                true,
            );

        $response = $this->post($this->orderRoute, $postData);
        $response->assertOk();

        $domainSubscription = Subscription::where([
            'customer_id' => $this->customer->id,
            'product_uuid' => $this->nlDomainProduct->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->first();

        self::assertInstanceOf(Subscription::class, $domainSubscription);
        self::assertInstanceOf(DomainDeployment::class, $domainSubscription->domainDeployment);
        self::assertSame(self::TRANSFER_CODE, $domainSubscription->domainDeployment->transfer_secret);

        $dnsChildSubscription = Subscription::with('dnsDeployment')
            ->where([
                'customer_id' => $this->customer->id,
                'product_uuid' => $this->freeDnsProduct->uuid,
                'technical_status' => TechnicalStatus::OK->value,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'parent_subscription_id' => $domainSubscription->id,
            ])
            ->firstOrFail();

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
        $nameservers = $dnsChildSubscription->dnsDeployment->dnsNameservers;
        foreach ($nameservers as $index => $nameserver) {
            self::assertSame(self::NAMESERVERS[$index], $nameserver->nameserver);
        }

        self::assertNotNull($dnsChildSubscription->dnsDeployment, 'DNS Deployment is null.');
    }

    private function assertRtrDomainCheckCalled(): void
    {
        $checkResponse = (string) file_get_contents(__DIR__ . '/response/rtr-check.json');
        $this->rtrClient
            ->shouldReceive('get')
            ->once()
            ->with(sprintf('v2/domains/%s/check', self::DOMAIN), [])
            ->andReturn(new RealtimeRegisterResponse($checkResponse, [], 200));
    }

    private function assertRtrFetchExistingDomainCalled(int $times = 1): void
    {
        $fetchResponse = (string) file_get_contents(__DIR__ . '/response/rtr-fetch.json');

        $this->rtrClient
            ->shouldReceive('get')
            ->times($times)
            ->with(sprintf('v2/domains/%s', self::DOMAIN))
            ->andReturn(new RealtimeRegisterResponse($fetchResponse, [], 200));
    }

    private function assertRtrGetContactsCalled(): void
    {
        $contactResponse = (string) file_get_contents(__DIR__ . '/response/rtr-contacts.json');
        $this->rtrClient
            ->shouldReceive('get')
            ->twice()
            ->with(sprintf('v2/customers/%s/contacts/%s', $this->rtrCustomer, self::TEST_HANDLE))
            ->andReturn(new RealtimeRegisterResponse($contactResponse, [], 200));
    }

    private function assertRtrTldInfoCalled(?string $jsonResponseFile = null): void
    {
        $tldMetaDataResponse = (string) file_get_contents($jsonResponseFile
        ?? __DIR__ . '/response/rtr-tld-metadata-nl.json');
        $this->rtrClient
            ->shouldReceive('get')
            ->once()
            ->with('v2/tlds/nl/info')
            ->andReturn(new RealtimeRegisterResponse($tldMetaDataResponse, [], 200));
    }

    private function assertRtrRegisterDomainCalled(): void
    {
        $registerResponse = (string) file_get_contents(__DIR__ . '/response/rtr-register.json');
        $this->rtrClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (string $endpoint, $registerPayload): bool {
                    $correctDomain = $endpoint === sprintf('v2/domains/%s', self::DOMAIN);
                    $correctHandle =
                        array_key_exists(
                            'customer',
                            $registerPayload,
                        )
                        && $registerPayload['customer'] === $this->rtrCustomer;

                    $correctNameservers =
                        array_key_exists(
                            'ns',
                            $registerPayload,
                        )
                        && $registerPayload['ns'] === self::NAMESERVERS;
                    $correctKeyData =
                        array_key_exists('keyData', $registerPayload)
                        && $registerPayload['keyData'] === [
                            [
                                'protocol' => 3,
                                'flags' => 257,
                                'algorithm' => 13,
                                'publicKey' => self::DNSSEC_PUBLIC_KEY,
                            ],
                        ];
                    $isMissingAuthCode = ! array_key_exists('authCode', $registerPayload);

                    // Including nameservers and DNSSEC assert that we do a 'regular' register with zone check enabled
                    return (
                        $correctDomain
                        && $correctHandle
                        && $correctNameservers
                        && $correctKeyData
                        && $isMissingAuthCode
                    );
                },
            )
            ->andReturn(new RealtimeRegisterResponse($registerResponse, [], 200));
    }

    private function assertRtrMinimalRegisterDomainCalled(): void
    {
        $registerResponse = (string) file_get_contents(__DIR__ . '/response/rtr-register.json');
        $this->rtrClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (string $endpoint, $registerPayload): bool {
                    $correctDomain = $endpoint === sprintf('v2/domains/%s', self::DOMAIN);
                    $correctHandle =
                        array_key_exists(
                            'customer',
                            $registerPayload,
                        )
                        && $registerPayload['customer'] === $this->rtrCustomer;
                    $isMissingNs = ! array_key_exists('ns', $registerPayload);
                    $isMissingDnsSec = ! array_key_exists('keyData', $registerPayload);
                    $isMissingAuthCode = ! array_key_exists('authCode', $registerPayload);

                    // No nameservers and no DNSSEC assert that we do a minimal register
                    return $correctDomain && $correctHandle && $isMissingNs && $isMissingDnsSec && $isMissingAuthCode;
                },
            )
            ->andReturn(new RealtimeRegisterResponse($registerResponse, [], 200));
    }

    private function assertRtrUpdateDomainForPremiumDnsCalled(): void
    {
        $updateResponse = (string) file_get_contents(__DIR__ . '/response/rtr-update.json');
        $this->rtrClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (string $endpoint, array $updatePayload) {
                    $correctEndpoint = $endpoint === sprintf('v2/domains/%s/update', self::DOMAIN);
                    $correctNameservers =
                        array_key_exists(
                            'ns',
                            $updatePayload,
                        )
                        && $updatePayload['ns'] === self::VANITY_NAMESERVERS;
                    $correctKeyData =
                        array_key_exists('keyData', $updatePayload)
                        && $updatePayload['keyData'] === [
                            [
                                'protocol' => 3,
                                'flags' => 257,
                                'algorithm' => 13,
                                'publicKey' => self::DNSSEC_PUBLIC_KEY,
                            ],
                        ];

                    return $correctEndpoint && $correctNameservers && $correctKeyData;
                },
            )
            ->andReturn(new RealtimeRegisterResponse($updateResponse, [], 200));
    }

    private function assertRtrUpdateDomainCalled(): void
    {
        $updateResponse = (string) file_get_contents(__DIR__ . '/response/rtr-update.json');
        $this->rtrClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (string $endpoint, array $updatePayload) {
                    $correctEndpoint = $endpoint === sprintf('v2/domains/%s/update', self::DOMAIN);
                    $correctNameservers =
                        array_key_exists(
                            'ns',
                            $updatePayload,
                        )
                        && $updatePayload['ns'] === self::NAMESERVERS;
                    $correctKeyData =
                        array_key_exists('keyData', $updatePayload)
                        && $updatePayload['keyData'] === [
                            [
                                'protocol' => 3,
                                'flags' => 257,
                                'algorithm' => 13,
                                'publicKey' => self::DNSSEC_PUBLIC_KEY,
                            ],
                        ];

                    return $correctEndpoint && $correctNameservers && $correctKeyData;
                },
            )
            ->andReturn(new RealtimeRegisterResponse($updateResponse, [], 200));
    }

    private function assertPdnsCheckPremiumZoneCalledConsecutive(): void
    {
        $getZoneResponse = (string) file_get_contents(__DIR__ . '/response/pdns-get-zone-premium.json');
        $this->pdnsClient
            ->shouldReceive('send')
            ->withArgs(
                fn (Request $request) => (
                    rtrim($request->getUri()->getPath(), '.') === sprintf(
                        'api/v1/servers/localhost/zones/%s',
                        self::DOMAIN,
                    )
                    && $request->getMethod() === 'GET'
                ),
            )
            ->andReturn(
                // First call to check if zone exists from DnsCreationListener
                new Response(status: 404, headers: [], body: '{error: "Not found"}'),
                // Second call from RtrService (getDomainKeyDataCollection -> isDnssecSupported)
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Third call from RtrService (getDomainKeyDataCollection -> getDnsZone)
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Fourth call from RtrService (getDomainKeyDataCollection -> enableDnssec)
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Fifth call from DnsService (applyDiffToZone -> changeZone -> getZone)
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Sixth call from UpdateNameserverAndSoaAction (updateRecords -> getDnsZone)
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Seventh call from DnsService (ensureMinimumTTL -> getDnsZone)
                new Response(status: 200, headers: [], body: $getZoneResponse),
            );
    }

    private function assertPdnsModifyCalled(): void
    {
        $getZoneResponse = (string) file_get_contents(__DIR__ . '/response/pdns-get-zone-premium.json');
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                fn (Request $request) => (
                    rtrim($request->getUri()->getPath(), '.') === sprintf(
                        'api/v1/servers/localhost/zones/%s',
                        self::DOMAIN,
                    )
                    && $request->getMethod() === 'PATCH'
                ),
            )
            ->andReturn(
                new Response(status: 200, headers: [], body: $getZoneResponse),
            );
    }

    private function assertPdnsCheckZoneCalledConsecutive(): void
    {
        $getZoneResponse = (string) file_get_contents(__DIR__ . '/response/pdns-get-zone.json');
        $this->pdnsClient
            ->shouldReceive('send')
            ->times(4)
            ->withArgs(
                fn (Request $request) => (
                    $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s',
                        self::DOMAIN,
                    )
                    && $request->getMethod() === 'GET'
                ),
            )
            ->andReturn(
                new Response(status: 404, headers: [], body: '{error: "Not found"}'),
                // First call to check if zone exists from DnsCreationListener
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Second call from RtrService (getDomainKeyDataCollection -> isDnssecSupported)
                new Response(status: 200, headers: [], body: $getZoneResponse),
                // Third call from RtrService (getDomainKeyDataCollection -> getDnsZone)
                new Response(
                    status: 200,
                    headers: [],
                    body: $getZoneResponse,
                ), // Fourth call from RtrService (getDomainKeyDataCollection -> enableDnssec)
            );
    }

    private function assertPdnsCreateZoneCalled(): void
    {
        $createZoneResponse = (string) file_get_contents(__DIR__ . '/response/pdns-create-zone.json');
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                function (Request $request) {
                    /** @var ?array<string, mixed> $sendData */
                    $sendData = json_decode($request->getBody()->getContents(), true);
                    if (! is_array($sendData) || ! is_array($sendData['rrsets'])) {
                        return false;
                    }

                    $rrsets = new Collection($sendData['rrsets']);
                    $nameservers = $rrsets->firstWhere('type', '=', 'NS');

                    if (! is_array($nameservers)) {
                        return false;
                    }

                    $correctNameservers = true;
                    foreach ($nameservers['records'] as $index => $record) {
                        if (! is_string($record['content'])) {
                            $correctNameservers = false;
                            break;
                        }

                        $correctNameservers = $record['content'] === self::NAMESERVERS[$index] . '.';
                    }

                    $correctDomain = $sendData['name'] === self::DOMAIN . '.';
                    $correctEndpoint = $request->getUri()->getPath() === 'api/v1/servers/localhost/zones';
                    $correctMethod = $request->getMethod() === 'POST';

                    return $correctNameservers && $correctDomain && $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(200, [], $createZoneResponse));
    }

    private function assertPdnsCreatePremiumZoneCalled(): void
    {
        $createZoneResponse = (string) file_get_contents(__DIR__ . '/response/pdns-create-zone-premium.json');
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                function (Request $request) {
                    /** @var ?array<string, mixed> $sendData */
                    $sendData = json_decode($request->getBody()->getContents(), true);
                    if (! is_array($sendData) || ! is_array($sendData['rrsets'])) {
                        return false;
                    }

                    $rrsets = new Collection($sendData['rrsets']);
                    $nameservers = $rrsets->firstWhere('type', '=', 'NS');

                    if (! is_array($nameservers)) {
                        return false;
                    }

                    $correctNameservers = true;
                    foreach ($nameservers['records'] as $index => $record) {
                        if (! is_string($record['content'])) {
                            $correctNameservers = false;
                            break;
                        }

                        $correctNameservers = $record['content'] === self::VANITY_NAMESERVERS[$index] . '.';
                    }

                    $correctDomain = $sendData['name'] === self::DOMAIN . '.';
                    $correctEndpoint = $request->getUri()->getPath() === 'api/v1/servers/localhost/zones';
                    $correctMethod = $request->getMethod() === 'POST';

                    return $correctNameservers && $correctDomain && $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(200, [], $createZoneResponse));
    }

    private function assertPdnsEnableMetaSoaEditCalled(): void
    {
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                function (Request $request) {
                    /** @var ?array<string, mixed> $sendData */
                    $sendData = json_decode($request->getBody()->getContents(), true);
                    if (! is_array($sendData)) {
                        return false;
                    }

                    self::assertSame(['soa_edit' => 'INCEPTION-INCREMENT'], $sendData);

                    $correctEndpoint = $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s',
                        self::DOMAIN,
                    );
                    $correctMethod = $request->getMethod() === 'PUT';

                    return $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(status: 200, headers: []));
    }

    private function assertPdnsEnableMetaDataCalled(PowerDnsMetadataType $metaDataType): void
    {
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                function (Request $request) use ($metaDataType) {
                    /** @var ?array<string, mixed> $sendData */
                    $sendData = json_decode($request->getBody()->getContents(), true);
                    if (! is_array($sendData)) {
                        return false;
                    }

                    $isAllowAxfr = array_key_exists('kind', $sendData) && $sendData['kind'] === $metaDataType->value;

                    $correctEndpoint = $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s/metadata',
                        self::DOMAIN,
                    );
                    $correctMethod = $request->getMethod() === 'POST';

                    return $isAllowAxfr && $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(status: 200, headers: []));
    }

    private function assertPdnsSendNotifyCalled(): void
    {
        $this->pdnsClient
            ->shouldReceive('send')
            ->twice()
            ->withArgs(
                function (Request $request) {
                    $correctEndpoint = $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s/notify',
                        self::DOMAIN,
                    );
                    $correctMethod = $request->getMethod() === 'PUT';

                    return $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(status: 200, headers: []));

        $this->pdnsClient
            ->shouldReceive('send')
            ->withArgs(
                function (Request $request) {
                    $correctEndpoint = $request->getUri()->getPath() === sprintf(
                        'notify-gandi/%s',
                        self::DOMAIN,
                    );
                    $correctMethod = $request->getMethod() === 'GET';

                    return $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(status: 200, headers: [], body: ''));
    }

    private function assertPdnsUpdateLiveDnsCalled(): void
    {
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                function (Request $request) {
                    /** @var ?array<string, mixed> $sendData */
                    $sendData = json_decode($request->getBody()->getContents(), true);
                    if (! is_array($sendData)) {
                        return false;
                    }

                    $isLiveDnsUpdate = array_key_exists('account', $sendData) && $sendData['account'] === 'LiveDns';

                    $correctEndpoint = $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s',
                        self::DOMAIN,
                    );
                    $correctMethod = $request->getMethod() === 'PUT';

                    return $isLiveDnsUpdate && $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(status: 204, headers: []));
    }

    private function assertPdnsEnableDnsSecCalled(): void
    {
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                function (Request $request) {
                    /** @var ?array<string, mixed> $sendData */
                    $sendData = json_decode($request->getBody()->getContents(), true);
                    if (! is_array($sendData) || ! is_array($sendData['rrsets'])) {
                        return false;
                    }

                    $dnsSecEnabled = array_key_exists('dnssec', $sendData) && $sendData['dnssec'] === true;
                    $correctDomain = array_key_exists('name', $sendData) && $sendData['name'] === self::DOMAIN . '.';
                    $correctEndpoint = $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s.',
                        self::DOMAIN,
                    );
                    $correctMethod = $request->getMethod() === 'PUT';

                    return $dnsSecEnabled && $correctDomain && $correctEndpoint && $correctMethod;
                },
            )
            ->andReturn(new Response(status: 204, headers: []));
    }

    private function assertPdnsGetZoneKeysCalled(): void
    {
        $zoneKeysResponse = (string) file_get_contents(__DIR__ . '/response/pdns-zone-keys.json');
        $this->pdnsClient
            ->shouldReceive('send')
            ->once()
            ->withArgs(
                fn (Request $request) => (
                    $request->getUri()->getPath() === sprintf(
                        'api/v1/servers/localhost/zones/%s/cryptokeys',
                        self::DOMAIN,
                    )
                    && $request->getMethod() === 'GET'
                ),
            )
            ->andReturn(new Response(status: 200, headers: [], body: $zoneKeysResponse));
    }

    private function assertRtrTransferDomainCalled(): void
    {
        $registerResponse = (string) file_get_contents(__DIR__ . '/response/rtr-transfer.json');
        $this->rtrClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (string $endpoint, $registerPayload): bool {
                    $correctDomain = $endpoint === sprintf('v2/domains/%s/transfer', self::DOMAIN);
                    $correctHandle =
                        array_key_exists(
                            'customer',
                            $registerPayload,
                        )
                        && $registerPayload['customer'] === $this->rtrCustomer;

                    $correctNameservers =
                        array_key_exists(
                            'ns',
                            $registerPayload,
                        )
                        && $registerPayload['ns'] === self::NAMESERVERS;
                    $correctKeyData =
                        array_key_exists('keyData', $registerPayload)
                        && $registerPayload['keyData'] === [
                            [
                                'protocol' => 3,
                                'flags' => 257,
                                'algorithm' => 13,
                                'publicKey' => self::DNSSEC_PUBLIC_KEY,
                            ],
                        ];
                    $correctAuthCode =
                        array_key_exists(
                            'authcode',
                            $registerPayload,
                        )
                        && $registerPayload['authcode'] === self::TRANSFER_CODE;

                    // Correct nameservers and DNSSEC assert that we do a 'regular' transfer with zone check enabled
                    return (
                        $correctDomain
                        && $correctHandle
                        && $correctNameservers
                        && $correctKeyData
                        && $correctAuthCode
                    );
                },
            )
            ->andReturn(new RealtimeRegisterResponse($registerResponse, [], 200));
    }

    private function assertRtrMinimalTransferDomainCalled(): void
    {
        $registerResponse = (string) file_get_contents(__DIR__ . '/response/rtr-transfer.json');
        $this->rtrClient
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (string $endpoint, $registerPayload): bool {
                    $correctDomain = $endpoint === sprintf('v2/domains/%s/transfer', self::DOMAIN);
                    $correctHandle =
                        array_key_exists(
                            'customer',
                            $registerPayload,
                        )
                        && $registerPayload['customer'] === $this->rtrCustomer;
                    $isMissingNs = ! array_key_exists('ns', $registerPayload);
                    $isMissingDnsSec = ! array_key_exists('keyData', $registerPayload);
                    $correctAuthCode =
                        array_key_exists(
                            'authcode',
                            $registerPayload,
                        )
                        && $registerPayload['authcode'] === self::TRANSFER_CODE;

                    // No nameservers and no DNSSEC assert that we do a minimal transfer
                    return $correctDomain && $correctHandle && $isMissingNs && $isMissingDnsSec && $correctAuthCode;
                },
            )
            ->andReturn(new RealtimeRegisterResponse($registerResponse, [], 200));
    }

    private function assertGandiGetRecordsCalled(): void
    {
        $gandiRecordResponse = (string) file_get_contents(__DIR__ . '/response/gandi-records.json');
        $mockGandiClient = new MockClient([
            GetDomainRecordsRequest::class => MockResponse::make($gandiRecordResponse, 200),
        ]);

        $this->app->bind(function () use ($mockGandiClient): GandiConnector {
            $gandiMockConfig = new ConnectorConfig(
                baseUrl: 'https://gandi-api.net/v1337',
                authToken: '19782c819e7ftest504015f1097c6457917a1908',
                retryConfig: new RetryConfig(),
            );

            $client = new GandiConnector(
                $gandiMockConfig,
                $this->app->make(LoggerInterface::class),
                $this->app->make(JsonLogMasker::class),
            );

            $client->withMockClient($mockGandiClient);

            return $client;
        });
    }
}
