<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Services;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionTerminateService;
use Waterfront\Domain\Transfers\Mailer\MailTransferAwayCompleted;

#[CoversClass(SubscriptionTerminateService::class)]
class CancelSubscriptionsTest extends IntegrationTestCase
{
    private const string DOMAIN = 'test.com';

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $group = new ProductGroupFactory()->extension()->createOne();
        $product = new ProductFactory()->for($group)->createOne();
        $this->subscription = new SubscriptionFactory()->withCustomer()->for($product)->createOne([
            'domain' => self::DOMAIN,
            'administrative_status' => AdministrativeStatus::ACTIVE->value,
            'technical_status' => TechnicalStatus::OK->value,
        ]);
    }

    #[Test]
    public function endTransferredSubscription(): void
    {
        self::assertEmailsSend([
            MailTransferAwayCompleted::class,
        ]);
        $service = self::resolve(SubscriptionTerminateService::class);
        $service->endTransferredSubscription(self::DOMAIN);

        $this->subscription->refresh();
        Assert::assertSame(AdministrativeStatus::ARCHIVED->value, $this->subscription->administrative_status);
        Assert::assertSame(TechnicalStatus::DELETED->value, $this->subscription->technical_status);
    }
}
