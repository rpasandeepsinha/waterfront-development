<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\Actions;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ItemNotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\Templates\SubscriptionContractUpdated;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Subscriptions\Actions\ExtendContractAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;

#[CoversClass(ExtendContractAction::class)]
class ExtendContractActionTest extends IntegrationTestCase
{
    public Subscription $subscription;

    public ProductPriceComponent $productPrice;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();
        $this->productPrice = new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne([
                'contract_period' => 1,
                'billing_period' => 1,
                'price' => 1000,
            ]);
        new ProductPriceComponentFactory()
            ->for($product)
            ->prolongation()
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
                'price' => 1000,
            ]);

        $this->subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOne([
                'contract_period' => 12,
                'billing_period' => 12,
            ]);

        new TemplateFactory()->createOne([
            'slug' => SubscriptionContractUpdated::getTemplateSlug(),
        ]);
    }

    #[Test]
    public function mutationWasCreated(): void
    {
        self::assertEmailsSend([
            SubscriptionContractUpdated::class,
        ]);
        $extendContractAction = new ExtendContractAction(
            self::resolve(PriceResolver::class),
            self::resolve(MailerInterface::class),
        );

        $extendContractAction->execute(
            $this->subscription,
            1,
            1,
            null,
            null,
        );

        self::assertDatabaseHas(
            new SubscriptionMutation()->getTable(),
            [
                'subscription_id' => $this->subscription->id,
                'product_id' => $this->subscription->product->id,
                'billing_period' => 1,
                'contract_period' => 1,
                'mutated_at' => null,
                'net_price' => $this->productPrice->price,
            ],
        );
    }

    #[Test]
    public function mutationWithNewProductWasCreatedAndNoMailWasSend(): void
    {
        $mailerMock = $this->createMock(MailerInterface::class);
        $mailerMock->expects(self::never())->method('send');

        $extendContractAction = new ExtendContractAction(
            self::resolve(PriceResolver::class),
            $mailerMock,
        );

        $newProduct = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        $productPrice = new ProductPriceComponentFactory()
            ->for($newProduct)
            ->prolongation()
            ->createOne(['billing_period' => 1, 'contract_period' => 1]);

        $extendContractAction->execute(
            $this->subscription,
            1,
            1,
            null,
            $newProduct,
        );

        self::assertDatabaseHas(
            new SubscriptionMutation()->getTable(),
            [
                'subscription_id' => $this->subscription->id,
                'product_id' => $newProduct->id,
                'billing_period' => 1,
                'contract_period' => 1,
                'mutated_at' => null,
                'net_price' => $productPrice->price,
            ],
        );
    }

    #[Test]
    public function mutationWasCreatedWithCustomPrice(): void
    {
        self::assertEmailsSend([
            SubscriptionContractUpdated::class,
        ]);

        $extendContractAction = self::resolve(ExtendContractAction::class);

        $extendContractAction->execute(
            $this->subscription,
            1,
            1,
            12345,
            null,
        );

        self::assertDatabaseHas(
            new SubscriptionMutation()->getTable(),
            [
                'subscription_id' => $this->subscription->id,
                'product_id' => $this->subscription->product->id,
                'billing_period' => 1,
                'contract_period' => 1,
                'mutated_at' => null,
                'net_price' => 12345,
            ],
        );
    }

    #[Test]
    public function productPriceDoesNotExistException(): void
    {
        $this->expectException(ItemNotFoundException::class);

        $extendContractAction = self::resolve(ExtendContractAction::class);

        ProductPriceComponent::query()->delete();

        $extendContractAction->execute(
            $this->subscription,
            1,
            1,
            null,
            null,
        );
    }

    #[Test]
    public function givenPeriodsMatchSubscriptionPeriodsThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $extendContractAction = self::resolve(ExtendContractAction::class);

        $extendContractAction->execute(
            $this->subscription,
            12,
            12,
            null,
            null,
        );
    }

    #[Test]
    public function givenPeriodsMatchSubscriptionPeriodsWithRenewalPriceSucceeds(): void
    {
        $extendContractAction = self::resolve(ExtendContractAction::class);

        $extendContractAction->execute(
            $this->subscription,
            1,
            1,
            500,
            null,
        );

        self::assertDatabaseHas(
            new SubscriptionMutation()->getTable(),
            [
                'subscription_id' => $this->subscription->id,
                'product_id' => $this->subscription->product->id,
                'billing_period' => 1,
                'contract_period' => 1,
                'mutated_at' => null,
                'net_price' => 500,
            ],
        );
    }
}
