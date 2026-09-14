<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ProductPriceGenerator;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductAddonCoupling;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;

class AddOnSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Add on';
        $group->slug = ProductGroupType::ADD_ON;
        $group->ledger_code = 8011;
        $group->save();

        $this->fancyInstaller($group);
        $this->extraDatabase($group);
        $this->extraStorage($group);
        $this->trustee($group);
        $this->basekitBookingAddon($group);
        $this->servicePlus($group);
    }

    private function trustee(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Domain trustee';
        $product->slug = 'domain_trustee_fr';
        $product->description = 'trustee';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::DOMAIN_ADD_ON_TRUSTEE, $product);

        $productSpec = new ProductSpec();
        $productSpec->name = ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value;
        $productSpec->value = true;
        $productSpec->product_id = $product->id;
        $productSpec->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::DOMAIN_ADD_ON_TRUSTEE_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $frDomain = $this->referenceRepo->get(ProductReference::DOMAIN_FR, Product::class);

        $addon = new ProductAddonCoupling();
        $addon->parent_product_id = $frDomain->id;
        $addon->addon_product_id = $product->id;
        $addon->save();
    }

    private function basekitBookingAddon(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Sitebuilder booking module';
        $product->slug = 'basekit-booking-addon';
        $product->description = 'Hiermee kunnen jouw klanten zelf hun afspraak met je inplannen';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::BASEKIT_ADD_ON_BOOKING, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE, 'value' => 9691, 'product_id' => $product->id],
            ['name' => ProductSpecName::CANCEL_WITH_PARENT, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1000);

        $defaultPrice = $prices->where('contract_period', 12)->where('billing_period', 1)->firstOrFail();

        $this->referenceRepo->set(ProductReference::BASEKIT_ADD_ON_BOOKING_PRICE, $defaultPrice);

        $website = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $webshop = $this->referenceRepo->get(ProductReference::SITEBUILDER_SHOP_BASEKIT, Product::class);

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $website->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $webshopAddon = new ProductAddonCoupling();
        $webshopAddon->parent_product_id = $webshop->id;
        $webshopAddon->addon_product_id = $product->id;
        $webshopAddon->save();
    }

    private function fancyInstaller(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Fancy installer';
        $product->slug = 'fancy-installer';
        $product->description = 'Een mooie installer die je kan helpen met installeren van Wordpress, Drupal of andere applicaties. Addon voor hosting';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::ADD_ON_FANCY_INSTALLER, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::ADD_ON_FANCY_INSTALLER_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function extraDatabase(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Extra database';
        $product->slug = 'extra-db';
        $product->description = 'Extra database voor hosting product.';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::ADD_ON_EXTRA_DB, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::ADD_ON_EXTRA_DB_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function extraStorage(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Extra Storage';
        $product->slug = 'extra-storage';
        $product->description = 'Dit is extra storage om bij een hosting product als add on te kopen.';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::ADD_ON_EXTRA_STORAGE, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::ADD_ON_EXTRA_STORAGE_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function servicePlus(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'ServicePlus';
        $product->description = 'Jij focust op je bedrijf, wij regelen de rest. Voor degene die extra gemak en zekerheid willen.';
        $product->slug = 'service_plus';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::SERVICE_SERVICE_PLUS, $product);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 999, false);

        $price = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::SERVICE_SERVICE_PLUS_REGISTRATION_PRICE, $price);

        $hostingStart = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START, Product::class);
        $hostingStartWp = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START_WP, Product::class);
        $hostingStartWeb = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START, Product::class);
        $hostingStartWebWp = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START_WP, Product::class);
        $hostingPlus = $this->referenceRepo->get(ProductReference::HOSTING_WEB_PLUS, Product::class);
        $hostingPlusWp = $this->referenceRepo->get(ProductReference::HOSTING_WEB_PLUS_WP, Product::class);
        $hostingPlusWeb = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_PLUS, Product::class);
        $hostingPlusWebWp = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_PLUS_WP, Product::class);

        ProductSpec::insert([
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $product->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingStart->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingStartWp->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingStartWeb->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingStartWebWp->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingPlus->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingPlusWp->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingPlusWeb->id],
            ['name' => ProductSpecName::HAS_SERVICE_PLUS, 'value' => '1', 'product_id' => $hostingPlusWebWp->id],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingStart->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingStartWp->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingStartWeb->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingStartWebWp->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingPlus->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingPlusWp->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingPlusWeb->id,
            ],
            [
                'name' => ProductSpecName::COMES_WITH_FREE_PRODUCT_SLUG,
                'value' => $product->slug,
                'product_id' => $hostingPlusWebWp->id,
            ],
        ]);

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingStart->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingStartWp->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingPlus->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingPlusWp->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $hostingBasic = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class);
        $hostingBasicWp = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP, Product::class);
        $hostingGrow = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW, Product::class);
        $hostingGrowWp = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW_WP, Product::class);

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingBasic->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingBasicWp->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingGrow->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();

        $websiteAddon = new ProductAddonCoupling();
        $websiteAddon->parent_product_id = $hostingGrowWp->id;
        $websiteAddon->addon_product_id = $product->id;
        $websiteAddon->save();
    }
}
