<?php

declare(strict_types=1);

namespace Tests\Apps\API\Ferry\Controller;

use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Ferry\Controllers\RedirectMigrationController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Results\RedirectResult;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(RedirectMigrationController::class)]
class RedirectMigrationControllerTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    private const string TEST_DOMAIN = 'test-domain.nl';

    private Customer $customer;

    private Subscription $redirectSubscription;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2023-12-11 12:00:00');

        $this->customer = CustomerFactory::new()->createOne();

        $redirectProduct = ProductFactory::new()
            ->freeRedirect()
            ->createOne();

        $this->redirectSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($redirectProduct)
            ->forDomain(self::TEST_DOMAIN)
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $migrationCustomer = MigratedCustomersFactory::new()->createOne();
        $migrationCustomer->migratedSubscriptions()->attach($migratedSubscription);
        $migrationCustomer->customers()->attach($this->customer);

        $this->redirectSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $this->redirectSubscription->save();

        LegacyRedirectingServerFactory::new()->createOne([
            'hostname' => 'legacy-redirects-server.test',
            'ipv4' => '1.2.3.4',
            'ipv6' => '::2',
        ]);
    }

    #[Test]
    public function migrateRedirectsMixedValidAndInvalidSubscriptions(): void
    {
        Http::fake();

        $pdnsMock = $this->makePdnsWithMultipleResponses([
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBodyForRedirects(
                    domain: self::TEST_DOMAIN,
                    redirectNameForARrset: 'subdomain.' . self::TEST_DOMAIN,
                    redirectContentForARrset: '1.2.3.4',
                    redirectNameForAAAARrset: 'subdomain.' . self::TEST_DOMAIN,
                    redirectContentForAAAARrset: '::2',
                )
            ),
            // A record
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(
                    domain: self::TEST_DOMAIN,
                )
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(
                    domain: self::TEST_DOMAIN,
                )
            ),
            new Response(
                207,
                []
            ),
            // AAAA record
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(
                    domain: self::TEST_DOMAIN,
                )
            ),
            new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(
                    domain: self::TEST_DOMAIN,
                )
            ),
            new Response(
                207,
                []
            ),
        ]);

        $this->pdns($pdnsMock);

        $redirectsService = self::createStub(RedirectService::class);
        $redirectsService->method('listRedirects')->willReturn([]);
        $redirectsService->method('createRedirect')->willReturn(
            new RedirectResult(
                provisionData: self::createStub(ProvisionRequestInterface::class),
                provisionStatus: ProvisionStatus::FAILED,
            )
        );

        $this->app->bind(
            RedirectService::class,
            fn (): RedirectService => $redirectsService
        );

        $product = ProductFactory::new()
            ->for(ProductGroupFactory::new()->dns())
            ->freeDns();
        SubscriptionFactory::new()
            ->administrativeStatusActive()
            ->forDomain(self::TEST_DOMAIN)
            ->for(CustomerFactory::new())
            ->for($product)
            ->createOne();

        $invalidSubscription = SubscriptionFactory::new()
            ->for($this->customer)
            ->for($this->redirectSubscription->product)
            ->forDomain('invalid-' . self::TEST_DOMAIN)
            ->administrativeStatusInactive()
            ->technicalStatusOk()
            ->createOne();

        $migratedSubscription = MigratedSubscriptionsFactory::new()->createOne();
        $invalidSubscription->migratedSubscriptions()->attach($migratedSubscription);
        $invalidSubscription->save();

        $postData = include __DIR__ . '/data/redirect_migration.php';

        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_redirects', ['customer' => $this->customer->id]),
                $postData,
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)
            ->assertExactJson([
                'failures' => [
                    [
                        'message' => 'Redirect migration step not allowed for subscription: ' . NotEligibleForMigrationException::administrativeStatusIncorrect(AdministrativeStatus::INACTIVE->value)->getMessage(),
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
                        'message' => 'Created jobs to migrate redirects for every eligible subscription',
                        'parameters' => [
                            'customerId' => $this->customer->id,
                            'subscriptionIds' => implode(',', [$this->redirectSubscription->id]),
                        ],
                    ],
                ],
            ]);

        $this->redirectSubscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->redirectSubscription->administrative_status);
        self::assertSame(TechnicalStatus::OK->value, $this->redirectSubscription->technical_status);

        self::assertSame([], Invoice::all()->toArray(), 'Technical migration should not create any invoices');
    }

    #[Test]
    public function invalidDomainInSource(): void
    {
        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_redirects', ['customer' => $this->customer->id]),
                [
                    [
                        'source' => 'test123',
                        'destination' => 'https://google.com',
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'message' => 'Dit veld bevat geen geldige domeinnaam. (and 1 more error)',
                'errors' => [
                    '0.source' => [
                        'Dit veld bevat geen geldige domeinnaam.',
                        'The source field with value: test123 has no representation as a redirect subscription domain: ',
                    ],
                ],
            ]);
    }

    #[Test]
    public function mismatchingSourceWithAdministrativelyMigratedSubscription(): void
    {
        $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_redirects', ['customer' => $this->customer->id]),
                [
                    [
                        'source' => 'mismatching-domain.nl',
                        'destination' => 'https://google.com',
                        'type' => RedirectType::TEMPORARY->value,
                    ],
                ],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            )
            ->assertStatus(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'message' => 'The source field with value: mismatching-domain.nl has no representation as a redirect subscription domain: mismatching-domain.nl',
                'errors' => [
                    '0.source' => [
                        'The source field with value: mismatching-domain.nl has no representation as a redirect subscription domain: mismatching-domain.nl',
                    ],
                ],
            ]);
    }

    #[Test]
    public function withNoRedirectData(): void
    {
        // mapSubscriptionsWithRedirects Will return a empty array so the job in reality will not get triggered with a run
        // So in essence administrative only redirects won't block migrations
        Queue::fake();

        $responseNoData = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_redirects', ['customer' => $this->customer->id]),
                [],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $responseNoData->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)->assertExactJson([
             'failures' => [],
             'success' => [
                 [
                     'message' => 'Created jobs to migrate redirects for every eligible subscription',
                     'baseParameters' => [],
                     'parameters' => [
                         'customerId' => $this->customer->id,
                         'subscriptionIds' => (string) $this->redirectSubscription->id,
                     ],
                 ],
             ],
         ]);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function withEmptyRedirectData(): void
    {
        // mapSubscriptionsWithRedirects Will return a empty array so the job in reality will not get triggered with a run
        // So in essence administrative only redirects won't block migrations
        Queue::fake();

        $responseWithEmptyData = $this->actingAsSystem()
            ->postJson(
                $this->generateRoute('ferry.customers.subscriptions.migrate_redirects', ['customer' => $this->customer->id]),
                [[]],
                [
                    'Authorization' => 'Bearer ferry_testing_api_key',
                ]
            );

        $responseWithEmptyData->assertStatus(SymfonyResponse::HTTP_MULTI_STATUS)->assertExactJson([
            'failures' => [],
            'success' => [
                [
                    'message' => 'Created jobs to migrate redirects for every eligible subscription',
                    'baseParameters' => [],
                    'parameters' => [
                        'customerId' => $this->customer->id,
                        'subscriptionIds' => (string) $this->redirectSubscription->id,
                    ],
                ],
            ],
        ]);

        Queue::assertNothingPushed();
    }
}
