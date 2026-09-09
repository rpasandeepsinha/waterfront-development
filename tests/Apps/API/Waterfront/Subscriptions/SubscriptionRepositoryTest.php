<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Subscriptions;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Generator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;

#[CoversClass(SubscriptionRepository::class)]
class SubscriptionRepositoryTest extends IntegrationTestCase
{
    private Customer $customer;

    private SubscriptionRepository $subscriptionRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);
    }

    #[Test]
    public function findAllSubscriptionsInHostingGroupWithoutServicePlusProductSpec(): void
    {
        $group = new ProductGroupFactory()->hosting()->createOne();

        $cheapProductWithoutServicePlus = new ProductFactory()->for($group)->createOne();

        $productWithServicePlus = new ProductFactory()->for($group)->createOne();
        ProductSpecFactory::new()->for($productWithServicePlus)->createOne([
            'name' => ProductSpecName::HAS_SERVICE_PLUS,
            'value' => 'true',
        ]);

        $servicePlusProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->addon())
            ->createOne(['name' => 'service_plus']);
        ProductSpecFactory::new()->for($servicePlusProduct)->createOne([
            'name' => ProductSpecName::HAS_SERVICE_PLUS,
            'value' => 'true',
        ]);

        $productThatCannotOrderServicePlus = new ProductFactory()->for($group)->createOne(['name' => 'legacyhosting', 'orderable' => false]);

        //Cheap product no service plus, no child
        $noServicePlusSub = new SubscriptionFactory()
            ->for($this->customer)
            ->for($cheapProductWithoutServicePlus)
            ->createOne(['domain' => 'hasnoserviceplus.nl']);

        //Cheap product no service plus, no child, administratively archived
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($cheapProductWithoutServicePlus)
            ->administrativeStatusArchived()
            ->createOne();

        //Expensive product, so service plus
        new SubscriptionFactory()->for($this->customer)->for($productWithServicePlus)->createOne();

        //Cheap product no service plus, serviceplus addon bought
        $parent = new SubscriptionFactory()
            ->for($this->customer)
            ->for($cheapProductWithoutServicePlus)
            ->administrativeStatusActive()
            ->createOne();
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($servicePlusProduct)
            ->administrativeStatusActive()
            ->createOne(['parent_subscription_id' => $parent->id]);

        //Cheap product no service plus parent active, serviceplus addon bought but administratively ended
        $parentActive = new SubscriptionFactory()
            ->for($this->customer)
            ->for($cheapProductWithoutServicePlus)
            ->createOne(['domain' => 'cheapProductButBoughtServicePlusAdditionallyButChildExpired']);
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($servicePlusProduct)
            ->administrativeStatusArchived()
            ->createOne(['parent_subscription_id' => $parentActive->id]);

        // (legacy) hosting product that can not order service plus
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($productThatCannotOrderServicePlus)
            ->administrativeStatusActive()
            ->createOne();

        $collection = $this->subscriptionRepository->findAllSubscriptionsInHostingGroupWithoutServicePlusProductSpec($this->customer->id);

        $noServicePlusSubFromCollection = $collection->where('domain', $noServicePlusSub->domain)->first();
        $activeParentFromCollection = $collection->where('domain', $parentActive->domain)->first();

        self::assertNotNull($noServicePlusSubFromCollection);
        self::assertNotNull($activeParentFromCollection);
        self::assertCount(2, $collection);
        self::assertSame($noServicePlusSub->domain, $noServicePlusSubFromCollection->domain);
        self::assertSame($parentActive->domain, $activeParentFromCollection->domain);
    }

    /**
     * Test we collect the correct subscriptions for renewal.
     */
    #[Test]
    public function getAllDueForRenewal(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne(['name' => '.nl']);

        ProviderFactory::new()->domainRtr()->createOne();
        ProviderFactory::new()->sslOpenProvider()->createOne();

        new SubscriptionFactory()->for($this->customer)->createOne([
            'start_date' => CarbonImmutable::today()->subYear()->addDays(
                $this->getConfiguration()->getAsInteger('constants.renewal-days') - 10
            ),
            'product_uuid' => $product->uuid,
        ]);

        $nonRenewable = new SubscriptionFactory()->for($this->customer)->createOne([
            'start_date' => CarbonImmutable::today(),
            'product_uuid' => $product->uuid,
        ]);

        $renewable = $this->subscriptionRepository->getAllDueForRenewal();

        self::assertNotEmpty($renewable);

        $renewableItem = $renewable->firstOrFail();

        self::assertLessThan(CarbonImmutable::today()->addDays(
            $this->getConfiguration()->getAsInteger('constants.renewal-days')
        ), $renewableItem->end_date);
        self::assertNotContains($renewableItem, new Collection($nonRenewable));
    }

    #[Test]
    public function getAllCustomersEligibleForInvoicing(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne();

        $billingDate = new CarbonImmutable('2022-12-14');

        $customer = new CustomerFactory()->createOne();
        $customer = $customer->fresh();
        self::assertInstanceOf(Customer::class, $customer);

        $otherCustomer = new CustomerFactory()->createOne();

        new SubscriptionFactory()->for($product)->for($customer)->createOne([
            'next_billing_date' => new CarbonImmutable('2022-12-12'),
        ]);
        new SubscriptionFactory()->for($product)->for($customer)->createOne([
            'next_billing_date' => new CarbonImmutable('2022-12-13'),
        ]);

        $subscriptionNotEligibleForInvoicingWithBillingDate = new SubscriptionFactory()->for($otherCustomer)->for($product)->createOne([
            'next_billing_date' => new CarbonImmutable('2022-12-14'),
        ]);
        $subscriptionNotEligibleForInvoicingWithDateInFuture = new SubscriptionFactory()->for($otherCustomer)->for($product)->createOne([
            'next_billing_date' => new CarbonImmutable('2022-12-15'),
        ]);

        $customers = $this->subscriptionRepository->getAllCustomersEligibleForInvoicing($billingDate);

        self::assertCount(1, $customers);
        self::assertEquals($customer, $customers->first());
        self::assertNotEquals($subscriptionNotEligibleForInvoicingWithBillingDate->customer, $customers->first());
        self::assertNotEquals($subscriptionNotEligibleForInvoicingWithDateInFuture->customer, $customers->first());
    }

    #[Test]
    public function getAllDueForInvoicing(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne(['name' => '.nl']);

        ProviderFactory::new()->domainRtr()->createOne();
        ProviderFactory::new()->sslOpenProvider()->createOne();

        new SubscriptionFactory()->for($this->customer)->createOne([
            'start_date' => CarbonImmutable::today()->subYear(),
            'next_billing_date' => CarbonImmutable::today(),
            'product_uuid' => $product->uuid,
        ]);

        $nonRenewable = new SubscriptionFactory()->for($this->customer)->createOne([
            'start_date' => CarbonImmutable::today(),
            'next_billing_date' => CarbonImmutable::tomorrow(),
            'product_uuid' => $product->uuid,
        ]);

        $renewable = $this->subscriptionRepository->getAllDueForInvoicing(
            $this->customer,
            CarbonImmutable::tomorrow()
        );

        self::assertNotEmpty($renewable);

        $renewableItem = $renewable->firstOrFail();

        self::assertLessThan(CarbonImmutable::tomorrow(), $renewableItem->next_billing_date);
        self::assertNotContains($nonRenewable, $renewable);
    }

    #[Test]
    public function getDomainSubscriptionsDueForAutoRenewalDisable(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $product = new ProductFactory()->for($productGroup)->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain1.nl')
            ->administrativeStatusCancelled()
            ->technicalStatusDomainActive()
            ->createOne([
                'end_date' => CarbonImmutable::tomorrow('Europe/Amsterdam'),
            ]);

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain2.nl')
            ->administrativeStatusCancelled()
            ->technicalStatusDomainActive()
            ->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain2.nl')
            ->technicalStatusDomainActive()
            ->createOne();

        $subscriptions = $this->subscriptionRepository->getDomainSubscriptionsDueForAutoRenewalDisable();
        $subscription = $subscriptions[0];
        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertCount(1, $subscriptions);
        self::assertSame('domain1.nl', $subscription->domain);
    }

    #[Test]
    public function getDomainSubscriptionsDueForAutoRenewalDisableWithoutResult(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $product = new ProductFactory()->for($productGroup)->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain1.nl')
            ->technicalStatusDomainActive()
            ->administrativeStatusCancelled()
            ->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain2.nl')
            ->technicalStatusDomainActive()
            ->administrativeStatusCancelled()
            ->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain2.nl')
            ->technicalStatusDomainActive()
            ->createOne();

        $subscriptions = $this->subscriptionRepository->getDomainSubscriptionsDueForAutoRenewalDisable();
        self::assertEmpty($subscriptions);
    }

    #[Test]
    public function getDomainSubscriptionsDueForAutoRenewalDisableMultipleResults(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $product = new ProductFactory()->for($productGroup)->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain1.nl')
            ->administrativeStatusCancelled()
            ->technicalStatusDomainActive()
            ->createOne([
                'end_date' => CarbonImmutable::tomorrow('Europe/Amsterdam'),
            ]);

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain2.nl')
            ->administrativeStatusCancelled()
            ->technicalStatusDomainActive()
            ->createOne([
                'end_date' => CarbonImmutable::tomorrow('Europe/Amsterdam'),
            ]);

        $notCollectedSubscription = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('domain3.nl')
            ->technicalStatusDomainActive()
            ->createOne([
                'end_date' => CarbonImmutable::tomorrow('Europe/Amsterdam'),
            ]);

        $subscriptions = $this->subscriptionRepository->getDomainSubscriptionsDueForAutoRenewalDisable();
        self::assertCount(2, $subscriptions);
        self::assertCount(0, $subscriptions->where('uuid', $notCollectedSubscription->uuid));
    }

    #[Test]
    public function getDomainSubscriptionsDueForAutoRenewalDisableDateInThePast(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $product = new ProductFactory()->for($productGroup)->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('inthepast.nl')
            ->administrativeStatusCancelled()
            ->technicalStatusDomainActive()
            ->createOne([
                'end_date' => CarbonImmutable::yesterday('Europe/Amsterdam'),
            ]);

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain('tomorrow.nl')
            ->administrativeStatusCancelled()
            ->technicalStatusDomainActive()
            ->createOne([
                'end_date' => CarbonImmutable::tomorrow('Europe/Amsterdam'),
            ]);

        $subscriptions = $this->subscriptionRepository->getDomainSubscriptionsDueForAutoRenewalDisable();
        self::assertCount(2, $subscriptions);
    }

    #[Test]
    public function getNotTerminatedManualSubscriptions(): void
    {
        $productGroup = new ProductGroupFactory()->manualSubscription();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $domain = 'needs-notification.nl';

        $needsNotification = new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->forDomain($domain)
            ->administrativeStatusArchived()
            ->technicalStatusDomainActive()
            ->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->administrativeStatusActive()
            ->technicalStatusDomainActive()
            ->createOne();

        new SubscriptionFactory()
            ->for($product)
            ->for($this->customer)
            ->administrativeStatusActive()
            ->technicalStatus(TechnicalStatus::PENDING->value)
            ->createOne();

        $subscriptions = $this->subscriptionRepository->getNotTerminatedManualSubscriptions();
        self::assertCount(1, $subscriptions);

        $subscription = $subscriptions->firstOrFail();

        self::assertSame($subscription->uuid, $needsNotification->uuid);
    }

    #[DataProvider('getTerminationDates')]
    #[Test]
    public function getAllDueForTermination(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
        string $status,
        bool $shouldTerminate
    ): void {
        new SubscriptionFactory()->withCustomer()->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))->create([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'termination_date' => $shouldTerminate ? $endDate : null,
            'parent_subscription_id' => null,
            'administrative_status' => $status,
            'technical_status' => TechnicalStatus::OK->value,
        ]);

        $terminate = $this->subscriptionRepository->getAllDueForTermination();

        if ($shouldTerminate === false) {
            self::assertEmpty($terminate);
            return;
        }

        self::assertNotEmpty($terminate);

        $terminateItem = $terminate->firstOrFail();

        self::assertLessThan(CarbonImmutable::today()->addDays(
            $this->getConfiguration()->getAsInteger('constants.invoice-ahead-days')
        ), $terminateItem->next_billing_date);
    }

    public static function getTerminationDates(): Generator
    {
        yield [
            new DateTimeImmutable('-1 year 00:00'),
            new DateTimeImmutable('yesterday 00:00'),
            AdministrativeStatus::EXPIRED->value,
            true,
        ];
        yield [
            new DateTimeImmutable('-1 year 00:00'),
            new DateTimeImmutable('yesterday 00:00'),
            AdministrativeStatus::ACTIVE->value,
            false,
        ];
        yield [
            new DateTimeImmutable('-1 year 23:58'),
            new DateTimeImmutable('today'),
            AdministrativeStatus::CANCELED->value,
            false,
        ];
        yield [
            new DateTimeImmutable('-10 years'),
            new DateTimeImmutable('now'),
            AdministrativeStatus::CANCELED->value,
            false,
        ];
        yield [
            new DateTimeImmutable('-10 years'),
            new DateTimeImmutable('yesterday 23:59'),
            AdministrativeStatus::EXPIRED->value,
            true,
        ];
    }
}
