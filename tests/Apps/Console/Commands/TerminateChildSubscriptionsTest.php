<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\Factories\Microsoft365DeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Subscriptions\TerminateSubscriptions as TerminateSubscriptionCommand;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Jobs\TerminateSubscription;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Support\Enums\QueueName;

#[CoversClass(TerminateSubscriptionCommand::class)]
class TerminateChildSubscriptionsTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);
    }

    #[Test]
    public function parentSubscriptionIsDispatchedToQueueForCanceledChildSubscription(): void
    {
        Queue::fake();
        CarbonImmutable::setTestNow();

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $dnsGroup = new ProductGroupFactory()->dns()->createOne();

        $nlProduct = new ProductFactory()->for($extensionGroup)->createOne();
        $dnsProduct = new ProductFactory()->for($dnsGroup)->createOne();

        $subscription = new SubscriptionFactory()->withCustomer()->for($nlProduct)->createOne([
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
            'termination_date' => null,
        ]);

        $childSubscription = new SubscriptionFactory()->withCustomer()->for($dnsProduct)->parentSubscription($subscription)->createOne([
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::SUSPENDED->value,
            'termination_date' => CarbonImmutable::now(),
        ]);

        $this->artisan(TerminateSubscriptionCommand::class);

        Queue::assertPushed(TerminateSubscription::class, 1);

        Queue::assertPushedOn(QueueName::SUBSCRIPTIONS->value, fn (TerminateSubscription $job) => $subscription->id === $job->subscription->id);

        Queue::assertNotPushed(fn (TerminateSubscription $job) => $childSubscription->id === $job->subscription->id);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function terminateMicrosoft365ChildSubscriptions(): void
    {
        $end = new DateTime()->modify('-1 days')->format(DateTimeFormat::DEFAULT);

        $productGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::MICROSOFT_365]);
        $product = new ProductFactory()->for($productGroup)->createOne();
        $productTwo = new ProductFactory()->for($productGroup)->createOne();

        $firstSubscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $product->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($firstSubscription->customer)->createOne([
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
        ]);
        new Microsoft365DeploymentFactory()->for($customerInfo)->createOne([
            'subscription_id' => $firstSubscription->id,
        ]);
        new SubscriptionFactory()->withCustomer()->for($product)->state([
            'parent_subscription_id' => $firstSubscription->id,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'end_date' => $end,
        ])->createMany(10);

        new SubscriptionFactory()->withCustomer()->for($product)->state([
            'parent_subscription_id' => $firstSubscription->id,
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'end_date' => $end,
            'termination_date' => $end,
        ])->createMany(5);

        $secondSubscription = new SubscriptionFactory()->withCustomer()->createOne([
            'product_uuid' => $productTwo->uuid,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
        ]);
        new Microsoft365DeploymentFactory()->for($customerInfo)->createOne([
            'subscription_id' => $secondSubscription->id,
        ]);
        new SubscriptionFactory()->withCustomer()->for($product)->state([
            'parent_subscription_id' => $secondSubscription->id,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'end_date' => $end,
        ])->createMany(5);
        new SubscriptionFactory()->withCustomer()->for($product)->state([
            'parent_subscription_id' => $secondSubscription->id,
            'administrative_status' => AdministrativeStatus::EXPIRED->value,
            'technical_status' => TechnicalStatus::OK->value,
            'end_date' => $end,
            'termination_date' => $end,
        ])->createMany(2);

        //Check if 7 expired subscriptions are in the database.
        $cancel_child_count = Subscription::where('administrative_status', AdministrativeStatus::EXPIRED->value)->whereNotNull('parent_subscription_id')->count();
        self::assertSame(7, $cancel_child_count);

        //Check if 15 active child subscriptions are in the database.
        $active_child_count = Subscription::where('administrative_status', AdministrativeStatus::ACTIVE->value)->whereNotNull('parent_subscription_id')->count();
        self::assertSame(15, $active_child_count);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::exactly(2))
            ->method('modifyOrder');
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $microsoft365Service);

        $this->artisan(TerminateSubscriptionCommand::class);

        //Check if 0 canceled subscription are in the database.
        $cancel_child_count = Subscription::where('administrative_status', AdministrativeStatus::ARCHIVED->value)->whereNotNull('parent_subscription_id')->count();
        self::assertSame(0, $cancel_child_count);

        //Check if 7 deleted subscription are in the database.
        $deleted_child_count = Subscription::where('administrative_status', AdministrativeStatus::ARCHIVING->value)->whereNotNull('parent_subscription_id')->count();
        self::assertSame(7, $deleted_child_count);

        //Check if 15 active child subscriptions are still in the database and untouched.
        $active_child_count = Subscription::where('administrative_status', AdministrativeStatus::ACTIVE->value)->whereNotNull('parent_subscription_id')->count();
        self::assertSame(15, $active_child_count);

        // Check if the parent subscriptions where not deleted.
        $active_parent_count = Subscription::where('administrative_status', AdministrativeStatus::ACTIVE->value)->whereNull('parent_subscription_id')->count();
        self::assertSame(2, $active_parent_count);
    }
}
