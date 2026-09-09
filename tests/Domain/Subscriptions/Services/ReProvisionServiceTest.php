<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Contracts\Bus\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionChangeFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\SubscriptionMutationFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Backup\Jobs\UpgradeBackupJob;
use Waterfront\Domain\Hosting\Jobs\DowngradeHostingJob;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionChangeRepository;
use Waterfront\Domain\Subscriptions\Services\ReProvisionService;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(ReProvisionService::class)]
class ReProvisionServiceTest extends IntegrationTestCase
{
    private ReProvisionService $service;

    private Dispatcher&MockObject $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = self::createMock(Dispatcher::class);

        $this->service = new ReProvisionService(
            $this->dispatcher,
            self::resolve(SubscriptionChangeRepository::class),
            self::resolve(LoggerInterface::class),
        );
    }

    #[Test]
    public function reProvisionDomainAndThrowsException(): void
    {
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->withAddress())
            ->for(new ProductFactory()->for(new ProductGroupFactory()->extension()))
            ->createOne();

        $mutation = new SubscriptionMutationFactory()->for($subscription)->for($subscription->product)->createOne();

        $this->dispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(NotImplementedException::class);
        $this->service->reProvisionSubscription($subscription, $mutation);
    }

    #[Test]
    public function reProvisionHostingAndDispatchesJob(): void
    {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $newProduct = new ProductFactory()->for($hostingGroup)->createOne();
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->withAddress())
            ->for(new ProductFactory()->for($hostingGroup))
            ->createOne();

        $mutation = new SubscriptionMutationFactory()->for($subscription)->for($subscription->product)->createOne();
        new SubscriptionChangeFactory()->for($subscription)->createOne([
            'subscription_uuid' => $subscription->uuid,
            'from_product_uuid' => $subscription->product->uuid,
            'to_product_uuid' => $newProduct->uuid,
            'type' => ProductChangeType::DOWNGRADE,
            'status' => SubscriptionChangeStatus::REQUESTED,
            'completed_at' => null,
            'requested_at' => CarbonImmutable::now()->subYear(),
        ]);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')->with($this->isInstanceOf(DowngradeHostingJob::class));

        $this->service->reProvisionSubscription($subscription, $mutation);
    }

    #[Test]
    public function reProvisionHostingButNotDowngradeSoDoesntDispatchJob(): void
    {
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory()->withAddress())
            ->for(new ProductFactory()->for($hostingGroup))
            ->createOne();

        $mutation = new SubscriptionMutationFactory()->for($subscription)->for($subscription->product)->createOne();

        $this->dispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(Exception::class);
        $this->service->reProvisionSubscription($subscription, $mutation);
    }

    #[Test]
    public function reProvisionBackupAndDispatchesJob(): void
    {
        $backupGroup = new ProductGroupFactory()->backup()->createOne();
        $newProduct = new ProductFactory()->for($backupGroup)->createOne([
            'name' => 'Backup 100',
            'slug' => 'backup-100',
        ]);
        new ProductSpecFactory()
            ->for($newProduct)
            ->createMany([
                [
                    'name'  => ProductSpecName::ACRONIS_CLOUD_STORAGE_GB->value,
                    'value' => '100',
                ],
                [
                    'name'  => ProductSpecName::ACRONIS_LOCAL_STORAGE_GB->value,
                    'value' => '100',
                ],
                [
                    'name'  => ProductSpecName::ACRONIS_MOBILE_DEVICES->value,
                    'value' => '15',
                ],
                [
                    'name'  => ProductSpecName::ACRONIS_WORKSTATIONS->value,
                    'value' => '10',
                ],
                [
                    'name'  => ProductSpecName::ACRONIS_VMS->value,
                    'value' => '20',
                ],
                [
                    'name'  => ProductSpecName::ACRONIS_SERVERS->value,
                    'value' => '5',
                ],
            ]);
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->backupAcronis()->for($backupGroup))
            ->createOne();

        $mutation = new SubscriptionMutationFactory()->for($subscription)->for($subscription->product)->createOne();
        new SubscriptionChangeFactory()->for($subscription)->createOne([
            'subscription_uuid' => $subscription->uuid,
            'from_product_uuid' => $subscription->product->uuid,
            'to_product_uuid' => $newProduct->uuid,
            'type' => ProductChangeType::UPGRADE,
            'status' => SubscriptionChangeStatus::REQUESTED,
            'completed_at' => null,
            'requested_at' => CarbonImmutable::now()->subYear(),
        ]);

        $this->dispatcher
            ->expects(self::once())
            ->method('dispatch')->with($this->isInstanceOf(UpgradeBackupJob::class));

        $this->service->reProvisionSubscription($subscription, $mutation);
    }

    #[Test]
    public function reProvisionBackupButNotUpgradeSoDoesntDispatchJob(): void
    {
        $backupGroup = new ProductGroupFactory()->backup()->createOne();
        $subscription = new SubscriptionFactory()
            ->for(new CustomerFactory())
            ->for(new ProductFactory()->backupAcronis()->for($backupGroup))
            ->createOne();

        $mutation = new SubscriptionMutationFactory()->for($subscription)->for($subscription->product)->createOne();

        $this->dispatcher
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(Exception::class);
        $this->service->reProvisionSubscription($subscription, $mutation);
    }
}
