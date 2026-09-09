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
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;

class ResellerHostingSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Reseller hosting';
        $group->ledger_code = 8011;
        $group->slug = ProductGroupType::RESELLER_HOSTING;
        $group->save();
        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_GROUP, $group);

        $this->resellerBrons();
        $this->resellerSilver();
        $this->resellerGold();
        $this->provisioningResellerHostingDirectAdmin($group);
        $this->provisioningResellerHostingPlesk($group);
    }

    private function resellerBrons(): void
    {
        $group = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_GROUP, ProductGroup::class);
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Reseller Brons';
        $product->slug = 'hosting_reseller_brons';
        $product->description = 'Reseller hosting start';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_BRONS, $product);

        ProductSpec::insert([
            ['name' => 'resellerhosting.limits.max_traffic', 'value' => 4294967296, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_domains', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_users', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_databases', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_email_addresses', 'value' => 10, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 5000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_BRONS_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 5000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_BRONS_PROLONGATION_PRICE, $price);
    }

    private function resellerSilver(): void
    {
        $group = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_GROUP, ProductGroup::class);
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Reseller Silver';
        $product->slug = 'reseller-hosting-silver';
        $product->description = 'Reseller hosting professional';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_SILVER, $product);

        ProductSpec::insert([
            ['name' => 'resellerhosting.limits.max_traffic', 'value' => 8294967296, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.disk_space', 'value' => 170737418240, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_domains', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_users', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_databases', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_email_addresses', 'value' => 100, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 7000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_SILVER_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 7000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function resellerGold(): void
    {
        $group = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_GROUP, ProductGroup::class);
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Reseller gold';
        $product->slug = 'reseller-hosting-gold';
        $product->description = 'Reseller hosting expert';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_GOLD, $product);

        ProductSpec::insert([
            ['name' => 'resellerhosting.limits.max_traffic', 'value' => 8294967296, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.disk_space', 'value' => 'ssddx', 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_domains', 'value' => 170737418240, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_users', 'value' => 1000, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_databases', 'value' => 1000, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_email_addresses', 'value' => 1000, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10000;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::RESELLER_HOSTING_GOLD_REGISTRATION_PRICE, $price);

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

    private function provisioningResellerHostingDirectAdmin(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Provisioning reseller hosting (DirectAdmin)';
        $product->slug = 'provisioning-reseller-hosting-directadmin';
        $product->description = 'A provision reseller hosting product within the new Provision Domain.';
        $product->orderable = true;
        $product->weight = 1337;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'resellerhosting.limits.max_traffic', 'value' => 4294967296, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_domains', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_users', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_databases', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_email_addresses', 'value' => 10, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 1999);
    }

    private function provisioningResellerHostingPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Provisioning reseller hosting (Plesk)';
        $product->slug = 'provisioning-reseller-hosting-plesk';
        $product->description = 'A provision reseller hosting product within the new Provision Domain.';
        $product->orderable = true;
        $product->weight = 1337;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'resellerhosting.limits.max_traffic', 'value' => 4294967296, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_domains', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_users', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_databases', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'resellerhosting.limits.max_email_addresses', 'value' => 10, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 1999);
    }
}
