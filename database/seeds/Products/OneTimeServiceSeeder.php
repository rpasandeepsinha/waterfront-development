<?php

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSlug;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPeriod;
use Waterfront\Domain\Products\Models\ProductSpec;

class OneTimeServiceSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepository,
    ) {
    }

    public function run(): void
    {
        $otsGroup = new ProductGroup();
        $otsGroup->uuid = Str::uuid()->toString();
        $otsGroup->name = 'One Time Service';
        $otsGroup->ledger_code = 9009;
        $otsGroup->slug = ProductGroupType::ONE_TIME_SERVICE;
        $otsGroup->save();

        $this->wordPressWebDesign($otsGroup);
        $this->transferService($otsGroup);
    }

    private function wordPressWebDesign(ProductGroup $otsGroup): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webmasterservice - WordPress installeren';
        $product->slug = 'webmasterservice_wp_install';
        $product->description = 'De installatie van een WordPress instantie op een lege website.';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $otsGroup->id;
        $product->save();
        $this->referenceRepository->set(ProductReference::ONE_TIME_SERVICE_WORDPRESS_DESIGN, $product);

        ProductSpec::insert([
            ['name' => 'ots.requires-parent-webhosting', 'value' => 1, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 7000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepository->set(ProductReference::ONE_TIME_SERVICE_WORDPRESS_DESIGN_PRICE, $price);
    }

    private function transferService(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Overstapservice';
        $product->description = 'Wij nemen alle technische stappen van je over. Zonder downtime. Zonder stress. Zonder gedoe.';
        $product->slug = ProductSlug::TRANSFER_SERVICE->value;
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        $productPeriod = ProductPeriod::where('product_id', $product->id)
            ->where('billing_period', 1)
            ->where('contract_period', 1)
            ->firstOrNew();

        $productPeriod->product_id = $product->id;
        $productPeriod->billing_period = 1;
        $productPeriod->contract_period = 1;
        $productPeriod->is_default = true;
        $productPeriod->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 7500;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }
}
