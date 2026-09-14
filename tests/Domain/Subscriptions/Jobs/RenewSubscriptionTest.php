<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Repositories\PriceRepository;
use Waterfront\Domain\Pricing\Services\PricePersistService;
use Waterfront\Domain\Products\Enums\CustomPriceReasonType;
use Waterfront\Domain\Subscriptions\Actions\GetRenewalInfoAction;
use Waterfront\Domain\Subscriptions\DTO\RenewalInfoDTO;
use Waterfront\Domain\Subscriptions\Events\SubscriptionRenewedEvent;
use Waterfront\Domain\Subscriptions\Jobs\RenewSubscription;
use Waterfront\Domain\Subscriptions\Repositories\ProductAllowedChangeRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionChangeService;
use Waterfront\Domain\Subscriptions\Services\SubscriptionRenewService;

#[CoversClass(RenewSubscription::class)]
#[CoversClass(SubscriptionRenewService::class)]
class RenewSubscriptionTest extends IntegrationTestCase
{
    #[Test]
    public function normalSubscriptionRenewalUpdatesEndDateAndPrice(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $billingPeriod = 12;
        $contractPeriod = 12;

        $product = new ProductFactory()
            ->for(new ProductGroupFactory()->hosting()->createOne())
            ->nlDomain()
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->administrativeStatusActive()
            ->createOneQuietly([
                'contract_period' => $contractPeriod,
                'next_billing_date' => $now,
                'end_date' => $now,
                'net_price' => 100,
                'gross_price' => 100,
            ]);

        $renewalInfoAction = self::createMock(GetRenewalInfoAction::class);
        $renewalInfoAction
            ->expects(self::once())
            ->method('execute')
            ->willReturn(
                new RenewalInfoDTO(
                    $subscription->product,
                    $contractPeriod,
                    $billingPeriod,
                    500,
                    500,
                    CarbonImmutable::now(),
                    CarbonImmutable::now()->addMonths(12),
                ),
            );

        $subscriptionRenewService = new SubscriptionRenewService(
            $renewalInfoAction,
            self::resolve(LoggerInterface::class),
            self::createStub(SubscriptionChangeService::class),
            self::createStub(ProductAllowedChangeRepository::class),
            self::resolve(PricePersistService::class),
            self::resolve(PriceRepository::class),
        );

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new SubscriptionRenewedEvent($subscription));

        new RenewSubscription($subscription)->handle($subscriptionRenewService, $eventDispatcher);

        self::assertSame($product->id, $subscription->product->id);
        self::assertSame($contractPeriod, $subscription->contract_period);
        self::assertSame($billingPeriod, $subscription->billing_period);
        self::assertSame(
            $now->addMonths($contractPeriod)->startOfDay()->getTimestamp(),
            $subscription->end_date->startOfDay()->getTimestamp(),
        );
        self::assertSame(
            $now->startOfDay()->getTimestamp(),
            $subscription->next_billing_date->startOfDay()->getTimestamp(),
        );
        self::assertSame(500, $subscription->gross_price);
        self::assertSame(500, $subscription->net_price);
    }

    #[Test]
    public function subscriptionRenewalWithDowngradeAsMutation(): void
    {
        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $billingPeriod = 12;
        $contractPeriod = 12;

        $product = new ProductFactory()
            ->for(new ProductGroupFactory()->hosting()->createOne())
            ->nlDomain()
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->administrativeStatusActive()
            ->createOneQuietly([
                'contract_period' => $contractPeriod,
                'next_billing_date' => $now,
                'end_date' => $now,
                'net_price' => 100,
                'gross_price' => 100,
            ]);

        $renewalInfoAction = self::createMock(GetRenewalInfoAction::class);
        $renewalInfoAction
            ->expects(self::once())
            ->method('execute')
            ->willReturn(
                new RenewalInfoDTO(
                    $subscription->product,
                    $contractPeriod,
                    $billingPeriod,
                    500,
                    500,
                    CarbonImmutable::now(),
                    CarbonImmutable::now()->addMonths(12),
                ),
            );

        $subscriptionRenewService = new SubscriptionRenewService(
            $renewalInfoAction,
            self::resolve(LoggerInterface::class),
            self::createStub(SubscriptionChangeService::class),
            self::createStub(ProductAllowedChangeRepository::class),
            self::resolve(PricePersistService::class),
            self::resolve(PriceRepository::class),
        );

        $eventDispatcher = self::createMock(Dispatcher::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(new SubscriptionRenewedEvent($subscription));

        new RenewSubscription($subscription)->handle($subscriptionRenewService, $eventDispatcher);

        self::assertSame($product->id, $subscription->product->id);
        self::assertSame($contractPeriod, $subscription->contract_period);
        self::assertSame($billingPeriod, $subscription->billing_period);
        self::assertSame(
            $now->addMonths($contractPeriod)->startOfDay()->getTimestamp(),
            $subscription->end_date->startOfDay()->getTimestamp(),
        );
        self::assertSame(
            $now->startOfDay()->getTimestamp(),
            $subscription->next_billing_date->startOfDay()->getTimestamp(),
        );
        self::assertSame(500, $subscription->gross_price);
        self::assertSame(500, $subscription->net_price);
    }

    #[Test]
    public function subscriptionRenewalWithIndefinitePrice(): void
    {
        $pricePersistService = self::resolve(PricePersistService::class);
        $subscriptionRenewService = self::resolve(SubscriptionRenewService::class);

        $now = CarbonImmutable::now();
        CarbonImmutable::setTestNow($now);

        $product = new ProductFactory()->for(new ProductGroupFactory()->createOne())->createOne();

        new ProductPriceComponentFactory()
            ->for($product)
            ->registration()
            ->createOne();

        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->administrativeStatusActive()
            ->createOneQuietly([
                'contract_period' => 12,
                'next_billing_date' => $now,
                'end_date' => $now,
                'net_price' => 100,
                'gross_price' => 100,
            ]);

        $pricePersistService->persistCustomPrice(
            $subscription,
            123,
            false,
            CustomPriceReasonType::FIXED_MIGRATION_PRICE,
        );

        new RenewSubscription($subscription)->handle($subscriptionRenewService, self::resolve(Dispatcher::class));

        self::assertSame(
            $now->addMonths(12)->startOfDay()->getTimestamp(),
            $subscription->end_date->startOfDay()->getTimestamp(),
        );
        self::assertSame(
            $now->startOfDay()->getTimestamp(),
            $subscription->next_billing_date->startOfDay()->getTimestamp(),
        );
        self::assertSame(100, $subscription->gross_price);

        // The price version hasn't been updated and the custom indefinite price component is still valid.
        self::assertSame(123, $subscription->net_price);
        self::assertCount(1, $subscription->prices);
        self::assertSame(
            PriceComponentType::CUSTOM_INDEFINITE,
            $subscription->activePrice?->components->firstOrFail()->type,
        );
    }
}
