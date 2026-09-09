<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Cart;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProductPriceComponentFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CartController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\UsedProductPriceType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

#[CoversClass(CartController::class)]
class CartWithAddonsControllerTest extends IntegrationTestCase
{
    private Product $addonProduct;

    private ProductGroup $hostingGroup;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $now = CarbonImmutable::create(2023, 2, 10);

        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $this->addonProduct = new ProductFactory()
            ->for($addonGroup)
            ->createOne(['slug' => 'addon']);

        new ProductPriceComponentFactory()->for($this->addonProduct)->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000, 'starts_at' => $now]);
        new ProductPriceComponentFactory()->for($this->addonProduct)->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);
        $this->hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $silver = new ProductFactory()->for($this->hostingGroup)->createOne([
            'slug' => 'silver',
        ]);

        new ProductPriceComponentFactory()->for($silver)->registration()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 1000,
            'starts_at' => $now,
        ]);

        $coupling = new ProductAddonCoupling();
        $coupling->parent_product_id = $silver->id;
        $coupling->addon_product_id = $this->addonProduct->id;
        $coupling->save();

        $startDate = CarbonImmutable::create(2023);
        $nextBillingDate = CarbonImmutable::create(2023, 12, 31);

        CarbonImmutable::setTestNow($now);

        $this->customer = CustomerFactory::new()->createOne(['has_direct_debit' => true]);

        new SubscriptionFactory()->for($silver)->for($this->customer)->createOne([
            'uuid' => '23e59c49-16bd-410e-85d6-5c2b6bf3400a',
            'start_date' => $startDate,
            'billing_period' => 12,
            'next_billing_date' => $nextBillingDate,
            'net_price' => 1000,
        ]);
    }

    #[Test]
    public function shouldProcessCartWithAddonAndCalculateProRataWithoutVouchers(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_addon_without_vouchers.json');

        $payload =  json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(
                ['productSlug' => $this->addonProduct->slug, 'priceExclVat' => 888]
            )
            ->assertJsonFragment(['vouchers' => []]);

        // [{"administrationFee":0,
        //"items":[{"billingPeriod":12,"contractPeriod":12,"itemUuid":"951fab39-c95c-468b-afa4-76a334460ed5","metaData":null,"parentItemUuid":null,"parentSubscriptionUuid":"23e59c49-16bd-410e-85d6-5c2b6bf3400a","price":{"appliedPrice":{"actionPeriod":null,"actionPeriodPrice":null,"priceExclVat":2290,"priceExplanation":null,"priceInclVat":2771,"priceType":"regular","voucher":null},"regularPrice":{"priceExclVat":2290,"priceInclVat":2771}},"priceType":"registration","productSlug":"addon","quantity":1}],"totalExclVatPrice":2290,"totalInclVatPrice":2771,"vouchers":[]}].
    }

    #[Test]
    public function shouldProcessCartWithAddonAndNormalProductAndCalculateProRataWithoutVouchers(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_addon_and_regular_product_without_vouchers.json');

        $payload =  json_decode($json, true);

        $extension = new ProductGroupFactory()->extension()->createOne();
        $extensionPR = new ProductFactory()->for($extension)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()->for($extensionPR)->registration()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 1000,
        ]);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(['productSlug' => $extensionPR->slug, 'priceExclVat' => 1000, 'priceType' => UsedProductPriceType::REGULAR_PRICE->value])
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartWithAddonAndCalculateProRataWithVoucherButShouldApplyProrataPrice(): void
    {
        VoucherFactory::new()->for($this->hostingGroup)->createOne(['code' => 'fietsen', 'amount' => 10, 'amount_type' => VoucherAmountType::PERCENTAGE, 'description' => '', 'display_name' => '']);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_addon_with_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment([
                'productSlug' => $this->addonProduct->slug,
                    'priceExclVat' => 888,
                    'fietsen',
                    'claimed_amount' => 0,
                ]);
    }

    #[Test]
    public function shouldProcessCartWithAddonAndNormalProductAndCalculateProRataWithVoucherButShouldApplyProrataPrice(): void
    {
        $extension = new ProductGroupFactory()->extension()->createOne();
        $extensionPR = new ProductFactory()->for($extension)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()->for($extensionPR)->registration()->createOne([
            'billing_period' => 12,
            'contract_period' => 12,
            'price' => 1000,
        ]);

        VoucherFactory::new()->for($extension)->createOne(['code' => 'fietsen', 'amount' => 10, 'amount_type' => VoucherAmountType::FIXED, 'description' => '', 'display_name' => '']);

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_addon_and_regular_product_with_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment([
                'fietsen',
                'productSlug' => $extensionPR->slug,
                'priceExclVat' => 990,
            ]);
    }
}
