<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

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
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\EnableDnsSecController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(EnableDnsSecController::class)]
class EnableDnsSecControllerTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DOMAIN = 'test-domain.testing';
    private const string TEST_DOMAIN2 = 'test-domain2.testing';

    private Customer $customer;

    private Subscription $extensionSubscription;

    private Subscription $extensionSubscription2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = CustomerFactory::new()->createOne();

        $extensionProductGroup = ProductGroupFactory::new()->extension()->createOne();

        $extensionProduct = ProductFactory::new()->for($extensionProductGroup)->createOne();

        $this->extensionSubscription = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN)
            ->for($this->customer)
            ->for($extensionProduct)
            ->technicalStatusDomainActive()
            ->createOne();

        $this->extensionSubscription2 = SubscriptionFactory::new()
            ->forDomain(self::TEST_DOMAIN2)
            ->for($this->customer)
            ->for($extensionProduct)
            ->technicalStatus(DomainStatus::DELETED->value)
            ->createOne();

        DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->extensionSubscription->uuid,
        ]);
        DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->extensionSubscription2->uuid,
        ]);

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $migratedSubscription2 = MigratedSubscriptionsFactory::new()->createOne();

        $this->extensionSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $this->extensionSubscription2->migratedSubscriptions()->attach($migratedSubscription2);

        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($this->customer);

        $migrationCustomer2 = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer2->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer2->customers()->attach($this->customer);

        $this->extensionSubscription->save();
        $this->extensionSubscription2->save();

        $dnsRegion = DnsRegionFactory::new()->createOne();

        DnsNameserverFactory::new()->for($dnsRegion)->createOne([
            'nameserver' => 'ns1.sandwave-test.com',
        ]);
        DnsNameserverFactory::new()->for($dnsRegion)->createOne([
            'nameserver' => 'ns02.sandwave-test.com',
        ]);
    }

    #[Test]
    public function enableDnssecWithMixedValidAndInvalidSubscriptions(): void
    {
        Http::fake();

        $domainDetailsResponse = include __DIR__ . '/data/domain_details_valid.php';
        $domainDetailsResponse['domain'] = self::TEST_DOMAIN;

        $domainDetailsResponse['ns'] = [
            'ns1.sandwave-test.com',
            'ns02.sandwave-test.com',
        ];

        $rtrRequests = [];

        $tldInfoValidData = include __DIR__ . '/data/tld_info_data_valid.php';

        $rtrSdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(200, [], json_encode($tldInfoValidData, JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['ok' => true], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($domainDetailsResponse, JSON_THROW_ON_ERROR)),
        ], static function (RequestInterface $request) use (&$rtrRequests): void {
            $rtrRequests[] = $request;
        });

        $this->app->bind(RealtimeRegister::class, static fn () => $rtrSdk);

        $pdnsRequests = [];

        $pdnsMock = $this->makePdnsWithMultipleResponses(
            [
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
                new Response(
                    200,
                    [],
                    $this->getMockedZoneResponseBody(self::TEST_DOMAIN),
                ),
                // key response
                new Response(
                    200,
                    [],
                    $this->getMockedKeyResponseBody(),
                ),
            ],
            static function (RequestInterface $request) use (&$pdnsRequests): void {
                $pdnsRequests[] = $request;
            },
        );

        $this->pdns($pdnsMock);

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.enable_dnssec', [
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
                            'Enable dnssec step not allowed for subscription: '
                                . NotEligibleForMigrationException::technicalStatusIncorrect(DomainStatus::DELETED->value)->getMessage(),
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionId' => $this->extensionSubscription2->id,
                        ],
                        'baseParameters' => [],
                    ],
                ],
                'success' => [
                    [
                        'baseParameters' => [],
                        'message' => 'Created jobs to enable dnssec for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [$this->extensionSubscription->id]),
                        ],
                    ],
                ],
            ]);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }
}
