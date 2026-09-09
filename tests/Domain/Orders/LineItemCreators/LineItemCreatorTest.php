<?php

declare(strict_types=1);

namespace Tests\Domain\Orders\LineItemCreators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Factories\CustomerFactory;
use Tests\Factories\OrderFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Orders\DTO\CartOrderLines\ExtensionLineItem;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\ExtensionMetaData;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\MetaData;
use Waterfront\Domain\Orders\DTO\CartOrderLines\VpsLineItem;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\LineItemCreators\LineItemCreator;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Pricing\DTO\PriceComponents\ProductGroupPriceComponent;
use Waterfront\Domain\Pricing\DTO\PriceComponents\RegistrationPriceComponent;
use Waterfront\Domain\Products\DTO\Price;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Exceptions\ProductNotFoundException;
use Waterfront\Domain\Products\Models\Product;

#[CoversClass(LineItemCreator::class)]
class LineItemCreatorTest extends IntegrationTestCase
{
    private const string TEST_PRODUCT_SLUG = 'vps';
    private const ProductGroupType TEST_PRODUCT_GROUP = ProductGroupType::VPS;

    private Order $testOrder;

    private Product $product;

    private LineItemCreator $lineItemCreator;

    protected function setUp(): void
    {
        parent::setUp();
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => self::TEST_PRODUCT_GROUP->value,
            'slug' => self::TEST_PRODUCT_GROUP,
        ]);

        $this->testOrder = new OrderFactory()->for(new CustomerFactory())->createOne();
        $this->product = new ProductFactory()->for($productGroup)
            ->createOne(['slug' => self::TEST_PRODUCT_SLUG]);
        new ProductPriceComponentFactory()->for($this->product)->registration()->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 100,
        ]);

        $this->lineItemCreator = self::resolve(LineItemCreator::class);
    }

    #[Test]
    public function create(): void
    {
        $lineItem = new VpsLineItem(
            uuid: Uuid::uuid4(),
            slug: self::TEST_PRODUCT_SLUG,
            parentSubscriptionUuid: null,
            subscriptionUuid: null,
            billingPeriod: 12,
            contractPeriod: 12,
            status: ProductPriceType::REGISTRATION,
            children: null,
            oneTimeServices: null,
            experimentSlug: null,
        );

        $price = new Price(ProductPriceType::REGISTRATION, $lineItem->billingPeriod, $this->product->id, $this->product->productGroup->uuid, 100, $lineItem->contractPeriod, true, true, appliedPriceComponents: [new RegistrationPriceComponent(100)], calculatedPrice: 100);

        $orderLineItem = $this->lineItemCreator->create($lineItem, $this->testOrder, $price);

        self::assertModelExists($orderLineItem);

        self::assertSame($this->testOrder->id, $orderLineItem->order->id);
        self::assertSame($lineItem->slug, $orderLineItem->product?->slug);

        self::assertNotNull($lineItem->status);
        self::assertSame($lineItem->status->value, $orderLineItem->status->value);

        self::assertSame($lineItem->billingPeriod, $orderLineItem->billing_period);
        self::assertSame($lineItem->contractPeriod, $orderLineItem->contract_period);
        self::assertTrue($orderLineItem->should_invoice);
    }

    #[Test]
    public function defaultRegistrationStatus(): void
    {
        $lineItem = new VpsLineItem(
            uuid: Uuid::uuid4(),
            slug: self::TEST_PRODUCT_SLUG,
            parentSubscriptionUuid: null,
            subscriptionUuid: null,
            billingPeriod: 12,
            contractPeriod: 12,
            status: null,
            children: null,
            oneTimeServices: null,
            experimentSlug: null,
        );

        $price = new Price(ProductPriceType::REGISTRATION, $lineItem->billingPeriod, $this->product->id, $this->product->productGroup->uuid, 100, $lineItem->contractPeriod, true, true, appliedPriceComponents: [new RegistrationPriceComponent(100)], calculatedPrice: 100);

        $orderLineItem = $this->lineItemCreator->create($lineItem, $this->testOrder, $price);

        self::assertNull($lineItem->status);
        self::assertSame(OrderLineItemStatus::REGISTRATION, $orderLineItem->status);
    }

    #[Test]
    public function shouldNotInvoice(): void
    {
        $lineItem = new VpsLineItem(
            uuid: Uuid::uuid4(),
            slug: self::TEST_PRODUCT_SLUG,
            parentSubscriptionUuid: null,
            subscriptionUuid: '123e4567-e89b-12d3-a456-426614174000',
            billingPeriod: 12,
            contractPeriod: 12,
            status: null,
            children: null,
            oneTimeServices: null,
            experimentSlug: null,
        );

        $price = new Price(ProductPriceType::REGISTRATION, $lineItem->billingPeriod, $this->product->id, $this->product->productGroup->uuid, 100, $lineItem->contractPeriod, true, true, appliedPriceComponents: [new RegistrationPriceComponent(100)], calculatedPrice: 100);

        $orderLineItem = $this->lineItemCreator->create($lineItem, $this->testOrder, $price);

        self::assertFalse($orderLineItem->should_invoice);
    }

    #[Test]
    public function missingProductException(): void
    {
        $lineItem = new VpsLineItem(
            uuid: Uuid::uuid4(),
            slug: 'non-existing',
            parentSubscriptionUuid: null,
            subscriptionUuid: null,
            billingPeriod: 12,
            contractPeriod: 12,
            status: null,
            children: null,
            oneTimeServices: null,
            experimentSlug: null,
        );

        $price = new Price(ProductPriceType::REGISTRATION, $lineItem->billingPeriod, $this->product->id, $this->product->productGroup->uuid, 100, $lineItem->contractPeriod, true, true, appliedPriceComponents: [new RegistrationPriceComponent(100)], calculatedPrice: 100);

        $this->expectException(ProductNotFoundException::class);
        $this->expectExceptionMessageIs(sprintf('Could not find product with slug %s', 'non-existing'));

        $this->lineItemCreator->create($lineItem, $this->testOrder, $price);

        self::assertDatabaseEmpty(OrderLineItem::class);
    }

    #[Test]
    public function lineItemWithMetaData(): void
    {
        $transferSecret = 'transfer-secret';
        $contactId = 123;
        $productGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::EXTENSION->value,
            'slug' => ProductGroupType::EXTENSION,
        ]);

        $product = new ProductFactory()->for($productGroup)
            ->createOne(['slug' => ProductGroupType::EXTENSION->value]);

        new ProductPriceComponentFactory()->for($product)->registration()->createOne([
            'contract_period' => 12,
            'billing_period' => 12,
            'price' => 100,
        ]);

        $lineItem = new ExtensionLineItem(
            uuid: Uuid::uuid4(),
            slug: ProductGroupType::EXTENSION->value,
            parentSubscriptionUuid: null,
            subscriptionUuid: null,
            billingPeriod: 12,
            contractPeriod: 12,
            domain: null,
            status: ProductPriceType::REGISTRATION,
            children: null,
            oneTimeServices: null,
            experimentSlug: null,
            transferSecret: $transferSecret,
            privateWhois: true,
            contactId: $contactId,
        );

        $price = new Price(ProductPriceType::REGISTRATION, $lineItem->billingPeriod, $this->product->id, $this->product->productGroup->uuid, 100, $lineItem->contractPeriod, true, true, appliedPriceComponents: [new RegistrationPriceComponent(100)], calculatedPrice: 100);

        $orderLineItem = $this->lineItemCreator->create($lineItem, $this->testOrder, $price);

        $metaData = self::resolve(CartSerializerFactory::class)->get()->deserialize($orderLineItem->meta_data, MetaData::class, 'json');
        self::assertInstanceOf(ExtensionMetaData::class, $metaData);
        self::assertSame($transferSecret, $metaData->transferSecret);
        self::assertTrue($metaData->privateWhois);
        self::assertSame($contactId, $metaData->contactId);
    }

    #[Test]
    public function persistOrderLineItemPrice(): void
    {
        $lineItem = new VpsLineItem(
            uuid: Uuid::uuid4(),
            slug: self::TEST_PRODUCT_SLUG,
            parentSubscriptionUuid: null,
            subscriptionUuid: null,
            billingPeriod: 12,
            contractPeriod: 12,
            status: ProductPriceType::REGISTRATION,
            children: null,
            oneTimeServices: null,
            experimentSlug: null,
        );

        $price = new Price(
            ProductPriceType::REGISTRATION,
            $lineItem->billingPeriod,
            $this->product->id,
            $this->product->productGroup->uuid,
            100,
            $lineItem->contractPeriod,
            true,
            true,
            calculatedPrice: 20,
            appliedPriceComponents: [new ProductGroupPriceComponent(null, 80, null, 20, 1)]
        );

        $orderLineItem = $this->lineItemCreator->create($lineItem, $this->testOrder, $price);

        self::assertCount(1, $orderLineItem->prices->firstOrFail()->components);
        self::assertSame(1, $orderLineItem->prices->firstOrFail()->components->firstOrFail()->order_applied);
    }
}
