<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Log\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\Factories\CustomerFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Marketing\Enums\HubspotObjectType;
use Waterfront\Domain\Marketing\Factory\HubspotSubscriptionFactory;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventStatus;
use Waterfront\Domain\Marketing\HubspotSynchronizer;
use Waterfront\Domain\Marketing\Models\HubspotEvent;
use Waterfront\Domain\Marketing\Models\HubspotObjectSync;
use Waterfront\Domain\Marketing\Repositories\HubspotRepository;
use Waterfront\Domain\OneTimeServices\Repositories\OneTimeServiceRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\HubspotClient\DTO\HubspotConfigDTO;
use Waterfront\Infra\HubspotClient\DTO\HubspotSubscriptionDTO;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\SubscriptionClient;

#[CoversClass(HubspotSynchronizer::class)]
class HubspotSynchronizerTest extends IntegrationTestCase
{
    #[Test]
    public function runSyncCreateBatch(): void
    {
        $customer = new CustomerFactory()->createOne();
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        $hubspotDto = $this->buildHubspotSubscriptionDto($subscription);

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->once())
            ->method('createBatch')
            ->willReturn([$hubspotDto]);
        $subscriptionClientMock->expects($this->never())
            ->method('updateBatch');

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );

        $synchronizer->runSync();

        $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => $hubspotDto->hubspotId]);
    }

    #[Test]
    public function runSyncUpdateBatch(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $customer = new CustomerFactory()->createOne();
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        $object = new HubspotObjectSync();
        $object->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $object->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $object->hubspot_object_id = 'hubspot-id';
        $object->synced_at = CarbonImmutable::now()->subWeek();
        $object->save();

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->never())
            ->method('createBatch');
        $subscriptionClientMock->expects($this->once())
            ->method('updateBatch')->with(
                self::callback(function (array $data) {
                    self::assertContainsOnlyInstancesOf(HubspotSubscriptionDTO::class, $data);
                    self::assertCount(1, $data);

                    return true;
                })
            );

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );

        $synchronizer->runSync();

        $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => 'hubspot-id', 'synced_at' => CarbonImmutable::now()]);
    }

    #[Test]
    public function batchStillRunsWithOldFailedEvent(): void
    {
        $customer = new CustomerFactory()->createOne(['updated_at' => CarbonImmutable::now()->addDay()]);
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->subWeek());
        $event = new HubspotEvent();
        $event->customer_id = $customer->id;
        $event->status = HubspotEventStatus::FAILED;
        $event->event = 'batch create';
        $event->message = 'some message';
        $event->created_at = CarbonImmutable::now();
        $event->updated_at = CarbonImmutable::now();
        $event->save();

        CarbonImmutable::setTestNow();
        $object = new HubspotObjectSync();
        $object->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $object->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $object->hubspot_object_id = 'hubspot-id';
        $object->synced_at = CarbonImmutable::now();
        $object->save();

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->never())
            ->method('createBatch');
        $subscriptionClientMock->expects($this->once())
            ->method('updateBatch')->with(
                self::callback(function (array $data) {
                    self::assertTrue($data[0]->switchContact);
                    self::assertContainsOnlyInstancesOf(HubspotSubscriptionDTO::class, $data);
                    self::assertCount(1, $data);

                    return true;
                })
            );

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );

        $synchronizer->runSync();

        $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => 'hubspot-id', 'synced_at' => CarbonImmutable::now()]);
    }

    #[Test]
    public function batchDoesNotRunWithRecentFailedEvent(): void
    {
        $customer = new CustomerFactory()->createOne(['updated_at' => CarbonImmutable::now()]);
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        $event = new HubspotEvent();
        $event->customer_id = $customer->id;
        $event->status = HubspotEventStatus::FAILED;
        $event->event = 'batch create';
        $event->message = 'some message';
        $event->created_at = CarbonImmutable::now();
        $event->updated_at = CarbonImmutable::now();
        $event->save();

        $object = new HubspotObjectSync();
        $object->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $object->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $object->hubspot_object_id = 'hubspot-id';
        $object->synced_at = CarbonImmutable::now();
        $object->save();

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->never())
            ->method('createBatch');
        $subscriptionClientMock->expects($this->never())
            ->method('updateBatch');

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );

        $synchronizer->runSync();

        $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => 'hubspot-id', 'synced_at' => CarbonImmutable::now()]);
    }

    #[Test]
    public function customerUpdatedAtChangedShouldSyncSubscriptions(): void
    {
        $customer = new CustomerFactory()->createOne(['updated_at' => CarbonImmutable::now()->addDay()]);
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        $object = new HubspotObjectSync();
        $object->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $object->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $object->hubspot_object_id = 'hubspot-id';
        $object->synced_at = CarbonImmutable::now();
        $object->save();

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->never())
            ->method('createBatch');
        $subscriptionClientMock->expects($this->once())
            ->method('updateBatch')->with(
                self::callback(function (array $data) {
                    self::assertTrue($data[0]->switchContact);
                    self::assertContainsOnlyInstancesOf(HubspotSubscriptionDTO::class, $data);
                    self::assertCount(1, $data);

                    return true;
                })
            );

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );

        $synchronizer->runSync();

        $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => 'hubspot-id', 'synced_at' => CarbonImmutable::now()]);
    }

    #[Test]
    public function createBatchThrowsException(): void
    {
        $customer = new CustomerFactory()->createOne();
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->once())
            ->method('createBatch')
            ->willThrowException(new HubspotConflictException());
        $subscriptionClientMock->expects($this->never())
            ->method('updateBatch');

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );

        $synchronizer->runSync();

        $this->assertDatabaseMissing('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => 'hubspot-id']);
    }

    #[Test]
    public function updateBatchThrowsException(): void
    {
        $oldTime = CarbonImmutable::now();
        $lastWeek = $oldTime->subWeek();
        CarbonImmutable::setTestNow($lastWeek);
        $customer = new CustomerFactory()->createOne(['updated_at' => $oldTime]);
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);

        $object = new HubspotObjectSync();
        $object->sandwave_object_id = Uuid::fromString($subscription->uuid);
        $object->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $object->hubspot_object_id = 'hubspot-id';
        $object->synced_at = $lastWeek;
        $object->save();

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->never())
            ->method('createBatch');
        $subscriptionClientMock->expects($this->once())
            ->method('updateBatch')->willThrowException(new HubspotConflictException());

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            self::resolve(HubspotConfigDTO::class),
        );
        CarbonImmutable::setTestNow($oldTime);
        $synchronizer->runSync();

        $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $subscription->uuid, 'hubspot_object_id' => 'hubspot-id', 'synced_at' => $lastWeek->format('Y-m-d H:i:s')]);
    }

    #[Test]
    public function createBatchExecutesMultipleCallsIfBatchExceedsMaxSize(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $customer = new CustomerFactory()->createOne();
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);
        $subscriptions = new SubscriptionFactory()->for($customer)->for($subscription->product)->createMany(2);
        $subscriptions->add($subscription);

        $hubspotObjects = [];

        foreach ($subscriptions as $item) {
            $hubspotObjects[] = $this->buildHubspotSubscriptionDto($item);
        }

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->exactly(3))
            ->method('createBatch')->with(
                self::callback(function (array $data) {
                    self::assertContainsOnlyInstancesOf(HubspotSubscriptionDTO::class, $data);
                    self::assertCount(1, $data);

                    return true;
                })
            )->willReturnOnConsecutiveCalls(
                [$hubspotObjects[0]],
                [$hubspotObjects[1]],
                [$hubspotObjects[2]],
            );
        $subscriptionClientMock->expects($this->never())
            ->method('updateBatch');

        $config = new HubspotConfigDTO(
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            1,
            1
        );

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            $config,
        );

        $synchronizer->runSync();

        foreach ($subscriptions as $item) {
            $hubspotId = $item->uuid . 'hubspot-id';
            $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $item->uuid, 'hubspot_object_id' => $hubspotId, 'synced_at' => CarbonImmutable::now()]);
        }
    }

    #[Test]
    public function updateBatchExecutesMultipleCallsIfBatchExceedsMaxSize(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::now());
        $customer = new CustomerFactory()->createOne();
        $subscription = DomainSubscriptionDataProvider::subscription(customer: $customer);
        $subscriptions = new SubscriptionFactory()->for($customer)->for($subscription->product)->createMany(2);
        $subscriptions->add($subscription);

        foreach ($subscriptions as $item) {
            $object = new HubspotObjectSync();
            $object->sandwave_object_id = Uuid::fromString($item->uuid);
            $object->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
            $object->hubspot_object_id = $item->uuid . 'hubspot-id';
            $object->synced_at = CarbonImmutable::now()->subWeek();
            $object->save();
        }

        $subscriptionClientMock = $this->createMock(SubscriptionClient::class);
        $subscriptionClientMock->expects($this->never())
            ->method('createBatch');
        $subscriptionClientMock->expects($this->exactly(3))
            ->method('updateBatch')->with(
                self::callback(function (array $data) {
                    self::assertContainsOnlyInstancesOf(HubspotSubscriptionDTO::class, $data);
                    self::assertCount(1, $data);

                    return true;
                })
            );

        $config = new HubspotConfigDTO(
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            1,
            1
        );

        $synchronizer = new HubspotSynchronizer(
            self::resolve(HubspotRepository::class),
            $subscriptionClientMock,
            self::resolve(SubscriptionRepository::class),
            self::resolve(OneTimeServiceRepository::class),
            self::resolve(HubspotSubscriptionFactory::class),
            self::resolve(HubspotEventRepository::class),
            self::resolve(Logger::class),
            $config,
        );

        $synchronizer->runSync();

        foreach ($subscriptions as $item) {
            $hubspotId = $item->uuid . 'hubspot-id';
            $this->assertDatabaseHas('hubspot_object_sync', ['sandwave_object_id' => $item->uuid, 'hubspot_object_id' => $hubspotId, 'synced_at' => CarbonImmutable::now()]);
        }
    }

    private function buildHubspotSubscriptionDto(Subscription $subscription): HubspotSubscriptionDTO
    {
        return new HubspotSubscriptionDTO(
            hubspotId: $subscription->uuid . 'hubspot-id',
            sandwaveId: (string) $subscription->id,
            uuid: $subscription->uuid,
            parentSubscriptionId: null,
            domain: null,
            administrativeStatus: null,
            technicalStatus: null,
            startDate: null,
            endDate: null,
            cancelDate: null,
            cancelReason: null,
            grossPrice: null,
            netPrice: null,
            billingPeriod: null,
            contractPeriod: null,
            productGroupName: null,
            productGroupSlug: null,
            productName: null,
            productSlug: null,
            customerNumber: null,
            nextBillingDate: null,
            swOrderUuid: null,
            otsAmount: null,
            otsDiscountPercentage: null,
            otsExecutionDate: null,
            otsStatus: null,
            cancellationFlowReason: null,
            switchContact: false,
        );
    }
}
