<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Console\Commands\Subscriptions\AdministrativelyExpireSubscriptions;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(AdministrativelyExpireSubscriptions::class)]
class SubscriptionExpireTest extends IntegrationTestCase
{
    protected Product $product;

    public function setUp(): void
    {
        parent::setUp();

        $extensionGroup = new ProductGroupFactory()->extension();

        $this->product = new ProductFactory()->for($extensionGroup)->createOne();
        new ProductSpecFactory()->for($this->product)->createOne(['name' => 'services.technical_grace_period', 'value' => 30]);
    }

    #[Test]
    public function expireSubscriptionsSucceeds(): void
    {
        $now = new CarbonImmutable();
        CarbonImmutable::setTestNow($now);

        $domainProvider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::PLACEHOLDER, 'default' => false, 'enabled' => true]);
        $canceledSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)->administrativeStatusCancelled()->createOne(['end_date' => $now->subDay()]);
        new DomainDeploymentFactory()->for($domainProvider, 'provider')->for($canceledSubscription)->createOne();
        $activeSubscription = new SubscriptionFactory()->withCustomer()->for($this->product)->administrativeStatusActive()->createOne();

        $this->artisan(AdministrativelyExpireSubscriptions::class);

        $canceledSubscription->refresh();
        $activeSubscription->refresh();

        self::assertSame(AdministrativeStatus::EXPIRED->value, $canceledSubscription->administrative_status);
        self::assertSame($now->addDays(30)->format(DateTimeFormat::DATE), $canceledSubscription->termination_date?->format(DateTimeFormat::DATE));
    }
}
