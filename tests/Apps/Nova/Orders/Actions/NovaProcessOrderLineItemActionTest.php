<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Orders\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OneTimeServiceFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\OrderLineItemFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Orders\Actions\NovaProcessOrderLineItemAction;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaProcessOrderLineItemAction::class)]
class NovaProcessOrderLineItemActionTest extends IntegrationTestCase
{
    #[Test]
    public function dangerMessageWhenOrderIsOnHold(): void
    {
        $order = new OrderFactory()
            ->for(new CustomerFactory())
            ->createOne([
                'status' => OrderStatus::ON_HOLD,
            ]);

        $otsGroup = new ProductGroupFactory()->oneTimeService()->createOne();
        $otsProduct = new ProductFactory()->for($otsGroup)->createOne([
            'name' => 'Domain reactivation',
            'slug' => 'domain-reactivation',
        ]);

        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($otsProduct)
            ->createOne();
        $action = new NovaProcessOrderLineItemAction(
            self::resolve(TranslatorInterface::class),
            self::createStub(SubscriptionService::class),
            self::createStub(OneTimeServiceCreator::class),
        );

        $actionFields = $this->getFields([]);
        $models = new Collection([$orderLineItem]);

        $response = $action->handle($actionFields, $models);
        self::assertInstanceOf(ActionResponse::class, $response);
        $danger = $response['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.process_order_line_item.invalid_status', $danger->text);
    }

    #[Test]
    public function handleOneTimeServiceOrderLineItem(): void
    {
        $order = new OrderFactory()
            ->for(new CustomerFactory())
            ->createOne([
                'status' => OrderStatus::IN_PROGRESS,
            ]);

        $otsGroup = new ProductGroupFactory()->oneTimeService()->createOne();
        $otsProduct = new ProductFactory()->for($otsGroup)->createOne([
            'name' => 'Domain reactivation',
            'slug' => 'domain-reactivation',
        ]);

        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($otsProduct)
            ->createOne();
        $oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $oneTimeServiceCreator->expects(self::once())
            ->method('createFromOrderLineItem')
            ->with($orderLineItem);

        $action = new NovaProcessOrderLineItemAction(
            self::resolve(TranslatorInterface::class),
            self::createStub(SubscriptionService::class),
            $oneTimeServiceCreator,
        );

        $actionFields = $this->getFields([]);
        $models = new Collection([$orderLineItem]);

        $response = $action->handle($actionFields, $models);
        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.invoice_propagated_to_harbor', $message->text);
    }

    #[Test]
    public function handleOneTimeServiceAlreadyCreated(): void
    {
        $order = new OrderFactory()
            ->for(new CustomerFactory())
            ->createOne([
                'status' => OrderStatus::IN_PROGRESS,
            ]);

        $otsGroup = new ProductGroupFactory()->oneTimeService()->createOne();
        $otsProduct = new ProductFactory()->for($otsGroup)->createOne([
            'name' => 'Domain reactivation',
            'slug' => 'domain-reactivation',
        ]);

        $ots = new OneTimeServiceFactory()
            ->for(new CustomerFactory()->createOne())
            ->for(new SubscriptionFactory()->withCustomer()->for($otsProduct)->createOne())
            ->for($otsProduct)
            ->createOne();

        $orderLineItem = new OrderLineItemFactory()
            ->for($order)
            ->for($otsProduct)
            ->for($ots)
            ->createOne([
                'processed_at' => CarbonImmutable::now(),
            ]);
        $oneTimeServiceCreator = self::createMock(OneTimeServiceCreator::class);
        $oneTimeServiceCreator->expects(self::never())
            ->method('createFromOrderLineItem');

        $action = new NovaProcessOrderLineItemAction(
            self::resolve(TranslatorInterface::class),
            self::createStub(SubscriptionService::class),
            $oneTimeServiceCreator,
        );

        $actionFields = $this->getFields([]);
        $models = new Collection([$orderLineItem]);

        $response = $action->handle($actionFields, $models);
        self::assertInstanceOf(ActionResponse::class, $response);
        $danger = $response['danger'];
        self::assertInstanceOf(Message::class, $danger);
        self::assertSame('nova-action.process_order_line_item.already_processed', $danger->text);
    }

    /**
     * @param array<string, bool|null|string> $payload
     */
    private function getFields(array $payload): ActionFields
    {
        return new ActionFields(new Collection($payload), new Collection([]));
    }
}
