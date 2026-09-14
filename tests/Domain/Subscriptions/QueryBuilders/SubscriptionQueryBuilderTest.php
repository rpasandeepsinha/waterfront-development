<?php

declare(strict_types=1);

namespace Tests\Domain\Subscriptions\QueryBuilders;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductSpecFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\QueryBuilders\SubscriptionQueryBuilder;

#[CoversClass(SubscriptionQueryBuilder::class)]
class SubscriptionQueryBuilderTest extends IntegrationTestCase
{
    private ProductGroup $productGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productGroup = new ProductGroupFactory()->hosting()->createOne();
    }

    #[Test]
    public function whereLikeDomain(): void
    {
        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->createOne([
                'domain' => 'sandwaveio.dev',
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->createOne([
                'domain' => 's4ndw4v310.dev',
            ]);

        $count = Subscription::query()->whereLikeDomain('sandwave')->count();

        self::assertSame(1, $count);
    }

    #[Test]
    public function whereAdministrativeStatusActive(): void
    {
        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->createOne([
                'administrative_status' => AdministrativeStatus::ARCHIVED->value,
            ]);

        $count = Subscription::query()->whereAdministrativeStatusActive()->count();

        self::assertSame(1, $count);
    }

    #[Test]
    public function whereProductGroup(): void
    {
        $product = new ProductFactory()->createOne([
            'product_group_id' => $this->productGroup->id,
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->createOne([
                'product_uuid' => $product->uuid,
            ]);

        $count = Subscription::query()->whereProductGroup($this->productGroup->uuid)->count();

        self::assertSame(1, $count);

        $count = Subscription::query()->whereProductGroup(':)')->count();

        self::assertSame(0, $count);

        $count = Subscription::query()->whereProductGroup([':)', $this->productGroup->uuid])->count();

        self::assertSame(1, $count);
    }

    #[Test]
    public function whereProductSpecName(): void
    {
        $product = new ProductFactory()->for($this->productGroup)->createOne([
            'name' => 'Mail Only',
            'slug' => 'hosting_mail_only',
        ]);

        $spec = new ProductSpecFactory()->for($product)->createOne([
            'name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value,
            'value' => '0',
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->for($product)
            ->createOne();

        $count = Subscription::query()
            ->whereProductSpecNameAndValueIsTrue(ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->count();
        self::assertSame(0, $count, 'should not return 1 since the spec is not true');

        $spec->value = 1;
        $spec->save();

        $count = Subscription::query()
            ->whereProductSpecNameAndValueIsTrue(ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER)
            ->count();
        self::assertSame(1, $count);
    }

    #[Test]
    public function whereProductName(): void
    {
        $productGroup = new ProductGroupFactory()->extension()->createOne();

        $product = new ProductFactory()->createOne([
            'name' => 'VPS',
            'product_group_id' => $productGroup->id,
        ]);

        $product2 = new ProductFactory()->createOne([
            'name' => 'VPS v2',
            'product_group_id' => $productGroup->id,
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product->uuid,
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product2->uuid,
            ]);

        $count = Subscription::query()->whereProductName('VPS')->count();

        self::assertSame(1, $count);
    }

    #[Test]
    public function whereProductSlug(): void
    {
        $product = new ProductFactory()->createOne([
            'slug' => 'sandwave',
            'product_group_id' => $this->productGroup->id,
        ]);

        $product2 = new ProductFactory()->createOne([
            'slug' => 'sandwave 2',
            'product_group_id' => $this->productGroup->id,
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product->uuid,
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product2->uuid,
            ]);

        $count = Subscription::query()->whereProductSlug('sandwave')->count();

        self::assertSame(1, $count);
    }

    #[Test]
    public function whereProductSlugs(): void
    {
        // 3 Different products
        $product = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => ProductType::BASIC_DNS,
        ]);

        $product2 = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'sandwave2',
        ]);

        $product3 = new ProductFactory()->for($this->productGroup)->createOne([
            'slug' => 'sandwave3',
        ]);

        // Each product a subscription and we only will search for and expect the first 2 to be found
        $expectedSubscriptionIds = [];
        $expectedSubscriptionIds[] = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product->uuid,
            ])
            ->id;

        $expectedSubscriptionIds[] = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product2->uuid,
            ])
            ->id;

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product3->uuid,
            ]);

        $subscriptions = Subscription::query()->whereProductSlugs([ProductType::BASIC_DNS, 'sandwave2'])->get();

        self::assertCount(2, $subscriptions);
        self::assertEqualsCanonicalizing($expectedSubscriptionIds, $subscriptions->pluck('id')->toArray());
    }

    #[Test]
    public function whereProductNames(): void
    {
        $product = new ProductFactory()->createOne([
            'name' => 'VPS',
            'product_group_id' => $this->productGroup->id,
        ]);

        $product2 = new ProductFactory()->createOne([
            'name' => 'VPS v2',
            'product_group_id' => $this->productGroup->id,
        ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product->uuid,
            ]);

        new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'product_uuid' => $product2->uuid,
            ]);

        $count = Subscription::query()->whereProductNames([':)', 'VPS v2'])->count();

        self::assertSame(1, $count);
    }

    #[Test]
    public function whereProductUuid(): void
    {
        $subscription = new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->createOne();
        new SubscriptionFactory()
            ->withCustomer()
            ->for(new ProductFactory()->for($this->productGroup))
            ->create();

        $count = Subscription::query()->whereProductUuid($subscription->product_uuid)->count();

        self::assertSame(1, $count);
    }
}
