<?php

declare(strict_types=1);

namespace Tests\Apps\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Subscriptions\SendManualTerminationReminder;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledReminderManualSubscription;

#[CoversClass(SendManualTerminationReminder::class)]
class SendManualTerminationReminderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Model::preventLazyLoading(false);
    }

    #[Test]
    public function sendManualTerminationReminder(): void
    {
        self::assertEmailsSend([
            CanceledReminderManualSubscription::class,
            CanceledReminderManualSubscription::class,
        ]);

        $productGroup = new ProductGroupFactory()->manualSubscription();
        $product = new ProductFactory()->for($productGroup)->createOne();

        $hostingProductGroup = new ProductGroupFactory()->hosting();
        $hostingProduct = new ProductFactory()->for($hostingProductGroup)->createOne();

        foreach ([$product, $product, $hostingProduct] as $p) {
            new SubscriptionFactory()
                ->withCustomer()
                ->for($p)
                ->administrativeStatusArchived()
                ->technicalStatusDomainActive()
                ->createOne();
        }

        $this->artisan(SendManualTerminationReminder::class)
            ->expectsOutput('Sending 2 notifications for manual subscriptions')
            ->assertExitCode(0);
    }

    #[Test]
    public function sendManualTerminationReminderWithOutResult(): void
    {
        $this->artisan(SendManualTerminationReminder::class)
            ->expectsOutput('There where no notifications to send.')
            ->assertExitCode(0);
    }
}
