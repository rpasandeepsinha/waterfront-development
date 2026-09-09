<?php

declare(strict_types=1);

namespace Tests\Domain\ResellerHosting\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ResellerHostingDeploymentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Jobs\TerminateResellerHostingJob;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelReason;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Mailer\MailSubscriptionCancelled;
use Waterfront\Domain\Subscriptions\Services\CancellationService;

#[CoversClass(TerminateResellerHostingJob::class)]
class ResellerHostingSubscriptionTerminateTest extends IntegrationTestCase
{
    private Customer $customer;

    private Provider $hostingProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        $this->actingAsCustomer($this->customer);

        $this->hostingProvider = ProviderFactory::new()->createOne(['type' => ProviderType::HOSTING, 'slug' => ProviderSlug::DIRECTADMIN, 'enabled' => true, 'default' => true]);
    }

    #[Test]
    public function resellerHostingSubscriptionCancel(): void
    {
        self::assertEmailsSend([
            MailSubscriptionCancelled::class,
        ]);
        $cancellationService = self::resolve(CancellationService::class);

        $resellerProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::RESELLER_HOSTING,
            'name' => 'Reseller Hosting',
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $resellerProductGroup->id,
            'name' => 'reseller-brons',
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($product)
            ->createOne();

        new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $this->hostingProvider->id,
        ]);

        $cancellationService->cancel($subscription, SubscriptionCancelType::CANCEL_END_DATE, SubscriptionCancelReason::REASON_CANCELLATION);

        $subscription->refresh();

        self::assertSame(AdministrativeStatus::CANCELED->value, $subscription->administrative_status);
    }

    #[Test]
    public function resellerHostingTerminationEvent(): void
    {
        $resellerProductGroup = new ProductGroupFactory()->createOne([
            'slug' => ProductGroupType::RESELLER_HOSTING,
            'name' => 'Reseller Hosting',
        ]);

        $resellerProduct = new ProductFactory()->createOne([
            'product_group_id' => $resellerProductGroup->id,
            'name' => 'Reseller Brons',
            'slug' => 'hosting_reseller_brons',
        ]);

        $subscription = new SubscriptionFactory()->withCustomer()->createOne([
            'domain' => null,
            'product_uuid'       => $resellerProduct->uuid,
            'technical_status'   => TechnicalStatus::OK->value,
        ]);

        $resellerHostingDeployment = new ResellerHostingDeploymentFactory()->createOne([
            'subscription_uuid' => $subscription->uuid,
            'directadmin_customer_username' => 'Reseller',
            'provider_id' => $this->hostingProvider->id,
        ]);

        $job = new TerminateResellerHostingJob('testkees', 'test@test.nl', $resellerHostingDeployment);

        $job->handle(self::resolve(ResellerHostingService::class), self::resolve(LoggerInterface::class));

        $subscription->refresh();

        self::assertSame(TechnicalStatus::DELETED->value, $subscription->technical_status);
    }
}
