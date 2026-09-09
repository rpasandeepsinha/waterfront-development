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
use Waterfront\Domain\Products\Models\HostingProductComposition;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class YourhostingHostingSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = $this->referenceRepo->get(ProductReference::HOSTING_GROUP, ProductGroup::class);

        // Product weight is important for the package configuration page in the shop
        // Hosting products are seeded ascending

        // web-only
        $this->webOnlyStart($group);
        $this->webOnlyStartWp($group);
        $this->webOnlyGrow($group);
        $this->webOnlyGrowWp($group);
        $this->webOnlyBasic($group);
        $this->webOnlyBasicWp($group);
        $this->webOnlyMini($group);
        $this->webOnlyMiniWp($group);
        $this->webOnlyMax($group);
        $this->webOnlyMaxWp($group);
        $this->webOnlyPlus($group); // currently only available by ordering mailonly-plus or web-plus
        $this->webOnlyPlusWp($group); // currently only available by ordering mailonly-plus or web-plus

        // mail-only
        $this->mailOnlyPlusPlesk($group);
        $this->mailOnlyStartPlesk($group);
        $this->mailOnlyGrowPlesk($group);
        $this->mailOnlyBasicPlesk($group);
        $this->mailOnlyMax($group);
        $this->emailMax($group);
        $this->emailStart($group);

        // web + mail
        $this->webPlus($group);
        $this->webPlusWp($group);
        $this->webMaxWp($group);
        $this->webMax($group);
        $this->webStart($group);
        $this->webStartWp($group);
        $this->webGrow($group);
        $this->webGrowWp($group);
        $this->webBasic($group);
        $this->webBasicWp($group);

        // Addons
        $this->mijnWinkelPlusAddons($group);
        $this->mijnWinkelStartAddons($group);
        $this->easyWpAddons($group);
        $this->avgAddons($group);

        // Composite
        $this->compositeHosting();

        // Old products
        $this->placeholder($group);
        $this->wordPressToolkit($group);
        $this->premium($group);
        $this->brons($group);
        $this->zilver($group);
        $this->groot($group);

        // Misc
        $this->productChangePaths();
    }

    private function mailOnlyBasicPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly basic';
        $product->slug = 'mailonly-basic';
        $product->description = 'mailonly-basic product';
        $product->orderable = true;
        $product->weight = 90;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 6588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN_PROLONGATION_PRICE, $price);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 14861;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 21395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function mailOnlyGrowPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly grow';
        $product->slug = 'mailonly-grow';
        $product->description = 'mailonly-grow product';
        $product->orderable = true;
        $product->weight = 80;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 26843545600, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 9588;
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
        $price->price = 22061;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 31895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function mailOnlyStartPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly start';
        $product->slug = 'mailonly-start';
        $product->description = 'mailonly-start product';
        $product->orderable = true;
        $product->weight = 70;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_START_PLESK, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 26843545600, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_START_PLESK_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 12588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 29261;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 42395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function mailOnlyPlusPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly plus';
        $product->slug = 'mailonly-plus';
        $product->description = 'mailonly-plus product';
        $product->orderable = true;
        $product->weight = 60;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 26843545600, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 36461;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function wordPressToolkit(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Hosting with WordPress ';
        $product->slug = 'technical-delay-hosting';
        $product->description = 'A hosting package that has a spec with wait for wp toolkit';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WORDPRESS_TOOLKIT, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 26843545600, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::WAIT_FOR_WP_TOOLKIT->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WORDPRESS_TOOLKIT_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function webBasic(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web basic';
        $product->slug = 'web-basic';
        $product->description = 'web-basic product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 26843545600, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 25, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => false, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '128M', 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 9588;
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
        $price->price = 22061;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 31895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webBasicWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web basic WP';
        $product->slug = 'web-basic-wp';
        $product->description = 'web-basic-wp product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC_WP, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 26843545600, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 25, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => false, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '128M', 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->price = 9588;
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
        $price->price = 22061;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 31895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function premium(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'premium';
        $product->slug = 'hosting_premium';
        $product->description = 'premium';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::HOSTING_PREMIUM, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 107374182400, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '256M', 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_PREMIUM_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function webGrow(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web grow';
        $product->slug = 'web-grow';
        $product->description = 'web-grow product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_GROW, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 214748364800, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 21474836480, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_GROW_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 12588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 29261;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 42395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webGrowWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web grow WP';
        $product->slug = 'web-grow-wp';
        $product->description = 'web-grow WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_GROW_WP, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 214748364800, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 21474836480, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 12588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 29261;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 42395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyMini(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web only mini';
        $product->slug = 'web-mini';
        $product->description = 'web-mini product';
        $product->orderable = true;
        $product->weight = 50;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_MINI, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 5988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_MINI_REGISTRATION_PRICE, $price);

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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 12575;
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
    }

    private function webOnlyBasic(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly basic';
        $product->slug = 'webonly-basic';
        $product->description = 'webonly-basic product';
        $product->orderable = true;
        $product->weight = 40;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 6588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 14861;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 21395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyBasicWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly basic WP';
        $product->slug = 'webonly-basic-wp';
        $product->description = 'webonly-basic-wp product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC_WP, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 6588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 11988;
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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 14861;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 25175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 21395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyGrow(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly grow';
        $product->slug = 'webonly-grow';
        $product->description = 'webonly-grow product';
        $product->orderable = true;
        $product->weight = 31;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_GROW, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_GROW_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 9588;
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
        $price->price = 22061;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 31895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyGrowWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly grow WP';
        $product->slug = 'webonly-grow-wp';
        $product->description = 'webonly-grow WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_GROW_WP, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

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
        $price->price = 9588;
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
        $price->price = 22061;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 37775;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 31895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyStart(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly start';
        $product->slug = 'webonly-start';
        $product->description = 'webonly-start product';
        $product->orderable = true;
        $product->weight = 20;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_START, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_COMPARISON_BADGE, 'value' => 'pages.steps.cross-sell.hosting.most-popular', 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 23988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_START_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 12588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 29261;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 42395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyStartWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly start WP';
        $product->slug = 'webonly-start-wp';
        $product->description = 'webonly-start WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_START_WP, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 12588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 38381;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 29261;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 50375;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 42395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyPlus(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly plus';
        $product->slug = 'webonly-plus';
        $product->description = 'webonly-plus product';
        $product->orderable = true;
        $product->weight = 50;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_PLUS, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_PLUS_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 36461;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 52895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webOnlyPlusWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly plus WP';
        $product->slug = 'webonly-plus-wp';
        $product->description = 'webonly-plus WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_PLUS_WP, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 36461;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 52895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webStart(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web start';
        $product->slug = 'web-start';
        $product->description = 'web-start product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_START, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 536870912000, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 53687091200, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 29988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_START_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 36461;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 52895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webStartWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web start WP';
        $product->slug = 'web-start-wp';
        $product->description = 'web-start WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_START_WP, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 536870912000, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 53687091200, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 15588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 47981;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 36461;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 62975;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 52895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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

    private function webPlus(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web plus';
        $product->slug = 'web-plus';
        $product->description = 'web-plus product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_PLUS, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 536870912000, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 53687091200, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 35988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_PLUS_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 18588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 57581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 43661;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 63395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
    }

    private function webPlusWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web plus WP';
        $product->slug = 'web-plus-wp';
        $product->description = 'web-plus WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_PLUS_WP, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 536870912000, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 53687091200, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 18588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 57581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 43661;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 63395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
    }

    private function brons(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'brons';
        $product->slug = 'hosting_brons';
        $product->description = 'brons';
        $product->orderable = true;
        $product->weight = 999;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::HOSTING_BRONZE, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => 536870912000, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 5368709120, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_BRONZE_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
        $this->referenceRepo->set(ProductReference::HOSTING_BRONZE_PROLONGATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 1;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function zilver(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'zilver';
        $product->slug = 'hosting_zilver';
        $product->description = 'zilver';
        $product->orderable = true;
        $product->weight = 999;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::HOSTING_SILVER, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_box', 'value' => 75, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 20, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function groot(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'groot';
        $product->slug = 'hosting_groot';
        $product->description = 'groot';
        $product->orderable = true;
        $product->weight = 999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_GROOT, $product);

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_box', 'value' => 200, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 66, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 1200;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function placeholder(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Hosting placeholder product';
        $product->slug = 'hosting_placeholder';
        $product->description = 'Hosting product that uses a placeholder driver';
        $product->orderable = false;
        $product->weight = 999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PLACEHOLDER, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::HOSTING_PLACEHOLDER_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function productChangePaths(): void
    {
        $bronzeProduct = $this->referenceRepo->get(ProductReference::HOSTING_BRONZE, Product::class);
        $zilverProduct = $this->referenceRepo->get(ProductReference::HOSTING_SILVER, Product::class);
        $grootProduct = $this->referenceRepo->get(ProductReference::HOSTING_GROOT, Product::class);

        $bronzeToSilverUpgrade = new ProductAllowedChange();
        $bronzeToSilverUpgrade->from_product_id = $bronzeProduct->id;
        $bronzeToSilverUpgrade->to_product_id = $zilverProduct->id;
        $bronzeToSilverUpgrade->change_type = ProductChangeType::UPGRADE;
        $bronzeToSilverUpgrade->is_available_for_customer = true;
        $bronzeToSilverUpgrade->display_order = 1;
        $bronzeToSilverUpgrade->save();

        $zilverToGrootUpgrade = new ProductAllowedChange();
        $zilverToGrootUpgrade->from_product_id = $zilverProduct->id;
        $zilverToGrootUpgrade->to_product_id = $grootProduct->id;
        $zilverToGrootUpgrade->change_type = ProductChangeType::UPGRADE;
        $zilverToGrootUpgrade->is_available_for_customer = true;
        $zilverToGrootUpgrade->display_order = 2;
        $zilverToGrootUpgrade->save();

        $bronzeToGrootSupportOnlyUpgrade = new ProductAllowedChange();
        $bronzeToGrootSupportOnlyUpgrade->from_product_id = $bronzeProduct->id;
        $bronzeToGrootSupportOnlyUpgrade->to_product_id = $grootProduct->id;
        $bronzeToGrootSupportOnlyUpgrade->change_type = ProductChangeType::UPGRADE;
        $bronzeToGrootSupportOnlyUpgrade->is_available_for_customer = false;
        $bronzeToGrootSupportOnlyUpgrade->display_order = 3;
        $bronzeToGrootSupportOnlyUpgrade->save();

        $GrootToBronzeSupportOnlyDowngrade = new ProductAllowedChange();
        $GrootToBronzeSupportOnlyDowngrade->from_product_id = $grootProduct->id;
        $GrootToBronzeSupportOnlyDowngrade->to_product_id = $bronzeProduct->id;
        $GrootToBronzeSupportOnlyDowngrade->change_type = ProductChangeType::DOWNGRADE;
        $GrootToBronzeSupportOnlyDowngrade->display_order = 1;
        $GrootToBronzeSupportOnlyDowngrade->is_available_for_customer = false;
        $GrootToBronzeSupportOnlyDowngrade->save();

        $grootToZilverDowngrade = new ProductAllowedChange();
        $grootToZilverDowngrade->from_product_id = $grootProduct->id;
        $grootToZilverDowngrade->to_product_id = $zilverProduct->id;
        $grootToZilverDowngrade->change_type = ProductChangeType::DOWNGRADE;
        $grootToZilverDowngrade->display_order = 2;
        $grootToZilverDowngrade->is_available_for_customer = true;
        $grootToZilverDowngrade->save();

        $zilverToBronzeDowngrade = new ProductAllowedChange();
        $zilverToBronzeDowngrade->from_product_id = $zilverProduct->id;
        $zilverToBronzeDowngrade->to_product_id = $bronzeProduct->id;
        $zilverToBronzeDowngrade->change_type = ProductChangeType::DOWNGRADE;
        $zilverToBronzeDowngrade->is_available_for_customer = true;
        $zilverToBronzeDowngrade->display_order = 3;
        $zilverToBronzeDowngrade->save();
    }

    private function compositeHosting(): void
    {
        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class)->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP, Product::class)->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN, Product::class)->id;
        $composition->web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC, Product::class)->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC_WP, Product::class)->id;
        $composition->save();

        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW, Product::class)->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW_WP, Product::class)->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK, Product::class)->id;
        $composition->web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW, Product::class)->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW_WP, Product::class)->id;
        $composition->save();

        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START, Product::class)->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START_WP, Product::class)->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_START_PLESK, Product::class)->id;
        $composition->web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START, Product::class)->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START_WP, Product::class)->id;
        $composition->save();

        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_PLUS, Product::class)->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_PLUS_WP, Product::class)->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK, Product::class)->id;
        $composition->web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_PLUS, Product::class)->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_PLUS_WP, Product::class)->id;
        $composition->save();
    }

    private function webOnlyMiniWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web Only Mini Wordpress';
        $product->slug = 'web-mini-wp';
        $product->description = 'web-mini-wp product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_traffic', 'value' => 536870912000, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 53687091200, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 100, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 15, 'product_id' => $product->id],
            ['name' => 'hosting.permissions.manage_crontab', 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.php-settings.memory_limit', 'value' => '512M', 'product_id' => $product->id],
        ]);

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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 9588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 9588;
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

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 12575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function webOnlyMax(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Max web';
        $product->slug = 'webonly-max';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => -1, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 18588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 27581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 43661;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 63395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
    }

    private function webOnlyMaxWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'WordPress Max web';
        $product->slug = 'web-only-max-wp';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => false, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => -1, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 18588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 27581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 43661;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 63395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
    }

    private function mailOnlyMax(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Max mail';
        $product->slug = 'mailonly-max';
        $product->description = 'Mailen met je eigen domeinnaam.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => -1, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 18588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 57581;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 43661;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 75575;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 63395;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
    }

    private function emailMax(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'E-mail Max';
        $product->slug = 'email-max';
        $product->description = 'E-mail only hosting pakket Max';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => -1, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 21474836480, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 250, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 0, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box_size', 'value' => 16106127360, 'product_id' => $product->id],
            ['name' => 'hosting.limits.advised_box_size', 'value' => 2147483648, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SERVICES_SPAM_FILTER, 'value' => 1, 'product_id' => $product->id],
            ['name' => 'hosting.services.outgoing_email', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 21588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 21588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 34541;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 26381;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 34541;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 45335;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 38195;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 45335;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function emailStart(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'E-mail Start';
        $product->slug = 'email-start';
        $product->description = 'Zakelijke e-mail vanaf je domeinnaam, beschermd door ons spamfilter.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => -1, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 0, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box_size', 'value' => 5368709120, 'product_id' => $product->id],
            ['name' => 'hosting.limits.advised_box_size', 'value' => 2147483648, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SERVICES_SPAM_FILTER, 'value' => 1, 'product_id' => $product->id],
            ['name' => 'hosting.services.outgoing_email', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->price = 9588;
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
        $price->price = 22061;
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

    private function webMaxWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'WordPress Max';
        $product->slug = 'web-max-wp';
        $product->description = 'Uitgebreide mogelijkheden voor complexere websites of webshops.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::WAIT_FOR_WP_TOOLKIT, 'value' => 0, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 21588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 67181;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 24;
        $price->billing_period = 24;
        $price->price = 50861;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 88175;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 73895;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
    }

    private function webMax(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Max (Web + Mail)';
        $product->slug = 'web-max';
        $product->description = 'Uitgebreide mogelijkheden voor complexere websites of webshops.';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'hosting.limits.max_traffic', 'value' => -1, 'product_id' => $product->id],
            ['name' => 'hosting.limits.disk_space', 'value' => 10737418240, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box', 'value' => 50, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_db', 'value' => 0, 'product_id' => $product->id],
            ['name' => 'hosting.limits.max_box_size', 'value' => 5368709120, 'product_id' => $product->id],
            ['name' => 'hosting.limits.advised_box_size', 'value' => 2147483648, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SERVICES_SPAM_FILTER, 'value' => 1, 'product_id' => $product->id],
            ['name' => 'hosting.services.outgoing_email', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value, 'value' => true, 'product_id' => $product->id],
        ]);

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
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 21588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
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
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 36;
        $price->billing_period = 36;
        $price->price = 88175;
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
    }

    private function mijnWinkelPlusAddons(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'MijnWebwinkel Plus';
        $product->slug = 'mijnwinkel_plus_addons';
        $product->description = 'MijnWebwinkel Plus';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function mijnWinkelStartAddons(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'MijnWebwinkel Start';
        $product->slug = 'mijnwinkel_start_addons';
        $product->description = 'MijnWebwinkel Start';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function easyWpAddons(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Easy WordPress';
        $product->slug = 'easy_wp_addons';
        $product->description = 'Easy WordPress installatie met Extendify voor een simpele start met WordPress zonder dat je een nieuwe hobby erbij neemt.';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::INTRODUCTION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function avgAddons(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'AVG Addon';
        $product->slug = 'avg_addons';
        $product->description = 'avg_addons product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::INTRODUCTION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 3588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 3588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 3588;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 3588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }
}
