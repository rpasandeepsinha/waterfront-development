<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\Microsoft\Graph\GraphServiceClient;
use SandwaveIo\Office365\Office\OfficeClient;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\Microsoft365KpnProductFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Microsoft365SyncWatcher;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Exceptions\OrderSummaryException;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;
use Waterfront\Domain\Microsoft365\Models\Microsoft365SyncLog;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365KpnProductRepository;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365Repository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Microsoft365\Services\Microsoft365TenantService;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(Microsoft365SyncWatcher::class)]
class Microsoft365SyncWatcherTest extends IntegrationTestCase
{
    private Product $childProductOne;

    private Product $parentProductOne;

    private Subscription $subscriptionOne;

    private Microsoft365KpnProduct $microsoft365KpnProduct;

    private Microsoft365CustomerInfo $microsoft365CustomerInfo;

    private Microsoft365Deployment $microsoft365DeploymentOne;

    private Microsoft365Deployment $microsoft365DeploymentTwo;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        $customer = new CustomerFactory()->createOne();

        $productGroup = new ProductGroupFactory()->microsoft365()->createOne();

        $this->parentProductOne = new ProductFactory()->for($productGroup)->createOne(['slug' => 'microsoft-business-standard-parent']);
        $this->childProductOne = new ProductFactory()->for($productGroup)->createOne(['slug' => 'microsoft-business-standard']);
        $parentProductTwo = new ProductFactory()->for($productGroup)->createOne(['slug' => 'microsoft-business-basic-parent']);
        $childProductTwo = new ProductFactory()->for($productGroup)->createOne(['slug' => 'microsoft-business-basic']);

        $this->subscriptionOne = new SubscriptionFactory()->withCustomer()->for($this->parentProductOne)->createOne([
            'product_uuid' => $this->parentProductOne->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'end_date' => CarbonImmutable::now()->addMonth(),
            'next_billing_date' => CarbonImmutable::now()->addMonth(),
        ]);

        new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->parentSubscription($this->subscriptionOne)->state([
            'product_uuid' => $this->childProductOne->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'end_date' => $this->subscriptionOne->end_date,
            'next_billing_date' => $this->subscriptionOne->next_billing_date,
        ])->createMany(5);

        $subscriptionTwo = new SubscriptionFactory()->withCustomer()->for($parentProductTwo)->createOne([
            'product_uuid' => $parentProductTwo->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'end_date' => CarbonImmutable::now()->addMonth(),
            'next_billing_date' => CarbonImmutable::now()->addMonth(),
        ]);

        new SubscriptionFactory()->withCustomer()->for($childProductTwo)->parentSubscription($subscriptionTwo)->state([
            'product_uuid' => $childProductTwo->uuid,
            'technical_status' => TechnicalStatus::OK->value,
            'end_date' => $subscriptionTwo->end_date,
            'next_billing_date' => $subscriptionTwo->next_billing_date,
        ])->createMany(3);

        $this->microsoft365KpnProduct = new Microsoft365KpnProductFactory()->for($this->parentProductOne)->createOne(['kpn_product_code' => '120A00179B', 'contract_period' => 12]);
        $this->microsoft365CustomerInfo = new Microsoft365CustomerInfoFactory()->for($customer)->createOne(['kpn_customer_id' => 'CID543598', 'synced_at' => CarbonImmutable::now()->subWeeks(3)]);
        $this->microsoft365DeploymentOne = new Microsoft365DeploymentFactory()->for($this->microsoft365CustomerInfo)->for($this->subscriptionOne)->createOne(['kpn_order_id' => 11118966]);
        $this->microsoft365DeploymentTwo = new Microsoft365DeploymentFactory()->for($this->microsoft365CustomerInfo)->for($subscriptionTwo)->createOne(['kpn_order_id' => 11118967]);
    }

    #[Test]
    public function syncWatcher(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        self::assertNull($this->microsoft365DeploymentOne->kpn_start_date);
        self::assertNull($this->microsoft365DeploymentTwo->kpn_start_date);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->expectsOutput('Watching 1 Microsoft365 customers.')
            ->expectsOutput(sprintf('Microsoft365 customer {%d} has successfully been checked.', $this->microsoft365CustomerInfo->id))
            ->expectsOutput('Finished watching the Microsoft365 subscriptions.')
            ->assertOk();

        $this->microsoft365CustomerInfo->refresh();
        $this->microsoft365DeploymentOne->refresh();
        $this->microsoft365DeploymentTwo->refresh();

        self::assertNotNull($this->microsoft365DeploymentOne->kpn_start_date);
        self::assertNotNull($this->microsoft365DeploymentTwo->kpn_start_date);

        self::assertSame(CarbonImmutable::parse('2022-08-08 11:49:52')->toDateTimeString(), $this->microsoft365DeploymentOne->kpn_start_date->toDateTimeString());
        self::assertSame(CarbonImmutable::parse('2022-08-08 11:53:38')->toDateTimeString(), $this->microsoft365DeploymentTwo->kpn_start_date->toDateTimeString());

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'No problems found on %s.',
                CarbonImmutable::today()->format(DateTimeFormat::DATE),
            ),
            'microsoft365_customer_info_id' => null,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function higherIrmaAmount(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->subscriptionChildren->firstOrFail()->delete();
        $this->microsoft365DeploymentOne->refresh();
        self::assertCount(4, $this->microsoft365DeploymentOne->subscriptionChildren);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'Found {5} seats in Irma and {4} seats in Waterfront with kpn_order_id {11118966} for kpn_customer_id {CID543598}!',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    #[Test]
    public function higherWaterfrontAmount(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->state([
            'product_uuid' => $this->childProductOne->uuid,
            'parent_subscription_id' => $this->subscriptionOne->id,
        ])->createMany(3);
        $this->microsoft365DeploymentOne->refresh();
        self::assertCount(8, $this->microsoft365DeploymentOne->subscriptionChildren);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'Found {5} seats in Irma and {8} seats in Waterfront with kpn_order_id {11118966} for kpn_customer_id {CID543598}!',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    #[Test]
    public function customerTechnicalStatusFailedAndSubscriptionFailed(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365CustomerInfo->technical_status = Microsoft365ProcessStatus::FAILED;
        $this->microsoft365CustomerInfo->save();

        $this->microsoft365DeploymentOne->kpn_status = Microsoft365OrderStatus::FAILED;
        $this->microsoft365DeploymentOne->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf('Microsoft365 customer {%d} has a failed state.', $this->microsoft365CustomerInfo->id),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf('Microsoft365 subscription {%d} has a failed state.', $this->microsoft365DeploymentOne->id),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    #[Test]
    public function customerTechnicalStatusCustomerCreatedWithSubscriptions(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365CustomerInfo->technical_status = Microsoft365ProcessStatus::CUSTOMER_CREATED;
        $this->microsoft365CustomerInfo->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf('Microsoft365 customer {%d} had a {placed} state and now has a {active} state despite having multiple subscriptions in irma.', $this->microsoft365CustomerInfo->id),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    // Ghost subscriptions are subscription with product_group 'microsoft_365' who are not coupled to a Microsoft365 subscription.
    #[Test]
    public function ghostSubscription(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $ghost_subscription_1 = new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->createOne([
            'product_uuid' => $this->childProductOne->uuid,
        ]);

        $ghost_subscription_2 = new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->createOne([
            'product_uuid' => $this->childProductOne->uuid,
        ]);

        $ghost_subscription_3 = new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->createOne([
            'product_uuid' => $this->childProductOne->uuid,
        ]);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'There are {3} ghost subscriptions in the microsoft_365 group with the following id\'s: [{"id":%d},{"id":%d},{"id":%d}]',
                $ghost_subscription_1->id,
                $ghost_subscription_2->id,
                $ghost_subscription_3->id,
            ),
        ]);
    }

    #[Test]
    public function tooManyRequests(): void
    {
        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('orderSummary')
            ->willThrowException(new OrderSummaryException('Too Many Requests'));
        $this->app->bind(Microsoft365Service::class, fn () => $microsoft365Service);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertFailed();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'Microsoft365 sync watcher has reached it\'s too many request limit!',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function kpnOrderIdDoesNotExist(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->kpn_order_id = null;
        $this->microsoft365DeploymentOne->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'Microsoft365 subscription was kpn_order_id {null} and is now updated to {11118966}. Updating subscription with the same product.',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);

        $this->microsoft365DeploymentOne->refresh();
        self::assertSame(11118966, $this->microsoft365DeploymentOne->kpn_order_id);
    }

    #[Test]
    public function noOtherProductFound(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->kpn_order_id = null;
        $this->microsoft365DeploymentOne->save();

        $this->microsoft365KpnProduct->kpn_product_code = 'doesnotexists';
        $this->microsoft365KpnProduct->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'Microsoft365 subscription with orderId {11118966} found in Irma but not in Waterfront. No other subscription found with this product.',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function moreWaterfrontSubscriptions(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $extraSubscription = new SubscriptionFactory()->withCustomer()->for($this->parentProductOne)->createOne([
            'product_uuid' => $this->parentProductOne->uuid,
        ]);

        $deletedSubscription = new SubscriptionFactory()->withCustomer()->for($this->parentProductOne)->administrativeStatusArchived()->createOne([
            'product_uuid' => $this->parentProductOne->uuid,
        ]);

        new Microsoft365DeploymentFactory()->for($this->microsoft365CustomerInfo)->for($extraSubscription)->createOne();
        new Microsoft365DeploymentFactory()->for($this->microsoft365CustomerInfo)->for($deletedSubscription)->createOne();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'There are {3} subscriptions in Waterfront and {2} subscriptions in Irma.',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function yearlyArchivingSubscriptionIsSkippedForIrmaAmountCheck(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        // Yearly (NCE) subscription that was cancelled by the customer. The deployment is still active
        // on our side, but in IRMA the order is in `Terminate` state and therefore not returned in the
        // `Active` order summary. It will be archived automatically through a webhook once the contract
        // period ends, so it must not trigger a Waterfront/IRMA count mismatch.
        $archivingYearlySubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProductOne)
            ->administrativeStatusArchiving()
            ->createOne([
                'product_uuid' => $this->parentProductOne->uuid,
                'contract_period' => 12,
            ]);

        new Microsoft365DeploymentFactory()
            ->for($this->microsoft365CustomerInfo)
            ->for($archivingYearlySubscription)
            ->createOne();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseMissing('microsoft365_sync_log', [
            'log' => 'There are {3} subscriptions in Waterfront and {2} subscriptions in Irma.',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function monthlyArchivingSubscriptionStillTriggersIrmaAmountCheck(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        // Monthly subscriptions terminate immediately at KPN, so an ARCHIVING monthly subscription that
        // is no longer in IRMA's `Active` list is unexpected and should still trigger a mismatch log.
        $archivingMonthlySubscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($this->parentProductOne)
            ->administrativeStatusArchiving()
            ->createOne([
                'product_uuid' => $this->parentProductOne->uuid,
                'contract_period' => 1,
            ]);

        new Microsoft365DeploymentFactory()
            ->for($this->microsoft365CustomerInfo)
            ->for($archivingMonthlySubscription)
            ->createOne();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'There are {3} subscriptions in Waterfront and {2} subscriptions in Irma.',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function moreIrmaSubscriptions(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentTwo->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->microsoft365DeploymentTwo->subscription->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => 'There are {1} subscriptions in Waterfront and {2} subscriptions in Irma.',
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function subscriptionAdministrativeStatusArchived(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->microsoft365DeploymentOne->subscription->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Subscription administrative {%d} has wrong administrative status {archived}. With {%d} seats not deleted.',
                $this->microsoft365DeploymentOne->id,
                $this->microsoft365DeploymentOne->subscription->children->count()
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    #[Test]
    public function administrativeStatusPlacedTechnicalNotOk(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->kpn_status = Microsoft365OrderStatus::PLACED;
        $this->microsoft365DeploymentOne->save();

        $this->microsoft365DeploymentOne->subscription->technical_status = TechnicalStatus::REGISTRATION->value;
        $this->microsoft365DeploymentOne->subscription->save();

        new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->state([
            'product_uuid' => $this->childProductOne->uuid,
            'parent_subscription_id' => $this->subscriptionOne->id,
            'technical_status' => TechnicalStatus::REGISTRATION->value,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ])->createMany(5);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf('Microsoft365 subscription {%d} had a {placed} state and now has a {active} state.', $this->microsoft365DeploymentOne->id),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 subscription {%d} has subscription with technical status {registration} changed to {ok}.',
                $this->microsoft365DeploymentOne->id,
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);

        $logCount = Microsoft365SyncLog::where('log', sprintf(
            'Microsoft365 subscription {%d} has seats with technical status {registration} changed to {ok}.',
            $this->microsoft365DeploymentOne->id
        ))->get();
        self::assertCount(5, $logCount);

        self::assertDatabaseMissing('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 subscription {%d} has seats with end date {%s} changed to {%s}.',
                $this->microsoft365DeploymentOne->id,
                $this->subscriptionOne->children->firstOrFail()->end_date,
                $this->subscriptionOne->end_date
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);

        self::assertDatabaseMissing('microsoft365_sync_log', [
            'log' => sprintf(
                'No problems found on %s.',
                CarbonImmutable::today()->format(DateTimeFormat::DATE),
            ),
            'microsoft365_customer_info_id' => null,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function kpnStatusAcceptedIsUpdatedToActiveFromOrderSummary(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->kpn_status = Microsoft365OrderStatus::ACCEPTED;
        $this->microsoft365DeploymentOne->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertSame(Microsoft365OrderStatus::ACTIVE, $this->microsoft365DeploymentOne->refresh()->kpn_status);
        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf('Microsoft365 subscription {%d} had a {accepted} state and now has a {active} state.', $this->microsoft365DeploymentOne->id),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    // Detect cases where the child product is the parent product.
    #[Test]
    public function parentIsSeatProductSubscription(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->subscriptionOne->product_uuid = $this->childProductOne->uuid;
        $this->subscriptionOne->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf('There are {1} subscriptions coupled to a seat product in the microsoft_365 group with the following id\'s: [{"id":%d}]', $this->subscriptionOne->id),
        ]);
    }

    #[Test]
    public function threeDaysAgoSynced(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365CustomerInfo->synced_at = CarbonImmutable::now()->subDays(3);
        $this->microsoft365CustomerInfo->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->expectsOutput('Watching 0 Microsoft365 customers.')
            ->expectsOutput('Finished watching the Microsoft365 subscriptions.')
            ->assertOk();
    }

    #[Test]
    public function orderSummaryRetrieveError(): void
    {
        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('orderSummary')
            ->willThrowException(new OrderSummaryException('Something went wrong while retrieving order summary.'));
        $this->app->bind(Microsoft365Service::class, fn () => $microsoft365Service);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Error while retrieving order summary for KPN customer with id {%s}. With exception message: Something went wrong while retrieving order summary.',
                $this->microsoft365CustomerInfo->kpn_customer_id
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function customerNotFound(): void
    {
        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('orderSummary')
            ->willThrowException(new OrderSummaryCustomerNotFoundException('Something went wrong while retrieving order summary.'));
        $this->app->bind(Microsoft365Service::class, fn () => $microsoft365Service);

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'KPN Customer with id: {%s} not found.',
                $this->microsoft365CustomerInfo->kpn_customer_id
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function childDifferentEndAndNextBillingDate(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->subscriptionOne->children()->delete();

        $endDateChild = new SubscriptionFactory()->withCustomer()->for($this->childProductOne)->createOne([
            'product_uuid' => $this->childProductOne->uuid,
            'parent_subscription_id' => $this->subscriptionOne->id,
            'end_date' => $this->subscriptionOne->end_date->addMonth(),
            'next_billing_date' => $this->subscriptionOne->next_billing_date->addMonth(),
        ]);

        $this->subscriptionOne->refresh();
        $endDateChild->refresh();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 subscription {%d} has seats with end date {%s} changed to {%s}.',
                $this->microsoft365DeploymentOne->id,
                $endDateChild->end_date,
                $this->subscriptionOne->end_date
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 subscription {%d} has seats with next billing date {%s} changed to {%s}.',
                $this->microsoft365DeploymentOne->id,
                $endDateChild->next_billing_date,
                $this->subscriptionOne->next_billing_date
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);

        $endDateChild->refresh();
        self::assertSame($this->subscriptionOne->end_date->toDateTimeString(), $endDateChild->end_date->toDateTimeString());
        self::assertSame($this->subscriptionOne->next_billing_date->toDateTimeString(), $endDateChild->next_billing_date->toDateTimeString());
    }

    #[Test]
    public function emptyStringKpnCustomerId(): void
    {
        $this->microsoft365CustomerInfo->kpn_customer_id = '';
        $this->microsoft365CustomerInfo->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 customer {%d} has a invalid kpn_customer_id {%s}.',
                $this->microsoft365CustomerInfo->id,
                $this->microsoft365CustomerInfo->kpn_customer_id
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => null,
        ]);
    }

    #[Test]
    public function administrativeStatusDeletedTechnicalOk(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        foreach ($this->subscriptionOne->children as $child) {
            $child->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $child->save();
        }

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        $logCount = Microsoft365SyncLog::where('log', sprintf(
            'Microsoft365 subscription {%d} has seats with technical status {%s} changed to {%s}.',
            $this->microsoft365DeploymentOne->id,
            TechnicalStatus::OK->value,
            TechnicalStatus::DELETED->value
        ))->get();
        self::assertCount(5, $logCount);
    }

    #[Test]
    public function deletedSubscriptionsActiveMicrosoft365Subscription(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->subscription->technical_status = TechnicalStatus::DELETED->value;
        $this->microsoft365DeploymentOne->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->microsoft365DeploymentOne->subscription->save();

        foreach ($this->subscriptionOne->children as $child) {
            $child->technical_status = TechnicalStatus::DELETED->value;
            $child->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $child->save();
        }

        $this->microsoft365DeploymentOne->kpn_status = Microsoft365OrderStatus::MODIFY_PENDING;
        $this->microsoft365DeploymentOne->save();

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        $this->microsoft365DeploymentOne->refresh();
        self::assertSame(Microsoft365OrderStatus::TERMINATED, $this->microsoft365DeploymentOne->kpn_status);

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 subscription {%d} has parent subscription and children which are all deleted. Changing kpn_status to {%s}.',
                $this->microsoft365DeploymentOne->id,
                Microsoft365OrderStatus::TERMINATED->value
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    #[Test]
    public function deletedSubscriptionsTechnicalStatusWasStillOk(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365DeploymentOne->kpn_status = Microsoft365OrderStatus::TERMINATED;
        $this->microsoft365DeploymentOne->save();

        $this->microsoft365DeploymentOne->subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $this->microsoft365DeploymentOne->subscription->save();

        foreach ($this->subscriptionOne->children as $child) {
            $child->administrative_status = AdministrativeStatus::ARCHIVED->value;
            $child->save();
        }

        self::assertSame(TechnicalStatus::OK->value, $this->subscriptionOne->technical_status);
        self::assertCount(5, $this->subscriptionOne->children->where('technical_status', '<>', TechnicalStatus::DELETED->value));

        $this->artisan(Microsoft365SyncWatcher::class)
            ->assertOk();

        $this->subscriptionOne->refresh();

        self::assertSame(TechnicalStatus::DELETED->value, $this->subscriptionOne->technical_status);
        self::assertCount(0, $this->subscriptionOne->children->where('technical_status', '<>', TechnicalStatus::DELETED->value));

        self::assertDatabaseHas('microsoft365_sync_log', [
            'log' => sprintf(
                'Microsoft365 subscription {%d} has kpn_status terminated and administrative archived. Changing technical_status to {%s}.',
                $this->microsoft365DeploymentOne->id,
                TechnicalStatus::DELETED->value
            ),
            'microsoft365_customer_info_id' => $this->microsoft365CustomerInfo->id,
            'microsoft365_deployment_id' => $this->microsoft365DeploymentOne->id,
        ]);
    }

    #[Test]
    public function syncWatcherSyncsTenantOrderIdWhenMissing(): void
    {
        $this->setupOfficeClientMock((string) file_get_contents(__DIR__ . '/data/OrderSummaryResponse_V1.xml'));

        $this->microsoft365CustomerInfo->tenant_order_id = null;
        $this->microsoft365CustomerInfo->save();

        $this->artisan(Microsoft365SyncWatcher::class)->assertOk();

        $this->microsoft365CustomerInfo->refresh();

        // The tenant product order id in OrderSummaryResponse_V1.xml is 1000000.
        self::assertSame(1000000, $this->microsoft365CustomerInfo->tenant_order_id);
    }

    private function setupOfficeClientMock(string $fileContents): void
    {
        $mockHandler = new MockHandler([
            new Response(200, [], $fileContents),
        ]);
        $stack = HandlerStack::create($mockHandler);

        $officeClient = new OfficeClient('example.com', 'test', 'test', ['handler' => $stack]);

        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => new Microsoft365Service(
            $officeClient,
            self::resolve(GetNextInvoicePriceAction::class),
            self::createStub(ConfigurationInterface::class),
            self::createStub(LoggerInterface::class),
            self::createStub(ProvisionGateway::class),
            self::createStub(Microsoft365TenantService::class),
            self::createStub(GraphServiceClient::class),
            self::createStub(DnsService::class),
            self::createStub(Microsoft365CustomerInfoRepository::class),
            self::createStub(Microsoft365KpnProductRepository::class),
            self::createStub(Microsoft365Repository::class),
            self::createStub(DomainDeploymentRepository::class),
        ));
    }
}
