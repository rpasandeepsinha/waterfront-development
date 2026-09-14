<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\Actions;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Orders\Actions\ProcessOrderLineItemAction;
use Waterfront\Domain\Orders\DTO\ProcessOrderLineItemDTO;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Exceptions\OrderLineItemNotProcessableException;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;

#[CoversClass(ProcessOrderLineItemAction::class)]
class ProcessOrderLineItemActionTest extends IntegrationTestCase
{
    private Customer $customer;

    private Product $subscriptionProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->subscriptionProduct = new ProductFactory()->for(
            new ProductGroupFactory()->other()->createOne(),
        )->createOne();
    }

    #[Test]
    public function createsSubscriptionFromLineItemAndAppliesGivenStatuses(): void
    {
        CarbonImmutable::setTestNow('2026-08-17 12:00:00');

        $orderLineItem = $this->buildOrderLineItem(ProductGroupType::HOSTING);
        $subscription = $this->buildSubscription();

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService
            ->expects(self::once())
            ->method('createSubscriptionFromOrderLineItem')
            ->with($orderLineItem, true)
            ->willReturn($subscription);

        $oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $oneTimeServiceCreator->expects(self::never())->method('createFromOrderLineItem');

        $this->buildAction($subscriptionService, $oneTimeServiceCreator)->execute(
            $orderLineItem,
            new ProcessOrderLineItemDTO(true, AdministrativeStatus::SUSPENDED, TechnicalStatus::PENDING, null),
        );

        $subscription->refresh();
        self::assertSame(AdministrativeStatus::SUSPENDED->value, $subscription->administrative_status);
        self::assertSame(TechnicalStatus::PENDING->value, $subscription->technical_status);
        self::assertNull($subscription->parent_subscription_id);

        $orderLineItem->refresh();
        self::assertSame($subscription->uuid, $orderLineItem->subscription_uuid);
        self::assertNotNull($orderLineItem->processed_at);
        self::assertSame(
            '2026-08-17 12:00:00',
            CarbonImmutable::parse($orderLineItem->processed_at)->toDateTimeString(),
        );
    }

    #[Test]
    public function associatesTheParentSubscriptionWhenAParentIdIsGiven(): void
    {
        $orderLineItem = $this->buildOrderLineItem(ProductGroupType::HOSTING);
        $subscription = $this->buildSubscription();
        $parentSubscription = $this->buildSubscription();

        $subscriptionService = self::createStub(SubscriptionService::class);
        $subscriptionService->method('createSubscriptionFromOrderLineItem')->willReturn($subscription);

        $this->buildAction($subscriptionService, self::createStub(OneTimeServiceCreator::class))->execute(
            $orderLineItem,
            new ProcessOrderLineItemDTO(
                false,
                AdministrativeStatus::ACTIVE,
                TechnicalStatus::OK,
                $parentSubscription->id,
            ),
        );

        $subscription->refresh();
        self::assertSame($parentSubscription->id, $subscription->parent_subscription_id);
    }

    #[Test]
    public function handsOneTimeServiceLineItemsToTheOneTimeServiceCreatorAndIgnoresTheStatuses(): void
    {
        $orderLineItem = $this->buildOrderLineItem(ProductGroupType::ONE_TIME_SERVICE);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('createSubscriptionFromOrderLineItem');

        $oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $oneTimeServiceCreator->expects(self::once())->method('createFromOrderLineItem')->with($orderLineItem);

        $this->buildAction($subscriptionService, $oneTimeServiceCreator)->execute(
            $orderLineItem,
            new ProcessOrderLineItemDTO(true, AdministrativeStatus::SUSPENDED, TechnicalStatus::PENDING, null),
        );
    }

    #[Test]
    public function throwsWhenTheLineItemWasAlreadyProcessed(): void
    {
        $orderLineItem = $this->buildOrderLineItem(ProductGroupType::HOSTING);
        $orderLineItem->processed_at = CarbonImmutable::now();
        $orderLineItem->save();

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('createSubscriptionFromOrderLineItem');

        $this->expectException(OrderLineItemNotProcessableException::class);

        try {
            $this->buildAction($subscriptionService, self::createStub(OneTimeServiceCreator::class))->execute(
                $orderLineItem,
                new ProcessOrderLineItemDTO(false, AdministrativeStatus::ACTIVE, TechnicalStatus::OK, null),
            );
        } catch (OrderLineItemNotProcessableException $exception) {
            self::assertSame('nova-action.process_order_line_item.already_processed', $exception->translationKey);
            throw $exception;
        }
    }

    #[Test]
    #[DataProvider('blockingOrderStatusProvider')]
    public function throwsWhenTheOrderStatusDoesNotAllowProcessing(OrderStatus $orderStatus): void
    {
        $orderLineItem = $this->buildOrderLineItem(ProductGroupType::HOSTING, $orderStatus);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('createSubscriptionFromOrderLineItem');

        $this->expectException(OrderLineItemNotProcessableException::class);

        try {
            $this->buildAction($subscriptionService, self::createStub(OneTimeServiceCreator::class))->execute(
                $orderLineItem,
                new ProcessOrderLineItemDTO(false, AdministrativeStatus::ACTIVE, TechnicalStatus::OK, null),
            );
        } catch (OrderLineItemNotProcessableException $exception) {
            self::assertSame('nova-action.process_order_line_item.invalid_status', $exception->translationKey);
            throw $exception;
        }
    }

    /**
     * @return array<string, array{OrderStatus}>
     */
    public static function blockingOrderStatusProvider(): array
    {
        return [
            'on hold' => [OrderStatus::ON_HOLD],
            'abuse' => [OrderStatus::ABUSE],
        ];
    }

    #[Test]
    public function throwsBeforeCreatingASubscriptionWhenTheParentSubscriptionDoesNotExist(): void
    {
        $orderLineItem = $this->buildOrderLineItem(ProductGroupType::HOSTING);

        $subscriptionService = self::createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('createSubscriptionFromOrderLineItem');

        $this->expectException(OrderLineItemNotProcessableException::class);

        try {
            $this->buildAction($subscriptionService, self::createStub(OneTimeServiceCreator::class))->execute(
                $orderLineItem,
                new ProcessOrderLineItemDTO(false, AdministrativeStatus::ACTIVE, TechnicalStatus::OK, 999999),
            );
        } catch (OrderLineItemNotProcessableException $exception) {
            self::assertSame('nova-action.error.parent_subscription_not_exists', $exception->translationKey);

            $orderLineItem->refresh();
            self::assertNull($orderLineItem->processed_at);

            throw $exception;
        }
    }

    private function buildAction(
        SubscriptionService $subscriptionService,
        OneTimeServiceCreator $oneTimeServiceCreator,
    ): ProcessOrderLineItemAction {
        return new ProcessOrderLineItemAction(
            $subscriptionService,
            $oneTimeServiceCreator,
            self::resolve(SubscriptionRepository::class),
        );
    }

    private function buildOrderLineItem(
        ProductGroupType $productGroupType,
        ?OrderStatus $orderStatus = null,
    ): OrderLineItem {
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => $productGroupType->value,
            'slug' => $productGroupType->value,
        ]);
        $product = new ProductFactory()->for($productGroup)->createOne();

        $order = new OrderFactory()->for($this->customer)->createOne([
            'status' => $orderStatus ?? OrderStatus::IN_PROGRESS,
        ]);

        return new OrderLineItemFactory()
            ->for($order)
            ->for($product)
            ->createOne(['subscription_uuid' => null]);
    }

    private function buildSubscription(): Subscription
    {
        return new SubscriptionFactory()
            ->for($this->subscriptionProduct)
            ->for($this->customer)
            ->administrativeStatusActive()
            ->createOne();
    }
}
