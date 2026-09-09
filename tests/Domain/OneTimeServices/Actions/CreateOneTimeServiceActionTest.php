<?php

declare(strict_types=1);

namespace Tests\Domain\OneTimeServices\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Actions\CreateOneTimeServiceAction;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductPriceAlternative;
use Waterfront\Domain\Subscriptions\Models\Subscription;

#[CoversClass(CreateOneTimeServiceAction::class)]
class CreateOneTimeServiceActionTest extends IntegrationTestCase
{
    private CarbonImmutable $endDate;

    private Customer $customer;

    private Product $subscriptionProduct1;

    private Product $subscriptionProduct2;

    private Product $oneTimeServiceProduct;

    private CreateOneTimeServiceAction $oneTimeServiceInvoicingAction;

    public function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);

        // We are testing on a yearly subscription, exactly half way the period
        $now = new CarbonImmutable('today 00:00:00');
        $this->endDate = $now->addMonths(6);
        CarbonImmutable::setTestNow($now);

        $this->customer = new CustomerFactory()
            ->withAddress()
            ->createOne();

        $extensionGroup = new ProductGroupFactory()->extension()->createOne();
        $this->subscriptionProduct1 = new ProductFactory()
            ->for($extensionGroup)
            ->createOne([
                'slug' => 'product-1',
            ]);

        $this->subscriptionProduct2 = new ProductFactory()
            ->for($extensionGroup)
            ->createOne([
                'slug' => 'product-2',
            ]);

        $this->oneTimeServiceProduct = new ProductFactory()
            ->for(new ProductGroupFactory()->oneTimeService())
            ->createOne();
        $price = new ProductPriceComponentFactory()
            ->oneTimeService()
            ->for($this->oneTimeServiceProduct)
            ->createOne([
                'price' => 2500,
            ]);
        $alternativePrice = new ProductPriceAlternative();
        $alternativePrice->product_id = $this->oneTimeServiceProduct->id;
        $alternativePrice->billing_period = $price->billing_period;
        $alternativePrice->contract_period = $price->contract_period;
        $alternativePrice->alternative_product_id = $this->subscriptionProduct2->id;
        $alternativePrice->gross_price = 5000;
        $alternativePrice->save();

        $this->oneTimeServiceInvoicingAction = new CreateOneTimeServiceAction(
            self::resolve(OneTimeServiceCreator::class)
        );

        // Acting user required for audit logging (notes)
        $this->actingAsEmployee();
    }

    /**
     * @param array<string> $subscriptionsByProduct
     * @param array<int>    $expectedGrossPrices
     */
    #[DataProvider('dataContexts')]
    #[Test]
    public function execute(
        array $subscriptionsByProduct,
        int $amount,
        int $discountPercentage,
        OneTimeServiceStatus $status,
        ?string $comment,
        array $expectedGrossPrices
    ): void {
        $products = [
            $this->subscriptionProduct1->slug => $this->subscriptionProduct1,
            $this->subscriptionProduct2->slug => $this->subscriptionProduct2,
        ];

        $executionDate = CarbonImmutable::tomorrow();

        $contexts = new Collection();
        foreach ($subscriptionsByProduct as $subscriptionProduct) {
            $contexts->add(
                new OneTimeServiceContext(
                    subscription: $this->getSubscription($products[$subscriptionProduct]),
                    product: $this->oneTimeServiceProduct,
                    amount: $amount,
                    discountPercentage: $discountPercentage,
                    status: $status,
                    executionDate: $executionDate,
                    comment: $comment,
                    grossPrice: null
                )
            );
        }

        $this->oneTimeServiceInvoicingAction->execute($contexts);

        foreach ($contexts as $i => $context) {
            $subscription = $context->subscription;

            $subscriptionOneTimeServices = $subscription->oneTimeServices()->get();

            self::assertCount(1, $subscriptionOneTimeServices);
            $oneTimeService = $subscriptionOneTimeServices->firstOrFail();

            self::assertSame($this->oneTimeServiceProduct->id, $oneTimeService->product->id);
            self::assertSame($executionDate->getTimestamp(), $oneTimeService->execution_date->getTimestamp());
            self::assertSame($expectedGrossPrices[$i], $oneTimeService->gross_price);
            self::assertSame($amount, $oneTimeService->amount);
            self::assertSame($discountPercentage, $oneTimeService->discount_percentage);
            self::assertSame($status, $oneTimeService->status);

            self::assertCount(0, $oneTimeService->invoices);

            self::assertStringContainsString('One-time service', (string) $subscription->notes()->firstOrFail()->note);
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function dataContexts(): iterable
    {
        yield '1 subscription, amount 1, no discount, status open' => [
            'subscriptionsByProduct' => ['product-1'],
            'amount' => 1,
            'discountPercentage' => 0,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [2500],
        ];

        yield '1 subscription, amount 1, 10% discount, status open' => [
            'subscriptionsByProduct' => ['product-1'],
            'amount' => 1,
            'discountPercentage' => 10,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [2500],
        ];

        yield '2 subscriptions, amount 1, no discount, status open' => [
            'subscriptionsByProduct' => ['product-1', 'product-1'],
            'amount' => 1,
            'discountPercentage' => 0,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [2500, 2500],
        ];

        yield '2 subscriptions, amount 2, no discount, status open' => [
            'subscriptionsByProduct' => ['product-1', 'product-1'],
            'amount' => 2,
            'discountPercentage' => 0,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [2500, 2500],
        ];

        yield '2 subscriptions, amount 2, 10% discount, status open' => [
            'subscriptionsByProduct' => ['product-1', 'product-1'],
            'amount' => 2,
            'discountPercentage' => 10,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [2500, 2500],
        ];

        yield '1 subscription (with alternative pricing), amount 1, 10% discount, status open' => [
            'subscriptionsByProduct' => ['product-2'],
            'amount' => 1,
            'discountPercentage' => 10,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [5000],
        ];

        yield '2 subscriptions (one with alternative pricing), amount 2, no discount, status open' => [
            'subscriptionsByProduct' => ['product-1', 'product-2'],
            'amount' => 2,
            'discountPercentage' => 0,
            'status' => OneTimeServiceStatus::OPEN,
            'comment' => null,
            'expectedGrossPrices' => [2500, 5000],
        ];

        yield '2 subscriptions (one with alternative pricing), amount 2, no discount, status in progress' => [
            'subscriptionsByProduct' => ['product-1', 'product-2'],
            'amount' => 2,
            'discountPercentage' => 0,
            'status' => OneTimeServiceStatus::IN_PROGRESS,
            'comment' => null,
            'expectedGrossPrices' => [2500, 5000],
        ];

        yield '2 subscriptions (one with alternative pricing), amount 2, no discount, status done' => [
            'subscriptionsByProduct' => ['product-1', 'product-2'],
            'amount' => 2,
            'discountPercentage' => 0,
            'status' => OneTimeServiceStatus::DONE,
            'comment' => null,
            'expectedGrossPrices' => [2500, 5000],
        ];
    }

    private function getSubscription(Product $subscriptionProduct): Subscription
    {
        $subscription = new SubscriptionFactory()
            ->for($this->customer)
            ->for($subscriptionProduct)
            ->createOneQuietly([
                'contract_period' => 12,
                'billing_period' => 12,
                'start_date' => $this->endDate->subMonths(12),
                'end_date' => $this->endDate,
                'next_billing_date' => $this->endDate,
            ]);

        $this->customer->refresh();
        $this->subscriptionProduct1->refresh();

        return $subscription;
    }
}
