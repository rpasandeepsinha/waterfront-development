<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MigratedCustomersFactory;
use Tests\Factories\MigratedSubscriptionsFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SearchController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(SearchController::class)]
class SearchMigrationReferenceTest extends IntegrationTestCase
{
    private const string REFERENCE_CUSTOMER_NUMBER = '9876543';

    private const string REFERENCE_SUBSCRIPTION_ID = 'sub_1337_1';

    private Customer $customer;

    private Product $product;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $this->customer = new CustomerFactory()->createOne();

        new MigratedCustomersFactory()->createOne([
            'reference_customer_number' => self::REFERENCE_CUSTOMER_NUMBER,
        ])->customers()->attach($this->customer);

        $this->product = new ProductFactory()->hostingBrons()->createOne();

        $this->subscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOne([
            'domain' => 'example.dev',
        ]);

        $this->subscription->migratedSubscriptions()->attach(
            new MigratedSubscriptionsFactory()->createOne([
                'reference_subscription_id' => self::REFERENCE_SUBSCRIPTION_ID,
            ])
        );
    }

    /** @return iterable<string, array<string>> */
    public static function provideMatchingCustomerReferences(): iterable
    {
        yield 'Finds customer by exact migration reference' => [self::REFERENCE_CUSTOMER_NUMBER];
        yield 'Finds customer by leading part of the migration reference' => ['9876'];
        yield 'Finds customer by trailing part of the migration reference' => ['6543'];
        yield 'Finds customer by middle part of the migration reference' => ['8765'];
    }

    #[DataProvider('provideMatchingCustomerReferences')]
    #[Test]
    public function searchOnMigrationReferenceReturnsLinkedCustomer(string $searchterm): void
    {
        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => $searchterm]));

        $response->assertOk();
        $response->assertExactJson([$this->expectedCustomerResult()]);
    }

    /** @return iterable<string, array<string>> */
    public static function provideMatchingSubscriptionReferences(): iterable
    {
        yield 'Finds subscription by exact migration reference' => [self::REFERENCE_SUBSCRIPTION_ID];
        yield 'Finds subscription by leading part of the migration reference' => ['sub_1337'];
        yield 'Finds subscription by trailing part of the migration reference' => ['1337_1'];
        yield 'Finds subscription by middle part of the migration reference' => ['b_1337_'];
    }

    #[DataProvider('provideMatchingSubscriptionReferences')]
    #[Test]
    public function searchOnMigrationReferenceReturnsLinkedSubscription(string $searchterm): void
    {
        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => $searchterm]));

        $response->assertOk();
        $response->assertExactJson([$this->expectedSubscriptionResult()]);
    }

    #[Test]
    public function searchReturnsBothCustomersAndSubscriptionsWhenReferencesOverlap(): void
    {
        $sharedReference = 'shared_reference_42';

        new MigratedCustomersFactory()->createOne([
            'reference_customer_number' => $sharedReference,
        ])->customers()->attach($this->customer);

        $this->subscription->migratedSubscriptions()->attach(
            new MigratedSubscriptionsFactory()->createOne([
                'reference_subscription_id' => $sharedReference,
            ])
        );

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => $sharedReference]));

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['type' => 'customer', 'customer_number' => $this->customer->customer_number]);
        $response->assertJsonFragment(['type' => 'subscription', 'subscription_id' => $this->subscription->id]);
    }

    #[Test]
    public function searchReturnsSubscriptionWithoutDomain(): void
    {
        $hostingSubscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOne([
            'domain' => null,
        ]);

        $hostingSubscription->migratedSubscriptions()->attach(
            new MigratedSubscriptionsFactory()->createOne([
                'reference_subscription_id' => 'hosting_reference_7',
            ])
        );

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => 'hosting_reference_7']));

        $response->assertOk();
        $response->assertExactJson([
            [
                'type' => 'subscription',
                'domain' => null,
                'technical_status' => $hostingSubscription->technical_status,
                'customer_number' => $this->customer->customer_number,
                'customer_name' => $this->customer->contact_name,
                'subscription_id' => $hostingSubscription->id,
                'reference_subscription_id' => 'hosting_reference_7',
            ],
        ]);
    }

    #[Test]
    public function searchOnUnknownMigrationReferenceReturnsNoResults(): void
    {
        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => 'no_such_reference']));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    #[Test]
    public function searchDoesNotReturnCustomersWithoutMigratedCustomer(): void
    {
        $unmigratedCustomer = new CustomerFactory()->createOne();

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => self::REFERENCE_CUSTOMER_NUMBER]));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonMissing(['customer_number' => $unmigratedCustomer->customer_number]);
    }

    #[Test]
    public function searchDoesNotReturnSubscriptionsWithoutMigratedSubscription(): void
    {
        $unmigratedSubscription = new SubscriptionFactory()->for($this->customer)->for($this->product)->createOne();

        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references', ['searchterm' => self::REFERENCE_SUBSCRIPTION_ID]));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonMissing(['subscription_id' => $unmigratedSubscription->id]);
    }

    #[Test]
    public function searchWithoutSearchtermIsRejected(): void
    {
        $response = $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.search.migration-references'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('searchterm');
    }

    /** @return array<string, int|string|null> */
    private function expectedCustomerResult(): array
    {
        return [
            'type' => 'customer',
            'customer_number' => $this->customer->customer_number,
            'first_name' => $this->customer->first_name,
            'last_name' => $this->customer->last_name,
            'organization' => $this->customer->organization,
        ];
    }

    /** @return array<string, int|string|null> */
    private function expectedSubscriptionResult(): array
    {
        return [
            'type' => 'subscription',
            'domain' => $this->subscription->domain,
            'technical_status' => $this->subscription->technical_status,
            'customer_number' => $this->customer->customer_number,
            'customer_name' => $this->customer->contact_name,
            'subscription_id' => $this->subscription->id,
            'reference_subscription_id' => self::REFERENCE_SUBSCRIPTION_ID,
        ];
    }
}
