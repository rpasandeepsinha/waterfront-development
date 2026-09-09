<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use RealtimeRegister\RealtimeRegister;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DnsNameserverFactory;
use Tests\Factories\DnsRegionFactory;
use Tests\Factories\DnsTemplateFactory;
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
use Waterfront\Apps\API\Ferry\Controllers\ConfigureDnsController;
use Waterfront\Apps\API\Ferry\Enum\ProductNotAllowedToMigrate;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSet;
use Waterfront\Domain\DNS\Services\DnsLogService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;

#[CoversClass(ConfigureDnsController::class)]
class ConfigureDnsControllerTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DOMAIN = 'test-domain.testing';

    private Customer $customer;

    private Subscription $extensionSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        CarbonImmutable::setTestNow('2023-12-11 12:00:00');

        $this->customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $this->extensionSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($extensionProduct)
            ->technicalStatusDomainActive()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $this->extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($this->customer);

        $this->extensionSubscription->save();

        $dnsRegion = DnsRegionFactory::new()->createOne();

        DnsNameserverFactory::new()->for($dnsRegion)->count(3)->createOne();

        $mockDnsLogService = self::createStub(DnsLogService::class);
        $this->app->bind(DnsLogService::class, fn (): DnsLogService => $mockDnsLogService);
    }

    #[Test]
    public function configureDnsWithMixedValidAndInvalidSubscriptions(): void
    {
        $dnsProductGroup = ProductGroupFactory::new()->dns()->createOne();

        $dnsProduct = ProductFactory::new()->for($dnsProductGroup)->createOne();
        $freeDnsProduct = ProductFactory::new()->for($dnsProductGroup)->createOne(['slug' => ProductNotAllowedToMigrate::FREE_DNS->value]);

        $domainContact = DomainContactFactory::new()->for($this->customer)->createOne([
            'first_name' => 'test',
            'last_name' => 'lastname',
            'default_owner' => true,
        ]);

        DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->extensionSubscription->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        $dnsSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($dnsProduct)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $invalidSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($dnsProduct)
            ->administrativeStatusInactive()
            ->technicalStatusOk()
            ->createOne();

        $freeDnsSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($freeDnsProduct)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $dnsSubscription->migratedSubscriptions()->attach($migratedSubscription);

        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne();
        $invalidSubscription->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription);

        $dnsSubscription->save();

        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;
        $domainDetailsResponse['ns'] = [
            'ns1.testing.test',
            'ns2.testing.test',
        ];

        $rtrRequests = [];

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)),
        ], static function (RequestInterface $request) use (&$rtrRequests): void {
            $rtrRequests[] = $request;
        });

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithResponseCallback([
            'dns.sandwaveio.test/api/v1/servers/localhost/zones/' . self::TEST_DOMAIN =>
                function (RequestInterface $request) {
                    if ($request->getMethod() === 'GET') {
                        return new Response(status: 200, body: $this->getMockedZoneResponseBody(self::TEST_DOMAIN));
                    }

                    if ($request->getMethod() === 'PUT') {
                        return new Response(status: 204);
                    }

                    if ($request->getMethod() === 'PATCH') {
                        return new Response(status: 204);
                    }

                    self::fail('Unknown request');
                },
            'dns.sandwaveio.test/presigned/' . self::TEST_DOMAIN =>
                function (RequestInterface $request) {
                    self::assertSame('GET', $request->getMethod());

                    return new Response(
                        200,
                        [],
                        '{"result": 1}'
                    );
                },
        ], static function (RequestInterface $request) use (&$pdnsRequests): void {
            $pdnsRequests[] = $request;
        });

        $this->pdns($pdnsMock);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.configure_dns', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Configure DNS migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
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
                        'message' => 'Created jobs to configure DNS zone for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [$this->extensionSubscription->id, $dnsSubscription->id]),
                        ],
                    ],
                ],
            ]);

        self::assertCount(12, $pdnsRequests, 'A subscription probably got skipped in the migration');

        self::assertSame('PUT', $pdnsRequests[2]->getMethod());
        self::assertSame('/api/v1/servers/localhost/zones/test-domain.testing', $pdnsRequests[2]->getUri()->getPath());

        /** @var array<string> $mastersDomainPayload */
        $mastersDomainPayload = json_decode($pdnsRequests[2]->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'kind' => PowerDnsZoneKind::MASTER->value,
                'last_check' => 0,
                'masters' => [],
            ],
            $mastersDomainPayload,
            'PDNS zone to master failed'
        );

        self::assertSame('PATCH', $pdnsRequests[4]->getMethod());

        $removeRRsetsPayload = json_decode($pdnsRequests[4]->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($removeRRsetsPayload);

        self::assertSame(
            [
                'rrsets' => [
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'RRSIG',
                        'changetype' => 'DELETE',
                    ],
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'DNSKEY',
                        'changetype' => 'DELETE',
                    ],
                    [
                        'name' => 'test-domain.testing.',
                        'type' => 'SOA',
                        'ttl' => 3600,
                        'changetype' => 'REPLACE',
                        'records' => [
                            [
                                'content' => 'a.misconfigured.powerdns.server. hostmaster.test-domain.testing. 2023121101 10800 3600 604800 3600',
                                'disabled' => false,
                            ],
                        ],
                    ],
                ],
            ],
            $removeRRsetsPayload,
        );

        self::assertSame('/api/v1/servers/localhost/zones/' . self::TEST_DOMAIN, $pdnsRequests[8]->getUri()->getPath());

        self::assertSame('GET', $pdnsRequests[5]->getMethod());
        self::assertSame('/presigned/' . self::TEST_DOMAIN, $pdnsRequests[5]->getUri()->getPath());

        self::assertSame(DomainStatus::ACTIVE->value, $this->extensionSubscription->refresh()->technical_status);

        /*
         * DNS subscription requests
         */
        self::assertSame('PUT', $pdnsRequests[8]->getMethod());
        self::assertSame('/api/v1/servers/localhost/zones/' . self::TEST_DOMAIN, $pdnsRequests[8]->getUri()->getPath());

        /** @var array<string> $mastersDnsPayload */
        $mastersDnsPayload = json_decode($pdnsRequests[8]->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            [
                'kind' => PowerDnsZoneKind::MASTER->value,
                'last_check' => 0,
                'masters' => [],
            ],
            $mastersDnsPayload,
            'PDNS zone to master failed'
        );

        self::assertSame('GET', $pdnsRequests[9]->getMethod());
        self::assertSame('/api/v1/servers/localhost/zones/' . self::TEST_DOMAIN, $pdnsRequests[9]->getUri()->getPath());

        self::assertSame(TechnicalStatus::OK->value, $dnsSubscription->refresh()->technical_status);
        self::assertSame(TechnicalStatus::OK->value, $freeDnsSubscription->refresh()->technical_status);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function configureDnsSuccessfullyWhenDnsZoneDoesNotExist(): void
    {
        $domainContact = DomainContactFactory::new()->for($this->customer)->createOne([
            'first_name' => 'test',
            'last_name' => 'lastname',
            'default_owner' => true,
        ]);

        DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->extensionSubscription->uuid,
            'contact_owner_id' => $domainContact->id,
        ]);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            // get DNS zone
            new Response(
                404
            ),
            // create DNS zone
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(self::TEST_DOMAIN)
            ),
        ], static function (RequestInterface $request) use (&$pdnsRequests): void {
            $pdnsRequests[] = $request;
        });

        $this->pdns($pdnsMock);

        $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.configure_dns', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to configure DNS zone for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [$this->extensionSubscription->id]),
                        ],
                    ],
                ],
            ]);

        self::assertCount(2, $pdnsRequests, 'A subscription probably got skipped in the migration');
        self::assertSame('GET', $pdnsRequests[0]->getMethod());
        self::assertSame('POST', $pdnsRequests[1]->getMethod());
    }

    #[Test]
    public function configureDnsSuccessfullyWhenDnsZoneEmpty(): void
    {
        DomainDeploymentFactory::new()
            ->for($this->extensionSubscription)
            ->withRtrProvider()
            ->createOne();

        $this->createDefaultDNSTemplate();

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            // getDnsZone
            new Response(
                200,
                [],
                self::getMockedZoneResponseBodyWithRrsets(self::TEST_DOMAIN)
            ),
            // getDnsZone (changeToMasterAndEmptyMasters)
            new Response(
                200,
                [],
                self::getMockedZoneResponseBodyWithRrsets(self::TEST_DOMAIN)
            ),
            // changeToMasterAndEmptyMasters
            new Response(
                204,
            ),
            // getDnsZone (applyDiffToZone)
            new Response(
                200,
                [],
                self::getMockedZoneResponseBodyWithRrsets(self::TEST_DOMAIN)
            ),
            // patch zone records with new NS/SOA
            new Response(
                204,
            ),
            // getDnsZone (disable presigned)
            new Response(
                200,
                [],
                self::getMockedZoneResponseBodyWithNsRecords(self::TEST_DOMAIN)
            ),
            // patch zone records cleanup records
            new Response(
                204,
            ),
            // presigned
            new Response(
                200,
                [],
                '{"result": 1}'
            ),
        ], static function (RequestInterface $request) use (&$pdnsRequests): void {
            $pdnsRequests[] = $request;
        });

        $this->pdns($pdnsMock);

        $this
            ->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.configure_dns', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to configure DNS zone for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [$this->extensionSubscription->id]),
                        ],
                    ],
                ],
            ]);

        self::assertCount(8, $pdnsRequests, 'Different expected number of calls');

        self::assertSame('PATCH', $pdnsRequests[4]->getMethod());

        // Check the NS/SOA records added
        /** @var array<string, array<int, array<string, string>>> $patchBody */
        $patchBody = json_decode(
            json: $pdnsRequests[4]->getBody()->getContents(),
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
        self::assertSame('NS', $patchBody['rrsets'][0]['type']);
        self::assertSame('REPLACE', $patchBody['rrsets'][0]['changetype']);
        self::assertSame('SOA', $patchBody['rrsets'][1]['type']);
        self::assertSame('REPLACE', $patchBody['rrsets'][1]['changetype']);
    }

    #[Test]
    public function migrateDnsOnlyInvalidSubscriptions(): void
    {
        $this->extensionSubscription->update([
            'administrative_status' => AdministrativeStatus::INACTIVE->value,
        ]);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.configure_dns', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Configure DNS migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
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

    private function createDefaultDNSTemplate(): void
    {
        $defaultDnsTemplate = DnsTemplateFactory::new()->createOne(['slug' => 'default']);

        $nsRecordSet = new DnsTemplateRecordSet();
        $nsRecordSet->name = '{domain}';
        $nsRecordSet->type = 'NS';
        $nsRecordSet->ttl = 3600;
        $nsRecordSet->template_id = $defaultDnsTemplate->id;
        $nsRecordSet->save();

        $nsRecordSet
            ->rows()
            ->createMany([
                ['content' => '{ns1}.'],
                ['content' => '{ns2}.'],
                ['content' => '{ns3}.'],
            ]);

        $soaRecordSet = new DnsTemplateRecordSet();
        $soaRecordSet->name = '{domain}';
        $soaRecordSet->type = 'SOA';
        $soaRecordSet->ttl = 3600;
        $soaRecordSet->template_id = $defaultDnsTemplate->id;
        $soaRecordSet->save();

        $soaRecordSet
            ->rows()
            ->create([
                'content' => '{ns1}. domain-admin.sandwave.testing. 1539941638 3600 600 86400 3600',
            ]);
    }
}
