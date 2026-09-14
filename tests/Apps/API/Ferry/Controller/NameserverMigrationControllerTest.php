<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use RealtimeRegister\RealtimeRegister;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsDeploymentFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Apps\API\Ferry\Controllers\NameserverMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsRegion;
use Waterfront\Domain\DNS\Services\DnsNameserverRetriever;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Ferry\Services\NameserverResolver;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(NameserverMigrationController::class)]
class NameserverMigrationControllerTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DOMAIN = 'test-domain.testing';
    private const string TEST_DOMAIN_OP = 'test-domain-op.testing';

    private Customer $customer;

    private Subscription $extensionSubscription;

    private DnsNameserver $ns1;

    private DnsNameserver $ns2;

    private DnsNameserver $ns3;

    private DnsNameserver $ns4;

    private DnsDeployment $dnsDeployment;

    private MigratedCustomer $migratedCustomer;

    private Product $freeDnsProduct;

    private Product $extensionProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        CarbonImmutable::setTestNow('2023-12-11 12:00:00');

        $this->customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $this->extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $this->extensionSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->extensionProduct)
            ->forDomain(self::TEST_DOMAIN)
            ->technicalStatusDomainActive()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();

        $this->migratedCustomer = MigratedCustomersFactory::new()->createOne();
        $this->migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $this->migratedCustomer->customers()->attach($this->customer);

        $this->extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $this->extensionSubscription->save();

        $domainContact = DomainContactFactory::new()->for($this->customer)->createOne();

        DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->extensionSubscription->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        DnsNameserver::truncate(); // Because we seed in IntegrationTestCase
        DnsRegion::truncate(); // Because we seed in IntegrationTestCase

        $dnsRegion1 = DnsRegionFactory::new()->createOne();
        $dnsRegion2 = DnsRegionFactory::new()->createOne();
        $dnsRegion3 = DnsRegionFactory::new()->createOne();
        $dnsRegion4 = DnsRegionFactory::new()->createOne();

        $this->ns1 = DnsNameserverFactory::new()->for($dnsRegion1)->createOne([
            'nameserver' => 'test-ns1.sandwave.testing',
        ]);
        $this->ns2 = DnsNameserverFactory::new()->for($dnsRegion2)->createOne([
            'nameserver' => 'test-ns2.sandwave.testing',
        ]);
        $this->ns3 = DnsNameserverFactory::new()->for($dnsRegion3)->createOne([
            'nameserver' => 'test-ns3.sandwave.testing',
        ]);
        $this->ns4 = DnsNameserverFactory::new()->for($dnsRegion4)->createOne([
            'nameserver' => 'test-ns4.sandwave.testing',
        ]);

        $this->freeDnsProduct = ProductFactory::new()->for(ProductGroupFactory::new()->dns())->freeDns()->createOne();

        $dnsSubscription = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($this->freeDnsProduct)
            ->parentSubscription($this->extensionSubscription)
            ->createOne();

        $this->dnsDeployment = DnsDeploymentFactory::new()->for($dnsSubscription)->createOne();
    }

    #[Test]
    public function migrateNameserversMixedValidAndInvalidSubscriptions(): void
    {
        $extensionSubscriptionOp = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->extensionProduct)
            ->forDomain(self::TEST_DOMAIN_OP)
            ->technicalStatusDomainActive()
            ->createOne();

        $domainContact = DomainContactFactory::new()->for($this->customer)->createOne();
        DomainDeploymentFactory::new()->withOpenProvider()->createOne([
            'subscription_uuid' => $extensionSubscriptionOp->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        $migratedSubscriptionOp = MigratedSubscriptionsFactory::new()->createOne();

        // Openprovider subscription
        $dnsSubscriptionOp = SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN_OP)
            ->for($this->customer)
            ->for($this->freeDnsProduct)
            ->parentSubscription($extensionSubscriptionOp)
            ->createOne();

        DnsDeploymentFactory::new()->for($dnsSubscriptionOp)->createOne();

        $domainContactOp = DomainContactFactory::new()->for($this->customer)->createOne();

        $this->migratedCustomer->migratedSubscriptions()->attach($migratedSubscriptionOp);
        $extensionSubscriptionOp->migratedSubscriptions()->attach($migratedSubscriptionOp);
        $extensionSubscriptionOp->save();

        $invalidSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->extensionSubscription->product)
            ->forDomain('invalid-' . self::TEST_DOMAIN)
            ->administrativeStatusInactive()
            ->technicalStatusDomainActive()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $invalidSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $invalidSubscription->save();

        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;
        $domainDetailsResponse['ns'] = [
            'ns1.testing.test',
            'nameserver1337.testing.test',
        ];

        $rtrRequests = [];

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasModernInternalNameservers
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasLegacyInternalNameservers
                new Response(200, []),
            ],
            static function (RequestInterface $request) use (&$rtrRequests): void {
                $rtrRequests[] = $request;
            },
        );

        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses(
            [
                // get DNS zone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBodyWithNsRecords(self::TEST_DOMAIN),
                ),
                // get DNS zone again, see PowerDnsClient::changeZone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBodyWithNsRecords(self::TEST_DOMAIN),
                ),
                // patch DNS zone
                new Response(204),
            ],
            static function (RequestInterface $request) use (&$pdnsRequests): void {
                $pdnsRequests[] = $request;
            },
        );

        $this->pdns($pdnsMock);

        $this->app->forgetInstance('domain-service-realtime_register');

        $dnsNameserverRetrieverMock = self::createMock(DnsNameserverRetriever::class);
        $dnsNameserverRetrieverMock
            ->expects(self::once())
            ->method('retrieve')
            ->willReturn(new Collection([
                $this->ns1,
                $this->ns2,
                $this->ns3,
                $this->ns4,
            ]));

        $this->app->bind(DnsNameserverRetriever::class, fn () => $dnsNameserverRetrieverMock);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_nameservers', [
                    'customer' => $this->customer->id,
                ]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ],
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' =>
                            'Nameserver migration step not allowed for subscription: '
                                . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $invalidSubscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to configure nameservers for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [
                                $this->extensionSubscription->id,
                                $extensionSubscriptionOp->id,
                            ]),
                        ],
                    ],
                ],
            ]);

        // We already test the action in a different test, so we're just checking if it's being called for this domain.
        self::assertCount(2, $pdnsRequests, 'A subscription probably got skipped in the migration');

        $pdnsPatchRequest = $pdnsRequests[1];
        /** @var array<mixed> $pdnsPatchRequestBody */
        $pdnsPatchRequestBody = json_decode(
            $pdnsPatchRequest->getBody()->getContents(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('PATCH', $pdnsPatchRequest->getMethod());
        self::assertSame('api/v1/servers/localhost/zones/' . self::TEST_DOMAIN, $pdnsPatchRequest->getUri()->getPath());

        // PDNS update call
        self::assertSame(
            [
                'rrsets' => [
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'NS',
                        'ttl' => 3600,
                        'changetype' => 'REPLACE',
                        'records' => [
                            [
                                'content' => 'test-ns1.sandwave.testing.',
                                'disabled' => false,
                            ],
                            [
                                'content' => 'test-ns2.sandwave.testing.',
                                'disabled' => false,
                            ],
                            [
                                'content' => 'test-ns3.sandwave.testing.',
                                'disabled' => false,
                            ],
                            [
                                'content' => 'test-ns4.sandwave.testing.',
                                'disabled' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'SOA',
                        'ttl' => 3600,
                        'changetype' => 'REPLACE',
                        'records' => [
                            [
                                'content' => 'test-ns1.sandwave.testing. hostmaster.test-domain.testing. 2023121101 10800 3600 604800 3600',
                                'disabled' => false,
                            ],
                        ],
                    ],
                ],
            ],
            $pdnsPatchRequestBody,
        );

        // RTR requests tests
        self::assertCount(3, $rtrRequests, 'A subscription probably got skipped in the migration');
        self::assertSame('POST', $rtrRequests[2]->getMethod());
        self::assertSame('v2/domains/' . self::TEST_DOMAIN . '/update', $rtrRequests[2]->getUri()->getPath());

        $this->extensionSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->extensionSubscription->administrative_status);
        self::assertSame(DomainStatus::ACTIVE->value, $this->extensionSubscription->technical_status);

        $this->dnsDeployment->refresh();

        self::assertSame(NameserverType::INTERNAL, $this->dnsDeployment->nameserver_type);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function migrateWhitelabelNameservers(): void
    {
        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;
        $domainDetailsResponse['ns'] = [
            'ns1.whitelabel.test',
            'nameserver1337.whitelabel.test',
        ];

        $rtrRequests = [];

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses(
            [
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasModernInternalNameservers
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // hasLegacyInternalNameservers
                new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)), // whitelabel nameserver fetch
                new Response(200, []), // modify domain nameservers
            ],
            static function (RequestInterface $request) use (&$rtrRequests): void {
                $rtrRequests[] = $request;
            },
        );

        $this->app->instance(RealtimeRegister::class, $rtrSdk);

        $nameserverResolver = self::createMock(NameserverResolver::class);

        $nameserverResolver
            ->expects(self::exactly(2))
            ->method('getNameserverIPs')
            ->willReturnCallback(
                fn (string $hostname): array => match ($hostname) {
                    'ns1.whitelabel.test' => ['1.2.3.4'],
                    'nameserver1337.whitelabel.test' => ['5.6.7.8'],
                    default => throw new UnexpectedValueException(),
                },
            );

        $nameserverResolver
            ->expects(self::exactly(2))
            ->method('getNameserverHostname')
            ->willReturnCallback(
                fn (string $ip): string => match ($ip) {
                    '1.2.3.4' => 'ns1.testing.test',
                    '5.6.7.8' => 'nameserver1337.testing.test',
                    default => throw new UnexpectedValueException(),
                },
            );

        $this->app->bind(NameserverResolver::class, fn () => $nameserverResolver);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses(
            [
                // get DNS zone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBodyWithNsRecords(self::TEST_DOMAIN),
                ),
                // get DNS zone again, see PowerDnsClient::changeZone
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBodyWithNsRecords(self::TEST_DOMAIN),
                ),
                // patch DNS zone
                new Response(204),
            ],
            static function (RequestInterface $request) use (&$pdnsRequests): void {
                $pdnsRequests[] = $request;
            },
        );

        $this->pdns($pdnsMock);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_nameservers', [
                    'customer' => $this->customer->id,
                ]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ],
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to configure nameservers for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [$this->extensionSubscription->id]),
                        ],
                    ],
                ],
            ]);

        self::assertCount(2, $pdnsRequests);

        // PDNS update call
        $pdnsPatchRequest = $pdnsRequests[1];

        self::assertSame('PATCH', $pdnsPatchRequest->getMethod());
        self::assertSame('api/v1/servers/localhost/zones/' . self::TEST_DOMAIN, $pdnsPatchRequest->getUri()->getPath());
        self::assertSame(
            [
                'rrsets' => [
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'NS',
                        'ttl' => 3600,
                        'changetype' => 'REPLACE',
                        'records' => [
                            [
                                'content' => 'test-ns1.sandwave.testing.',
                                'disabled' => false,
                            ],
                            [
                                'content' => 'test-ns2.sandwave.testing.',
                                'disabled' => false,
                            ],
                            [
                                'content' => 'test-ns3.sandwave.testing.',
                                'disabled' => false,
                            ],
                        ],
                    ],
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'SOA',
                        'ttl' => 3600,
                        'changetype' => 'REPLACE',
                        'records' => [
                            [
                                'content' => 'test-ns1.sandwave.testing. hostmaster.test-domain.testing. 2023121101 10800 3600 604800 3600',
                                'disabled' => false,
                            ],
                        ],
                    ],
                ],
            ],
            json_decode($pdnsPatchRequest->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR),
        );

        // RTR update call
        self::assertCount(4, $rtrRequests);
        self::assertSame('POST', $rtrRequests[3]->getMethod());
        self::assertSame('v2/domains/' . self::TEST_DOMAIN . '/update', $rtrRequests[3]->getUri()->getPath());

        $this->dnsDeployment->refresh();

        self::assertSame(NameserverType::INTERNAL, $this->dnsDeployment->nameserver_type);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function migrateNameserversOnlyInvalidSubscriptions(): void
    {
        $this->extensionSubscription->update([
            'administrative_status' => AdministrativeStatus::INACTIVE->value,
        ]);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_nameservers', [
                    'customer' => $this->customer->id,
                ]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ],
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' =>
                            'Nameserver migration step not allowed for subscription: '
                                . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->extensionSubscription->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [],
            ]);
    }
}
