<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Transfers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\TransferController;
use Waterfront\Apps\API\Waterfront\Resources\ProductTransferPresenter;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Models\Transfer;

#[CoversClass(TransferController::class)]
#[CoversClass(ProductTransferPresenter::class)]
class TransferShowTest extends IntegrationTestCase
{
    private Customer $receiver;

    private Customer $fromCustomer;

    /** @var Collection<int, Subscription> */
    private Collection $subscriptions;

    public function setUp(): void
    {
        parent::setUp();

        $this->receiver = new CustomerFactory()->createOne();
        $this->fromCustomer = new CustomerFactory()->createOne();

        $this->subscriptions = $this->getTestingSubscriptions();
    }

    #[Test]
    public function show(): void
    {
        foreach ($this->prepareShowTransfers() as $transfer) {
            $response = $this->actingAsCustomer($this->fromCustomer)->getJson(
                $this->generateRoute('partners.transfers.show', ['transfer' => $transfer->uuid])
            )->assertOk();

            self::assertIsArray($response->json());
            Assert::assertArraySubset([
                'data' => [
                    'id' => $transfer->uuid,
                ],
            ], $response->json());
        }
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function getTestingSubscriptions(): Collection
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();
        $product2 = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();
        $product3 = new ProductFactory()->for(new ProductGroupFactory()->ssl()->createOne())->createOne();

        new ProductPriceComponentFactory()->for($product)->prolongation()->createOne(['billing_period' => 12, 'contract_period' => 12]);
        new ProductPriceComponentFactory()->for($product2)->prolongation()->createOne(['billing_period' => 12, 'contract_period' => 12]);
        new ProductPriceComponentFactory()->for($product3)->prolongation()->createOne(['billing_period' => 12, 'contract_period' => 12]);

        $subscription = new SubscriptionFactory()->for($this->fromCustomer)->for($product)->createOne();

        $subscription2 = new SubscriptionFactory()->for($this->fromCustomer)->for($product2)->createOne();

        $subscription3 = new SubscriptionFactory()->for($this->fromCustomer)->for($product3)->createOne();

        return new Collection([$subscription, $subscription2, $subscription3]);
    }

    /**
     * @return Transfer[]
     */
    private function prepareShowTransfers(): array
    {
        $transfer1 = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $this->receiver->id,
        ]);

        $transfer2 = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $this->receiver->id,
        ]);
        $subscription = $this->subscriptions->firstOrFail();
        $transfer2->subscriptions()->save($subscription);

        $transfer3 = new TransferFactory()->createOne([
            'from_customer_id' => $this->fromCustomer->id,
            'to_customer_id' => $this->receiver->id,
        ]);
        $subscription = $this->subscriptions->firstOrFail();

        $transfer3->subscriptions()->save($subscription, [
                'failed_at' => CarbonImmutable::now(),
                'executed_at' => CarbonImmutable::now(),
        ]);

        return [$transfer1, $transfer2, $transfer3];
    }
}
