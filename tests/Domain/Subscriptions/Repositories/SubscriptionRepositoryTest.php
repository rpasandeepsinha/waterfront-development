<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Repositories;

use Carbon\CarbonImmutable;
use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(SubscriptionRepository::class)]
class SubscriptionRepositoryTest extends IntegrationTestCase
{
    private SubscriptionRepository $repository;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = self::resolve(SubscriptionRepository::class);

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function setTechnicalStatus(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne()))
            ->technicalStatusOk()
            ->createOne();

        $this->repository->setTechnicalStatus($subscription, TechnicalStatus::PENDING->value);

        self::assertSame(TechnicalStatus::PENDING->value, $subscription->refresh()->technical_status);
    }

    #[Test]
    public function isExpiredReturnsTrueWhenSubscriptionIsCancelledAndEndDateIsPast(): void
    {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::CANCELED->value;
        $subscription->end_date = CarbonImmutable::now()->subDay();

        self::assertTrue($this->repository->isExpired($subscription));
    }

    #[Test]
    public function isExpiredReturnsTrueWhenSubscriptionIsExpiredAndEndDateAndTerminationDateArePast(): void
    {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::EXPIRED->value;
        $subscription->end_date = CarbonImmutable::now()->subDay();
        $subscription->termination_date = CarbonImmutable::now()->subDay();

        self::assertTrue($this->repository->isExpired($subscription));
    }

    #[Test]
    public function isExpiredReturnsFalseWhenSubscriptionIsExpiredAndEndDateIsPastButTerminationDateIsFuture(): void
    {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::EXPIRED->value;
        $subscription->end_date = CarbonImmutable::now()->subDay();
        $subscription->termination_date = CarbonImmutable::now()->addDay();

        self::assertFalse($this->repository->isExpired($subscription));
    }

    #[Test]
    public function isExpiredReturnsFalseWhenSubscriptionIsNotExpired(): void
    {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->end_date = CarbonImmutable::now()->addDay();

        self::assertFalse($this->repository->isExpired($subscription));
    }

    #[Test]
    public function domainExistsInSubscription(): void
    {
        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne(['name' => '.nl']);
        ProviderFactory::new()->domainPlaceholder()->createOne();

        $domain = 'test.nl';

        self::assertFalse($this->repository->domainExistsInSubscription($domain));

        $subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne([
            'domain' => $domain,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);

        self::assertTrue($this->repository->domainExistsInSubscription($domain));

        $subscription->administrative_status = AdministrativeStatus::ARCHIVED->value;
        $subscription->save();

        self::assertFalse($this->repository->domainExistsInSubscription($domain));
    }

    #[Test]
    public function terminateFetchParentOfChildSubscriptions(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->microsoft365())->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);

        $end = new DateTime()->modify('-1 days')->format(DateTimeFormat::DEFAULT);

        new SubscriptionFactory()->count(2)->for($product)->for($this->customer)->createOne([
            'parent_subscription_id' => $subscription->id,
            'technical_status' => TechnicalStatus::OK->value,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'end_date' => $end,
        ]);

        new SubscriptionFactory()->count(3)->for($product)->for($this->customer)->createOne([
            'parent_subscription_id' => $subscription->id,
            'technical_status' => TechnicalStatus::OK->value,
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'end_date' => $end,
            'termination_date' => $end,
        ]);

        $service = self::resolve(SubscriptionRepository::class);
        $items = $service->getAllDueForTermination();

        self::assertCount(1, $items);
    }

    #[Test]
    public function getSubscriptionByCustomerDomainAndType(): void
    {
        $domain = 'example.com';
        $product = new ProductFactory()->for(new ProductGroupFactory()->dns())->createOne();

        $expectedSubscription = new SubscriptionFactory()->for($product)->for($this->customer)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'domain' => $domain,
        ]);

        $subscription = $this->repository->getSubscriptionByCustomerDomainAndType(
            $this->customer,
            $domain,
            ProductGroupType::DNS
        );

        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertSame($expectedSubscription->id, $subscription->id);
    }

    #[Test]
    public function getByUuid(): void
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        // Cloud the DB a bit >:).
        new SubscriptionFactory()->withCustomer()->for($product)->count(3)->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne();
        $retrievedSubscription = $this->repository->getByUuid($subscription->uuid);

        self::assertInstanceOf(Subscription::class, $retrievedSubscription);
        self::assertSame($subscription->id, $retrievedSubscription->id);
    }

    #[Test]
    public function getActiveFreeRedirectDeployments(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);
        $freeRedirectProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => ProductType::FREE_REDIRECT]);
        $freeDnsProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => ProductType::FREE_DNS]);
        $redirectProduct = new ProductFactory()->for($productGroup)->createOne(['slug' => ProductType::REDIRECT]);

        $activeFreeRedirectSubscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeRedirectProduct)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeRedirectProduct)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            ]);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($freeDnsProduct)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($redirectProduct)
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        $freeRedirectSubscriptions = $this->repository->getActiveFreeRedirectSubscriptions(10);
        self::assertCount(1, $freeRedirectSubscriptions);
        self::assertNotNull($freeRedirectSubscriptions->first());
        self::assertSame($activeFreeRedirectSubscription->uuid, $freeRedirectSubscriptions->first()->uuid);
    }

    #[Test]
    public function getRecentDomainNamesReturnsOnlyRecentDomainRegistrations(): void
    {
        $extensionProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->extension()->createOne())
            ->createOne();
        $hostingProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->hosting()->createOne())
            ->createOne();

        $recentDomain = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(['domain' => 'recent.nl']);
        $recentDomain->created_at = CarbonImmutable::now()->subMonth();
        $recentDomain->save();

        $oldDomain = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(['domain' => 'old.nl']);
        $oldDomain->created_at = CarbonImmutable::now()->subMonths(6);
        $oldDomain->save();

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($hostingProduct)
            ->createOne(['domain' => 'hosting.nl']);

        $domains = $this->repository->getRecentDomainNames(CarbonImmutable::now()->subMonths(4), 10);

        self::assertSame(['recent.nl'], $domains);
    }

    #[Test]
    public function getRecentDomainNamesRespectsTheLimit(): void
    {
        $extensionProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->extension()->createOne())
            ->createOne();

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(['domain' => 'first.nl']);
        new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(['domain' => 'second.nl']);

        $domains = $this->repository->getRecentDomainNames(CarbonImmutable::now()->subMonths(4), 1);

        self::assertCount(1, $domains);
    }
}
