<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Models\ProductGroup;

class VolumeDiscountSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Volume Discount';
        $group->ledger_code = 8008;
        $group->slug = ProductGroupType::VOLUME_DISCOUNT;
        $group->save();

        $this->volumeDiscountBronsProduct($group);
        $this->volumeDiscountBronsHosting();
    }

    private function volumeDiscountBronsProduct(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Volume Discount Brons';
        $product->slug = 'volume-discount-brons';
        $product->weight = 1;
        $product->orderable = true;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::VOLUME_DISCOUNT_BRONS, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 20000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::VOLUME_DISCOUNT_BRONS_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 20000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function volumeDiscountBronsHosting(): void
    {
        $volumeDiscountProduct = $this->referenceRepo->get(ProductReference::VOLUME_DISCOUNT_BRONS, Product::class);
        $hostingProduct = $this->referenceRepo->get(ProductReference::HOSTING_BRONZE, Product::class);

        $volumeDiscount = new ProductDiscount();
        $volumeDiscount->name = 'Volume Discount hosting Brons';
        $volumeDiscount->product_id = $volumeDiscountProduct->id;
        $volumeDiscount->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $hostingProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 500;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        DB::table('product_discount_prices')->insert([
            'product_discount_id' => $volumeDiscount->id,
            'price_id' => $price->id,
        ]);

        $this->referenceRepo->set(
            ProductReference::VOLUME_DISCOUNT_HOSTING_BRONS_PRODUCT_DISCOUNT_REGISTRATION_PRICE,
            $price,
        );
        $this->referenceRepo->set(ProductReference::VOLUME_DISCOUNT_HOSTING_BRONS_PRODUCT_DISCOUNT, $volumeDiscount);
    }
}
