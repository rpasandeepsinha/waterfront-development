<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Cart;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\ProductAllowedChangeFactory;
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
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;

#[CoversClass(CartController::class)]
class CartWithUpgradesControllerTest extends IntegrationTestCase
{
    private Product $hostingProductSilver;

    private Customer $customer;

    private Product $upgradeProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $startDate = CarbonImmutable::create(2023);
        $nextBillingDate = CarbonImmutable::create(2023, 12, 31);
        CarbonImmutable::setTestNow(CarbonImmutable::create(2023, 2, 10));

        $this->customer = CustomerFactory::new()->createOne(['has_direct_debit' => true]);
        $hostingGroup = new ProductGroupFactory()->hosting()->createOne();
        $this->hostingProductSilver = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'silver',
        ]);
        new ProductPriceComponentFactory()
            ->for($this->hostingProductSilver)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        new SubscriptionFactory()
            ->for($this->hostingProductSilver)
            ->for($this->customer)
            ->createOne([
                'uuid' => '23e59c49-16bd-410e-85d6-5c2b6bf3400a',
                'start_date' => $startDate,
                'billing_period' => 12,
                'next_billing_date' => $nextBillingDate,
                'net_price' => 1000,
            ]);

        $this->upgradeProduct = new ProductFactory()->for($hostingGroup)->createOne([
            'slug' => 'upgrade',
        ]);

        new ProductPriceComponentFactory()
            ->for($this->upgradeProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);
        new ProductPriceComponentFactory()
            ->for($this->upgradeProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);
    }

    #[Test]
    public function shouldProcessCartWithUpgradeAndCalculateProRataWithoutVouchers(): void
    {
        $extension = new ProductGroupFactory()->extension()->createOne();
        $extensionPR = new ProductFactory()->for($extension)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()
            ->for($extensionPR)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        $json = (string) file_get_contents(__DIR__
        . '/data/cart_payload_with_upgrade_and_regular_product_without_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(
                ['totalExclVatPrice' => 1888, 'productSlug' => $extensionPR->slug, 'priceExclVat' => 1000],
            )
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartWithUpgradeAndAddonWithoutVouchers(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $addonProduct = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon',
        ]);

        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);
        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);

        $coupling = new ProductAddonCoupling();
        $coupling->parent_product_id = $this->upgradeProduct->id;
        $coupling->addon_product_id = $addonProduct->id;
        $coupling->save();

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne(
                [
                    'from_product_id' => $this->hostingProductSilver->id,
                    'to_product_id' => $this->upgradeProduct->id,
                ],
            );

        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_upgrade_and_addon_without_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->withoutExceptionHandling()
            ->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(
                ['totalExclVatPrice' => 2291, 'productSlug' => $addonProduct->slug, 'priceExclVat' => 888],
            )
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartWithUpgradeAndAddonAndNormalProductsWithoutVouchers(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();
        $addonProduct = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon',
        ]);

        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);
        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);

        $coupling = new ProductAddonCoupling();
        $coupling->parent_product_id = $this->upgradeProduct->id;
        $coupling->addon_product_id = $addonProduct->id;
        $coupling->save();

        $extension = new ProductGroupFactory()->extension()->createOne();
        $extensionPR = new ProductFactory()->for($extension)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()
            ->for($extensionPR)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne(
                [
                    'from_product_id' => $this->hostingProductSilver->id,
                    'to_product_id' => $this->upgradeProduct->id,
                ],
            );

        $json = (string) file_get_contents(__DIR__
        . '/data/cart_payload_with_upgrade_and_addon_and_normal_product_without_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->withoutExceptionHandling()
            ->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(
                ['totalExclVatPrice' => 3291, 'productSlug' => $extensionPR->slug, 'priceExclVat' => 888],
            )
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartWithUpgradeAndAddonAndNormalProductsWithVouchers(): void
    {
        $addonGroup = new ProductGroupFactory()->addon()->createOne();

        $addonProduct = new ProductFactory()->for($addonGroup)->createOne([
            'slug' => 'addon',
        ]);

        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->registration()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 1000]);
        new ProductPriceComponentFactory()
            ->for($addonProduct)
            ->prolongation()
            ->createOne(['billing_period' => 12, 'contract_period' => 12, 'price' => 2580]);

        $coupling = new ProductAddonCoupling();
        $coupling->parent_product_id = $this->upgradeProduct->id;
        $coupling->addon_product_id = $addonProduct->id;
        $coupling->save();

        new ProductAllowedChangeFactory()
            ->upgradeChange()
            ->createOne(
                [
                    'from_product_id' => $this->hostingProductSilver->id,
                    'to_product_id' => $this->upgradeProduct->id,
                ],
            );

        $extension = new ProductGroupFactory()->extension()->createOne();
        $extensionPR = new ProductFactory()->for($extension)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()
            ->for($extensionPR)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        $json = (string) file_get_contents(__DIR__
        . '/data/cart_payload_with_upgrade_and_addon_and_normal_product_with_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);

        VoucherFactory::new()->for($extension)->createOne([
            'code' => 'fietsen',
            'amount' => 100,
            'amount_type' => VoucherAmountType::FIXED,
            'description' => '',
            'display_name' => '',
        ]);

        $this->withoutExceptionHandling()
            ->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(
                [
                    'totalExclVatPrice' => 3191,
                    'productSlug' => $extensionPR->slug,
                    'priceExclVat' => 900,
                    'fietsen',
                    'claimed_amount' => 100,
                ],
            );
    }

    #[Test]
    public function shouldProcessCartWithUpgradeAndNormalProductAndCalculateProRataWithoutVouchers(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/data/cart_payload_with_upgrade_without_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment(
                ['productSlug' => $this->upgradeProduct->slug, 'priceExclVat' => 888],
            )
            ->assertJsonFragment(['vouchers' => []]);
    }

    #[Test]
    public function shouldProcessCartWithUpgradeAndNormalProductAndCalculateProRataWithVoucherButShouldApplyProrataPrice(): void
    {
        $extension = new ProductGroupFactory()->extension()->createOne();
        $extensionPR = new ProductFactory()->for($extension)->createOne([
            'slug' => 'extension_nl',
        ]);
        new ProductPriceComponentFactory()
            ->for($extensionPR)
            ->registration()
            ->createOne([
                'billing_period' => 12,
                'contract_period' => 12,
                'price' => 1000,
            ]);

        VoucherFactory::new()->for($extension)->createOne([
            'code' => 'fietsen',
            'amount' => 10,
            'amount_type' => VoucherAmountType::FIXED,
            'description' => '',
            'display_name' => '',
        ]);

        $json = (string) file_get_contents(__DIR__
        . '/data/cart_payload_with_upgrade_and_regular_product_with_vouchers.json');

        $payload = json_decode($json, true);

        self::assertIsArray($payload);
        $this->actingAsCustomer($this->customer)
            ->postJson($this->generateRoute('partners.cart.calculate'), $payload)
            ->assertOk()
            ->assertJsonFragment([
                'fietsen',
                'claimed_amount' => 10,
                'productSlug' => $extensionPR->slug,
                'priceExclVat' => 990,
                'priceType' => UsedProductPriceType::VOUCHER_PRICE->value,
            ]);
    }
}
