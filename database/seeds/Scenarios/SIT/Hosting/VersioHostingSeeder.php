<?php

declare(strict_types=1);

namespace Database\Seeders\Scenarios\SIT\Hosting;

use Carbon\CarbonImmutable;
use Database\Seeders\Products\ProductReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;

class VersioHostingSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = $this->referenceRepo->get(ProductReference::HOSTING_GROUP, ProductGroup::class);

        $this->completeWeb($group);
        $this->completeMail($group);
        $this->maxWeb($group);
        $this->maxMail($group);
        $this->startWeb($group);
        $this->startMail($group);
        $this->basicWeb($group);
        $this->basicMail($group);
        $this->webOnly($group);
        $this->basic($group);
        $this->mailOnly($group);
        $this->power($group);
        $this->complete($group);
        $this->start($group);
        $this->max($group);
    }

    private function basic(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Basic';
        $product->slug = 'basic';
        $product->description = 'De basis voor het maken van een website of blog';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 2, 'product_id' => $product->id],
            ['name' => 'hosting.limits.virtual_hosts', 'value' => 2, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 99;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 13788;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 28781;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 25421;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 28781;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function completeWeb(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Complete web';
        $product->slug = 'complete-web';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 20;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_db', 'value' => -1, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function completeMail(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Complete mail';
        $product->slug = 'complete-mail';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 70;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_box', 'value' => -1, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function maxWeb(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Max web';
        $product->slug = 'max-web';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_db', 'value' => -1, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function maxMail(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Max mail';
        $product->slug = 'max-mail';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 60;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_box', 'value' => -1, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function startWeb(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Start web';
        $product->slug = 'start-web';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 30;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_db', 'value' => -1, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 28781;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 28781;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function startMail(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Start mail';
        $product->slug = 'start-mail';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 80;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 28781;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 28781;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function basicWeb(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Basic web';
        $product->slug = 'basic-web';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 40;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_db', 'value' => 2, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 19181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 19181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function basicMail(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Basic mail';
        $product->slug = 'basic-mail';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 90;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 19181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 19181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function webOnly(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web-Only';
        $product->slug = 'web-only';
        $product->description = '';
        $product->orderable = true;
        $product->weight = 50;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.disk_space', 'value' => 5368709120, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'hosting.limits.virtual_hosts', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 99;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = false;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 5988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 5988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 13788;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 9581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 9581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 12575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 12575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function mailOnly(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mail & Spam';
        $product->slug = 'mail_only';
        $product->description = 'E-mailadressen beschermd door een spamfilter.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.services.outgoing_email', 'value' => 'yes', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SERVICES_SPAM_FILTER, 'value' => 'yes', 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LEGACY_MAIL_ONLY->value, 'value' => true, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1299;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1299;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 24941;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 24941;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 32735;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 32735;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function power(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Power';
        $product->slug = 'power';
        $product->description = 'Uitgebreide mogelijkheden voor complexere websites of webshops.';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.virtual_hosts', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 3499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 3499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 41988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 41988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 67181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 67181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 88175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 88175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function complete(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Complete';
        $product->slug = 'complete';
        $product->description = 'Alles wat je nodig hebt voor je website';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.virtual_hosts', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 21474836480, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 6;
        $price->billing_period = 6;
        $price->price = 14994;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2499;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function start(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Start';
        $product->slug = 'start';
        $product->description = 'De basis voor een website of webshop.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.virtual_hosts', 'value' => 2, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 16106127360, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1999;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 6;
        $price->billing_period = 6;
        $price->price = 11994;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 60;
        $price->billing_period = 60;
        $price->price = 89940;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1999;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function max(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Max';
        $product->slug = 'max';
        $product->description = 'Meer ruimte en mogelijkheden voor jouw website of webshop.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.virtual_hosts', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2999;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2999;
        $price->orderable = false;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 35988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 35988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 57581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 57581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }
}
