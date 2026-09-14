<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Integration;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Marketing\Enums\HubspotObjectType;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventStatus;
use Waterfront\Domain\Marketing\Models\HubspotEvent;
use Waterfront\Domain\Marketing\Models\HubspotObjectSync;
use Waterfront\Domain\Marketing\Repositories\HubspotRepository;

#[CoversClass(HubspotRepository::class)]
class HubspotRepositoryTest extends IntegrationTestCase
{
    private HubspotRepository $repository;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::resolve(HubspotRepository::class);
        $this->customer = new CustomerFactory()->createOne(
            [
                'updated_at' => CarbonImmutable::yesterday(),
                'created_at' => CarbonImmutable::yesterday(),
            ],
        );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::CUSTOMER;
        $hos->sandwave_object_id = $this->customer->uuid;
        $hos->hubspot_object_id = 'any-fake-hubspot-customer-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();
        $hos->save();
    }

    #[Test]
    public function outdatedSubscriptionWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday()->addHour(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $hos->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $hos->hubspot_object_id = 'any-fake-hubspot-subscription-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();
        $hos->save();

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }

    #[Test]
    public function outdatedCustomerOfSubscriptionWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $this->customer->updated_at = CarbonImmutable::yesterday()->addHour();
        $this->customer->save();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $hos->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $hos->hubspot_object_id = 'any-fake-hubspot-subscription-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();
        $hos->save();

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }

    #[Test]
    public function outdatedOneTimeServiceWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $this->customer->updated_at = CarbonImmutable::yesterday()->addHour();
        $this->customer->save();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $hos->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $hos->hubspot_object_id = 'any-fake-hubspot-subscription-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();
        $hos->save();

        $oneTimeServiceProductGroup = new ProductGroupFactory()->oneTimeService()->createOne();

        $oneTimeServiceProduct = new ProductFactory()->for($oneTimeServiceProductGroup)->createOne();

        $oneTimeService = new OneTimeServiceFactory()
            ->for($subscription)
            ->for($this->customer)
            ->for($oneTimeServiceProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::ONE_TIME_SUBSCRIPTION;
        $hos->sandwave_object_id = $oneTimeService->uuid;
        $hos->hubspot_object_id = 'any-fake-hubspot-one-time-server-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }

    #[Test]
    public function outdatedCustomerOfOneTimeServiceWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $hos->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $hos->hubspot_object_id = 'any-fake-hubspot-subscription-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();
        $hos->save();

        $oneTimeServiceProductGroup = new ProductGroupFactory()->oneTimeService()->createOne();

        $oneTimeServiceProduct = new ProductFactory()->for($oneTimeServiceProductGroup)->createOne();

        $oneTimeService = new OneTimeServiceFactory()
            ->for($subscription)
            ->for($this->customer)
            ->for($oneTimeServiceProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::ONE_TIME_SUBSCRIPTION;
        $hos->sandwave_object_id = $oneTimeService->uuid;
        $hos->hubspot_object_id = 'any-fake-hubspot-one-time-server-object-id';
        $hos->synced_at = CarbonImmutable::yesterday();

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }

    #[Test]
    public function newSubscriptionWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }

    #[Test]
    public function newOneTimeServiceWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $oneTimeServiceProductGroup = new ProductGroupFactory()->oneTimeService()->createOne();

        $oneTimeServiceProduct = new ProductFactory()->for($oneTimeServiceProductGroup)->createOne();

        new OneTimeServiceFactory()
            ->for($subscription)
            ->for($this->customer)
            ->for($oneTimeServiceProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }

    #[Test]
    public function outdatedSubscriptionWithPreviousSyncEventWillBeReturnedForSync(): void
    {
        $extensionProduct = new ProductFactory()->nlDomain()->createOne();

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($extensionProduct)
            ->createOne(
                [
                    'updated_at' => CarbonImmutable::yesterday()->addHour(),
                    'created_at' => CarbonImmutable::yesterday(),
                ],
            );

        CarbonImmutable::setTestNow(CarbonImmutable::now()->subWeek());

        $event = new HubspotEvent();
        $event->customer_id = $this->customer->id;
        $event->event = 'Synchronizing subscription to Hubspot';
        $event->message = 'Updated existing Hubspot subscription';
        $event->status = HubspotEventStatus::SUCCESS;
        $event->created_at = CarbonImmutable::now();
        $event->updated_at = CarbonImmutable::now();
        $event->save();

        $hos = new HubspotObjectSync();
        $hos->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $hos->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $hos->hubspot_object_id = 'any-fake-hubspot-subscription-object-id';
        $hos->synced_at = CarbonImmutable::now();
        $hos->save();

        CarbonImmutable::setTestNow(CarbonImmutable::now());

        $customerIds = $this->repository->getCustomerIdsReadyToBeSyncedByUpdatedAt();

        self::assertNotNull(
            array_find(
                $customerIds,
                fn ($record): bool => $record->id === $this->customer->id,
            ),
        );
    }
}
