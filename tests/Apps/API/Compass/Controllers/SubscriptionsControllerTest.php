<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SubscriptionChangeFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\SubscriptionsController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Jobs\SuspendDomainJob;
use Waterfront\Domain\Domains\Jobs\UnsuspendDomainJob;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Enums\MigrationSubscriptionStatus;
use Waterfront\Domain\Ferry\Models\MigratedSubscriptionSteps;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionMetadataService;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(SubscriptionsController::class)]
class SubscriptionsControllerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private ProductGroup $productGroup;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow();

        $this->customer = new CustomerFactory()->createOne();
        $this->productGroup = new ProductGroupFactory()->createOne(
            [
                'name' => ProductGroupType::EXTENSION,
                'slug' => 'extension',
            ]
        );
        $this->product = new ProductFactory()->for($this->productGroup)->createOne(['slug' => 'extension_nl']);

        $this->subscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->administrativeStatusActive()
            ->technicalStatusOk()
            ->createOne();

        $this->subscription->refresh();
    }

    #[Test]
    public function showSubscriptionOnExistingSubscription(): void
    {
        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.show', $this->subscription->id)
            );

        $response->assertJsonFragment([
            'id'                    => $this->subscription->id,
            'customer_number'       => $this->customer->customer_number,
            'domain'                => $this->subscription->domain,
            'administrative_status' => $this->subscription->administrative_status,
            'technical_status'      => $this->subscription->technical_status,
            'start_date'            => $this->subscription->start_date->toW3cString(),
            'end_date'              => $this->subscription->end_date->toW3cString(),
        ]);
    }

    #[Test]
    public function showSubscriptionInvalidIdFail(): void
    {
        $response = $this->actingAsEmployee($this->customer->uuid)
            ->getJson($this->generateRoute('admin.subscriptions.subscription.show', ['identifier' => 4000]));

        $response->assertNotFound();
    }

    #[Test]
    public function showSubscriptionsPerDomain(): void
    {
        $domain = 'test.nl';

        new SubscriptionFactory()
            ->for(new CustomerFactory()->createOne())
            ->for(new ProductFactory()->hostingBrons()->createOne())
            ->createMany([
                ['domain' => $domain],
                ['domain' => $domain],
            ]);

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.list.for.domain', ['domain' => $domain])
            )->assertOk()->assertJsonCount(2);
    }

    #[Test]
    public function migrationStatus(): void
    {
        $domain = 'test.nl';

        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->createOne())
            ->for(new ProductFactory()->hostingBrons()->createOne())
            ->createOne([
                'domain' => $domain,
            ]);

        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::DOMAIN_MIGRATION;
        $step->status = MigrationSubscriptionStatus::EXECUTED;
        $step->executed_at = CarbonImmutable::now();
        $step->save();

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.migration', ['subscription' => $subscription->id]))
            ->assertOk()
            ->assertJsonCount(1);
    }

    #[Test]
    public function updateContractWithRenewalPrice(): void
    {
        new ProductPriceComponentFactory()->prolongation()->for($this->subscription->product)->createOne(['billing_period' => 24, 'contract_period' => 24, 'price' => 200]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.subscriptions.subscription.update.contract', ['subscription' => $this->subscription->id]),
                [
                    'contractPeriod' => 24,
                    'billingPeriod' => 24,
                    'renewalPrice' => 125,
                ]
            )
            ->assertNoContent();

        $mutation = $this->subscription->mutations->firstOrFail();

        self::assertSame(125, $mutation->net_price);
        self::assertSame(200, $mutation->gross_price);
    }

    #[Test]
    public function revertCancellation(): void
    {
        $this->subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->subscription->cancel_date = new CarbonImmutable();
        $this->subscription->save();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.cancel.revert', ['subscription' => $this->subscription->id])
            )
            ->assertNoContent();

        $this->subscription->refresh();

        self::assertSame(AdministrativeStatus::ACTIVE->value, $this->subscription->administrative_status);
        self::assertNull($this->subscription->cancel_date);
    }

    #[Test]
    public function suspendSubscriptionSuccess(): void
    {
        Queue::fake();
        $this->subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $this->subscription->cancel_date = new CarbonImmutable();
        $this->subscription->save();

        new DomainDeploymentFactory()->for($this->subscription)->withRtrProvider()->createOne();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.suspend', ['subscription' => $this->subscription->id])
            )
            ->assertOk();

        Queue::assertPushed(SuspendDomainJob::class);
    }

    #[Test]
    public function suspendNotEligible(): void
    {
        Queue::fake();

        $this->subscription->administrative_status = AdministrativeStatus::SUSPENDED->value;
        $this->subscription->technical_status = TechnicalStatus::OK->value;
        $this->subscription->cancel_date = new CarbonImmutable();
        $this->subscription->save();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.suspend', ['subscription' => $this->subscription->id])
            )
            ->assertServerError();

        Queue::assertNotPushed(SuspendDomainJob::class);
    }

    #[Test]
    public function unsuspendSuccess(): void
    {
        Queue::fake();

        $this->subscription->administrative_status = AdministrativeStatus::SUSPENDED->value;
        $this->subscription->technical_status = TechnicalStatus::SUSPENDED->value;
        $this->subscription->cancel_date = new CarbonImmutable();
        $this->subscription->save();

        new DomainDeploymentFactory()->for($this->subscription)->withRtrProvider()->createOne();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.unsuspend', ['subscription' => $this->subscription->id])
            )
            ->assertOk();

        Queue::assertPushed(UnsuspendDomainJob::class);
    }

    #[Test]
    public function unSuspendNotEligible(): void
    {
        Queue::fake();

        $this->subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $this->subscription->cancel_date = new CarbonImmutable();
        $this->subscription->save();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.unsuspend', ['subscription' => $this->subscription->id])
            )
            ->assertServerError();

        Queue::assertNotPushed(UnsuspendDomainJob::class);
    }

    #[Test]
    public function extendContractPercentageTooHigh(): void
    {
        new ProductPriceComponentFactory()->for($this->product)->prolongation()->createOne([
            'billing_period' => 24,
            'contract_period' => 24,
            'price' => 1000,
        ]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.subscriptions.subscription.update.contract', [
                    'subscription' => $this->subscription->id,
                ]),
                [
                    'billingPeriod' => 24,
                    'contractPeriod' => 24,
                    'renewalPrice' => 250,
            ]
            )
            ->assertUnprocessable()->assertJsonFragment([
                'errors' => [
                    'discount' => [
                        'contract-extension.percentage-too-high',
                    ]],
                    'message' => 'contract-extension.percentage-too-high',
            ]);
    }

    #[Test]
    public function extendContract51PercentDiscountTooHigh(): void
    {
        new ProductPriceComponentFactory()->for($this->product)->prolongation()->createOne([
            'billing_period' => 24,
            'contract_period' => 24,
            'price' => 1000,
        ]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.subscriptions.subscription.update.contract', [
                    'subscription' => $this->subscription->id,
                ]),
                [
                    'billingPeriod' => 24,
                    'contractPeriod' => 24,
                    'renewalPrice' => 499,
            ]
            )
            ->assertUnprocessable()->assertJsonFragment([
                'errors' => [
                    'discount' => [
                        'contract-extension.percentage-too-high',
                    ]],
                    'message' => 'contract-extension.percentage-too-high',
            ]);
    }

    #[Test]
    public function extendContract49PercentDiscountWillPass(): void
    {
        new ProductPriceComponentFactory()->for($this->product)->prolongation()->createOne([
            'billing_period' => 24,
            'contract_period' => 24,
            'price' => 1000,
        ]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.subscriptions.subscription.update.contract', [
                    'subscription' => $this->subscription->id,
                ]),
                [
                    'billingPeriod' => 24,
                    'contractPeriod' => 24,
                    'renewalPrice' => 501,
            ]
            )
            ->assertNoContent();
    }

    #[Test]
    public function extendContract50PercentDiscountWillPass(): void
    {
        new ProductPriceComponentFactory()->for($this->product)->prolongation()->createOne([
            'billing_period' => 24,
            'contract_period' => 24,
            'price' => 1000,
        ]);

        $this->actingAsEmployee()
            ->putJson(
                $this->generateRoute('admin.subscriptions.subscription.update.contract', [
                    'subscription' => $this->subscription->id,
                ]),
                [
                    'billingPeriod' => 24,
                    'contractPeriod' => 24,
                    'renewalPrice' => 500,
            ]
            )
            ->assertNoContent();
    }

    #[Test]
    public function provisioningRequestsReturnsResultsForGivenSubscription(): void
    {
        $createRequest = ProvisioningRequestFactory::new()->hosting()->createOne([
            'tag' => $this->subscription->uuid,
            'request_name' => ProvisionRequestName::CREATE_HOSTING,
        ]);

        $otherRequest = ProvisioningRequestFactory::new()->hosting()->createOne([
            'tag' => $this->subscription->uuid,
            'request_name' => ProvisionRequestName::GET_HOSTING_SSO,
        ]);

        $otherTagRequest = ProvisioningRequestFactory::new()->hosting()->createOne();

        ProvisioningResultFactory::new()->success()->for($createRequest)->createOne();
        ProvisioningResultFactory::new()->success()->for($otherRequest)->createOne();
        ProvisioningResultFactory::new()->success()->for($otherTagRequest)->createOne();

        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.provisioning-requests', ['subscription' => $this->subscription->id])
            );

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['request_name' => ProvisionRequestName::CREATE_HOSTING->value]);
        $response->assertJsonFragment(['request_name' => ProvisionRequestName::GET_HOSTING_SSO->value]);
        $response->assertJsonMissing(['request_uuid' => $otherTagRequest->uuid]);
    }

    #[Test]
    public function provisioningRequestsAlwaysIncludesCreateRequestEvenWhenNotOnCurrentPage(): void
    {
        $createRequest = ProvisioningRequestFactory::new()->hosting()->createOne([
            'tag' => $this->subscription->uuid,
            'request_name' => ProvisionRequestName::CREATE_HOSTING,
        ]);

        ProvisioningResultFactory::new()->success()->for($createRequest)->createOne();

        // Create additional requests so the create request falls outside the first page
        for ($i = 0; $i < 3; $i++) {
            $request = ProvisioningRequestFactory::new()->hosting()->createOne([
                'tag' => $this->subscription->uuid,
                'request_name' => ProvisionRequestName::GET_HOSTING_SSO,
            ]);
            ProvisioningResultFactory::new()->success()->for($request)->createOne();
        }

        $response = $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.provisioning-requests', ['subscription' => $this->subscription->id])
                . '?page=1&pageSize=2'
            );

        $response->assertOk();

        $data = $response->json('data');
        self::assertIsArray($data);
        $requestNames = array_column($data, 'request_name');
        self::assertContains(ProvisionRequestName::CREATE_HOSTING->value, $requestNames);
    }

    #[Test]
    public function assignEmployeeCallsServiceWithParsedUuid(): void
    {
        $uuid = Uuid::uuid4();
        $mock = self::createMock(SubscriptionMetadataService::class);
        $this->app->bind(SubscriptionMetadataService::class, fn () => $mock);

        $mock
            ->expects(self::once())
            ->method('assignEmployee')
            ->with(
                self::callback(fn ($sub) => $sub->id === $this->subscription->id),
                self::callback(fn ($arg) => $arg->toString() === $uuid->toString())
            );

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.assign-employee', ['subscription' => $this->subscription->id]),
                ['employee_uuid' => $uuid->toString()]
            )
            ->assertOk();
    }

    #[Test]
    public function assignEmployeeWithNullClearsAssignee(): void
    {
        $mock = self::createMock(SubscriptionMetadataService::class);
        $this->app->bind(SubscriptionMetadataService::class, fn () => $mock);

        $mock
            ->expects(self::once())
            ->method('assignEmployee')
            ->with(
                self::callback(fn ($sub) => $sub->id === $this->subscription->id),
                null
            );

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.assign-employee', ['subscription' => $this->subscription->id]),
                ['employee_uuid' => null]
            )
            ->assertOk();
    }

    #[Test]
    public function assignEmployeeReturnsValidationErrorWhenServiceFails(): void
    {
        $uuid = Uuid::uuid4();
        $mock = self::createMock(SubscriptionMetadataService::class);
        $this->app->bind(SubscriptionMetadataService::class, fn () => $mock);

        $mock
            ->expects(self::once())
            ->method('assignEmployee')
            ->willThrowException(new RuntimeException('Failed to retrieve required information'));

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.assign-employee', ['subscription' => $this->subscription->id]),
                ['employee_uuid' => $uuid->toString()]
            )
            ->assertUnprocessable();
    }

    #[Test]
    public function assignCategoryCallsServiceWithParsedCategory(): void
    {
        $mock = self::createMock(SubscriptionMetadataService::class);
        $this->app->bind(SubscriptionMetadataService::class, fn () => $mock);

        $mock
            ->expects(self::once())
            ->method('assignCategory')
            ->with(
                self::callback(fn ($sub) => $sub->id === $this->subscription->id),
                SubscriptionCategory::TECHNICAL
            );

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.assign-category', ['subscription' => $this->subscription->id]),
                ['category' => SubscriptionCategory::TECHNICAL->value]
            )
            ->assertOk();
    }

    #[Test]
    public function assignCategoryRejectsInvalidCategoryValue(): void
    {
        $mock = self::createMock(SubscriptionMetadataService::class);
        $this->app->bind(SubscriptionMetadataService::class, fn () => $mock);
        $mock->expects(self::never())->method('assignCategory');

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.assign-category', ['subscription' => $this->subscription->id]),
                ['category' => 'randomCategory']
            )
            ->assertUnprocessable();
    }

    #[Test]
    public function assignCategoryWithAbsentCategoryCallsServiceWithNull(): void
    {
        $mock = self::createMock(SubscriptionMetadataService::class);
        $this->app->bind(SubscriptionMetadataService::class, fn () => $mock);

        $mock
            ->expects(self::once())
            ->method('assignCategory')
            ->with(
                self::callback(fn ($sub) => $sub->id === $this->subscription->id),
                null
            );

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.assign-category', ['subscription' => $this->subscription->id]),
                []
            )
            ->assertOk();
    }

    #[Test]
    public function invoicePrefillWithNoPriorInvoiceReturnsNullDefaults(): void
    {
        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.invoice.prefill', ['subscription' => $this->subscription->id]))
            ->assertOk()
            ->assertJsonFragment([
                'start_date' => null,
                'end_date' => null,
                'gross_price' => null,
                'net_price' => null,
                'product' => [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                ],
            ]);
    }

    #[Test]
    public function invoicePrefillReusesLastInvoiceDatesAndPricesWhenPeriodAndProductMatch(): void
    {
        $lastInvoice = new InvoiceFactory()
            ->for($this->subscription)
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'period' => $this->subscription->billing_period,
                'gross_price' => 1200,
                'net_price' => 992,
            ]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.invoice.prefill', ['subscription' => $this->subscription->id]))
            ->assertOk()
            ->assertJsonFragment([
                'start_date' => $lastInvoice->start_date->format('Y-m-d'),
                'end_date' => $lastInvoice->end_date->format('Y-m-d'),
                'gross_price' => 1200,
                'net_price' => 992,
            ]);
    }

    #[Test]
    public function invoicePrefillComputesNewEndDateAndNullPricesWhenBillingPeriodDiffers(): void
    {
        $lastInvoice = new InvoiceFactory()
            ->for($this->subscription)
            ->for($this->customer)
            ->for($this->product)
            ->createOne([
                'period' => 1,
                'gross_price' => 1200,
                'net_price' => 992,
            ]);

        $expectedEndDate = $lastInvoice->start_date->addMonths($this->subscription->billing_period);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.invoice.prefill', ['subscription' => $this->subscription->id]))
            ->assertOk()
            ->assertJsonFragment([
                'start_date' => $lastInvoice->start_date->format('Y-m-d'),
                'end_date' => $expectedEndDate->format('Y-m-d'),
                'gross_price' => null,
                'net_price' => null,
            ]);
    }

    #[Test]
    public function invoicePrefillIgnoresInvoiceWithZeroGrossPrice(): void
    {
        new InvoiceFactory()
            ->for($this->subscription)
            ->for($this->customer)
            ->for($this->product)
            ->createOne(['gross_price' => 0]);

        $this->actingAsEmployee()
            ->getJson($this->generateRoute('admin.subscriptions.subscription.invoice.prefill', ['subscription' => $this->subscription->id]))
            ->assertOk()
            ->assertJsonFragment([
                'start_date' => null,
                'end_date' => null,
                'gross_price' => null,
                'net_price' => null,
            ]);
    }

    #[Test]
    public function createInvoiceSuccess(): void
    {
        $startDate = CarbonImmutable::today();
        $endDate = $startDate->addYear();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.invoice.create', ['subscription' => $this->subscription->id]),
                [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'gross_price' => 1200,
                    'net_price' => 992,
                ]
            )
            ->assertCreated();

        self::assertDatabaseHas('invoices', [
            'subscription_id' => $this->subscription->id,
            'gross_price' => 1200,
            'net_price' => 992,
        ]);
    }

    #[Test]
    public function createInvoiceValidationFailsWithoutRequiredFields(): void
    {
        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.invoice.create', ['subscription' => $this->subscription->id]),
                []
            )
            ->assertUnprocessable();
    }

    #[Test]
    public function createInvoiceCascadesFreeProductInvoiceWhenApplicable(): void
    {
        $freeProduct = new ProductFactory()->for($this->productGroup)->createOne();

        new ProductPriceComponentFactory()->for($freeProduct)->prolongation()->createOne([
            'billing_period' => 12,
            'price' => 100,
        ]);
        new ProductPriceComponentFactory()->for($freeProduct)->registration()->createOne([
            'billing_period' => 12,
            'price' => 90,
        ]);

        $productSpec = new ProductSpecFactory()->createOne([
            'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
            'value' => $freeProduct->slug,
            'product_id' => $this->product->id,
        ]);
        $this->product->productSpecs()->save($productSpec);

        $startDate = CarbonImmutable::today();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.invoice.create', ['subscription' => $this->subscription->id]),
                [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $startDate->addYear()->format('Y-m-d'),
                    'gross_price' => 1200,
                    'net_price' => 992,
                ]
            )
            ->assertCreated();

        self::assertSame(2, Invoice::count());
        self::assertDatabaseHas('invoices', [
            'subscription_id' => $this->subscription->id,
            'product_id' => $freeProduct->id,
        ]);
    }

    #[Test]
    public function createInvoiceDoesNotCreateAdminFeesInvoiceWhenFlagFalse(): void
    {
        $this->customer->has_direct_debit = false;
        $this->customer->save();

        $adminFeesProduct = new ProductFactory()->administrationFees()->createOne();
        new ProductPriceComponentFactory()->administrationFee()->createOne(['product_id' => $adminFeesProduct->id]);

        $startDate = CarbonImmutable::today();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.invoice.create', ['subscription' => $this->subscription->id]),
                [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $startDate->addYear()->format('Y-m-d'),
                    'gross_price' => 1200,
                    'net_price' => 992,
                    'manually_add_admin_fees' => false,
                ]
            )
            ->assertCreated();

        self::assertSame(1, Invoice::count());
    }

    #[Test]
    public function createInvoiceCascadesAdminFeesInvoiceWhenFlagTrueAndEligible(): void
    {
        $this->customer->has_direct_debit = false;
        $this->customer->save();

        $adminFeesProduct = new ProductFactory()->administrationFees()->createOne();
        new ProductPriceComponentFactory()->administrationFee()->createOne(['product_id' => $adminFeesProduct->id]);

        $startDate = CarbonImmutable::today();

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.invoice.create', ['subscription' => $this->subscription->id]),
                [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $startDate->addYear()->format('Y-m-d'),
                    'gross_price' => 1200,
                    'net_price' => 992,
                    'manually_add_admin_fees' => true,
                ]
            )
            ->assertCreated();

        self::assertSame(2, Invoice::count());
        self::assertDatabaseHas('invoices', [
            'product_id' => $adminFeesProduct->id,
            'subscription_id' => null,
        ]);
    }

    #[Test]
    public function listChangesReturnsChangesForSubscription(): void
    {
        $fromProduct = new ProductFactory()->hostingBrons($this->productGroup)->createOne();
        $toProduct = new ProductFactory()->hostingGold($this->productGroup)->createOne();

        new SubscriptionChangeFactory()->for($this->subscription)->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'from_product_uuid' => $fromProduct->uuid,
            'to_product_uuid' => $toProduct->uuid,
            'type' => ProductChangeType::UPGRADE,
            'status' => SubscriptionChangeStatus::COMPLETED,
        ]);

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.list.changes', ['subscription' => $this->subscription->id])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'from_product_name' => 'hosting_brons',
                'to_product_name'   => 'hosting_gold',
                'type'              => ProductChangeType::UPGRADE->value,
                'status'            => SubscriptionChangeStatus::COMPLETED->value,
            ])
            ->assertJsonPath('meta.totalChanges', 1);
    }

    #[Test]
    public function listChangesReturnsFailureReasonForFailedChange(): void
    {
        $fromProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $toProduct = new ProductFactory()->for($this->productGroup)->createOne();

        $subscriptionChange = new SubscriptionChangeFactory()->failed()->for($this->subscription)->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'from_product_uuid' => $fromProduct->uuid,
            'to_product_uuid'   => $toProduct->uuid,
            'type'              => ProductChangeType::DOWNGRADE,
        ]);

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.list.changes', ['subscription' => $this->subscription->id])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'status'          => SubscriptionChangeStatus::EXECUTION_FAILED->value,
                'failure_code'    => $subscriptionChange->failure_code,
                'failure_message' => $subscriptionChange->failure_message,
            ]);
    }

    #[Test]
    public function listChangesReturnsEmptyWhenSubscriptionHasNoChanges(): void
    {
        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.list.changes', ['subscription' => $this->subscription->id])
            )
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.totalChanges', 0);
    }

    #[Test]
    public function listChangesOnlyReturnsChangesForTheGivenSubscription(): void
    {
        $fromProduct = new ProductFactory()->for($this->productGroup)->createOne();
        $toProduct = new ProductFactory()->for($this->productGroup)->createOne();

        new SubscriptionChangeFactory()->for($this->subscription)->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'from_product_uuid' => $fromProduct->uuid,
            'to_product_uuid'   => $toProduct->uuid,
        ]);

        $otherSubscription = new SubscriptionFactory()
            ->for($this->product)
            ->for($this->customer)
            ->createOne();

        new SubscriptionChangeFactory()->for($otherSubscription)->createOne([
            'subscription_uuid' => $otherSubscription->uuid,
            'from_product_uuid' => $fromProduct->uuid,
            'to_product_uuid'   => $toProduct->uuid,
        ]);

        $this->actingAsEmployee()
            ->getJson(
                $this->generateRoute('admin.subscriptions.subscription.list.changes', ['subscription' => $this->subscription->id])
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.totalChanges', 1);
    }

    #[Test]
    public function addDomainToSpamExpertsAddsTheDomainOfTheSubscription(): void
    {
        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient->expects(self::once())
            ->method('addDomain')
            ->with($this->subscription->domain, null);

        $this->app->instance(SpamExpertsClient::class, $spamExpertsClient);

        $translator = self::resolve(TranslatorInterface::class);

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.add.domain.to.spam.experts', ['subscription' => $this->subscription->uuid])
            )
            ->assertOk()
            ->assertJsonFragment(['message' => $translator->translate('spam_experts.action_success')]);
    }

    #[Test]
    public function addDomainToSpamExpertsFailsWhenTheSubscriptionHasNoDomain(): void
    {
        $this->subscription->domain = null;
        $this->subscription->save();

        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient->expects(self::never())->method('addDomain');

        $this->app->instance(SpamExpertsClient::class, $spamExpertsClient);

        $translator = self::resolve(TranslatorInterface::class);

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.add.domain.to.spam.experts', ['subscription' => $this->subscription->uuid])
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => $translator->translate('spam_experts.action_failed')]);
    }

    #[Test]
    public function addDomainToSpamExpertsFailsWhenTheSpamExpertsClientFails(): void
    {
        $spamExpertsClient = self::createMock(SpamExpertsClient::class);
        $spamExpertsClient->expects(self::once())
            ->method('addDomain')
            ->willThrowException(new RuntimeException('Domain already exists'));

        $this->app->instance(SpamExpertsClient::class, $spamExpertsClient);

        $translator = self::resolve(TranslatorInterface::class);

        $this->actingAsEmployee()
            ->postJson(
                $this->generateRoute('admin.subscriptions.subscription.add.domain.to.spam.experts', ['subscription' => $this->subscription->uuid])
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => $translator->translate('spam_experts.action_failed')]);
    }
}
