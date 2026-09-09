<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Database\Seeders\Support\ProductPriceGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;

class OtherSeeder extends Seeder
{
    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Overig';
        $group->slug = ProductGroupType::OTHER;
        $group->ledger_code = 8011;
        $group->default_billing_period = 1;
        $group->default_contract_period = 1;
        $group->save();

        $this->webshopStart($group);
        $this->logoDesignService($group);
        $this->wordpressUpdateService($group);
        $this->administrationFees($group);
    }

    private function webshopStart(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webshop Start';
        $product->slug = 'webshop_start';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        ProductPriceGenerator::generateStandardPrices($product, 2999);
    }

    private function logoDesignService(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Logo ontwerp service';
        $product->slug = 'logo_design_service';
        $product->description = 'Doorlopende dienstverlening';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();

        ProductPriceGenerator::generateStandardPrices($product, 999);
    }

    private function wordpressUpdateService(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'WP update service';
        $product->slug = 'webmasterservice_eenmalig_wordpress_updaten';
        $product->description = 'Doorlopende dienstverlening';
        $product->orderable = true;
        $product->weight = 2;
        $product->product_group_id = $group->id;
        $product->save();

        ProductPriceGenerator::generateStandardPrices($product, 1999);
    }

    private function administrationFees(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Administration fees';
        $product->slug = ProductType::ADMINISTRATION_FEES->value;
        $product->description = 'Administratiekosten';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
    }
}
