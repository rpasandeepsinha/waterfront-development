<?php

declare(strict_types=1);

namespace Tests\Domain\Hosting\Repositories;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\HostingDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(HostingDeploymentRepository::class)]
class HostingDeploymentRepositoryTest extends IntegrationTestCase
{
    private Customer $customer;

    private HostingDeploymentRepository $subscriptionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->subscriptionRepository = self::resolve(HostingDeploymentRepository::class);
    }

    #[Test]
    public function getHostingSubscriptionsByCustomerSuccessful(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for($productGroup))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new HostingDeploymentFactory()->for($subscription, 'subscription')->createOne();

        self::assertCount(1, $this->subscriptionRepository->getActiveSharedByCustomer($this->customer));
    }

    #[Test]
    public function getHostingSubscriptionsByCustomerNoServer(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for($productGroup))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new HostingDeploymentFactory()->for($subscription, 'subscription')->createOne([
            'server_id' => null,
        ]);

        self::assertCount(0, $this->subscriptionRepository->getActiveSharedByCustomer($this->customer));
    }

    #[Test]
    public function getHostingSubscriptionsByCustomerNoUsernames(): void
    {
        $productGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::HOSTING,
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for($productGroup))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new HostingDeploymentFactory()->for($subscription, 'subscription')->createOne([
            'directadmin_customer_username' => null,
            'plesk_customer_username' => null,
        ]);

        self::assertCount(0, $this->subscriptionRepository->getActiveSharedByCustomer($this->customer));
    }

    #[Test]
    public function hasHostingDeploymentForDomainReturnsTrueForActiveHostingWithDeployment(): void
    {
        $subscription = $this->createHostingSubscription('hosted.nl', AdministrativeStatus::ACTIVE);
        new HostingDeploymentFactory()->for($subscription, 'subscription')->createOne();

        self::assertTrue($this->subscriptionRepository->hasHostingDeploymentForDomain('hosted.nl'));
    }

    #[Test]
    public function hasHostingDeploymentForDomainReturnsFalseWhenNoDeploymentExists(): void
    {
        $this->createHostingSubscription('nodeployment.nl', AdministrativeStatus::ACTIVE);

        self::assertFalse($this->subscriptionRepository->hasHostingDeploymentForDomain('nodeployment.nl'));
    }

    #[Test]
    public function hasHostingDeploymentForDomainReturnsFalseForAdministrativelyEndedSubscription(): void
    {
        $subscription = $this->createHostingSubscription('archived.nl', AdministrativeStatus::ARCHIVED);
        new HostingDeploymentFactory()->for($subscription, 'subscription')->createOne();

        self::assertFalse($this->subscriptionRepository->hasHostingDeploymentForDomain('archived.nl'));
    }

    #[Test]
    public function hasHostingDeploymentForDomainReturnsFalseForUnknownDomain(): void
    {
        self::assertFalse($this->subscriptionRepository->hasHostingDeploymentForDomain('unknown.nl'));
    }

    private function createHostingSubscription(string $domain, AdministrativeStatus $status): Subscription
    {
        $productGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::HOSTING]);

        return new SubscriptionFactory()
            ->for($this->customer)
            ->for(new ProductFactory()->for($productGroup))
            ->createOne([
                'domain' => $domain,
                'administrative_status' => $status->value,
            ]);
    }
}
