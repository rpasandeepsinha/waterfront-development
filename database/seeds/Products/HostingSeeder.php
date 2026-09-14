<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ProductPriceGenerator;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\DNS\Models\DnsTemplate;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSet;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSetRow;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\HostingProductComposition;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\Models\ProviderSetting;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class HostingSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Hosting';
        $group->ledger_code = 8008;
        $group->slug = ProductGroupType::HOSTING;
        $group->default_contract_period = 36;
        $group->default_billing_period = 1;
        $group->save();
        $this->referenceRepo->set(ProductReference::HOSTING_GROUP, $group);

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

        // mail-only
        $this->mailOnlyPlusPlesk($group);
        $this->mailOnlyStartPlesk($group);
        $this->mailOnlyGrowPlesk($group);
        $this->mailOnlyBasicDirectAdmin($group);

        // currently only available by ordering mailonly-plus or web-plus
        $this->webOnlyPlus($group);
        $this->webOnlyPlusWp($group);

        // web + mail
        $this->webPlus($group);
        $this->webPlusWp($group);
        $this->webStart($group);
        $this->webStartWp($group);
        $this->webGrow($group);
        $this->webGrowWp($group);
        $this->webBasic($group);
        $this->webBasicWp($group);

        $this->compositeHosting();

        // misc/old products
        $this->placeholder($group);
        $this->wordPressToolkit($group);
        $this->mailOnly($group);
        $this->premium($group);
        $this->brons($group);
        $this->zilver($group);
        $this->groot($group);
        $this->provisioningHosting($group);

        $this->servers();
        $this->dnsTemplates();
        $this->providers();
        $this->spamExperts();
        $this->oldProductChangePaths();
        $this->newProductChangePaths();
    }

    private function mailOnlyBasicDirectAdmin(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly basic';
        $product->slug = 'local-mailonly-basic';
        $product->description = 'Local mailonly-basic product';
        $product->orderable = true;
        $product->weight = 90;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SSL_SOLD_SEPARATELY, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 999);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();
        $prolongationPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', ProductPriceType::PROLONGATION)
            ->firstOrFail();

        $this->referenceRepo->set(
            ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN_REGISTRATION_PRICE,
            $defaultPrice,
        );
        $this->referenceRepo->set(
            ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN_PROLONGATION_PRICE,
            $prolongationPrice,
        );
    }

    private function mailOnlyGrowPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly grow';
        $product->slug = 'local-mailonly-grow';
        $product->description = 'Local mailonly-grow product';
        $product->orderable = true;
        $product->weight = 80;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 26843545600,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => '0', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SSL_SOLD_SEPARATELY, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK_REGISTRATION_PRICE, $defaultPrice);
    }

    private function mailOnlyStartPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly start';
        $product->slug = 'local-mailonly-start';
        $product->description = 'Local mailonly-start product';
        $product->orderable = true;
        $product->weight = 70;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_START_PLESK, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 26843545600,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => '0', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SSL_SOLD_SEPARATELY, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1999);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_START_PLESK_REGISTRATION_PRICE, $defaultPrice);
    }

    private function mailOnlyPlusPlesk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mailonly plus';
        $product->slug = 'local-mailonly-plus';
        $product->description = 'Local mailonly-plus product';
        $product->orderable = true;
        $product->weight = 60;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 26843545600,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => '0', 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SSL_SOLD_SEPARATELY, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 2499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK_REGISTRATION_PRICE, $defaultPrice);
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
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 26843545600,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 5, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::WAIT_FOR_WP_TOOLKIT->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 2499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WORDPRESS_TOOLKIT_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webBasic(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web basic';
        $product->slug = 'local-web-basic';
        $product->description = 'Local web-basic product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 26843545600,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 25, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 5, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => false,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '128M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::SERVICES_TECHNICAL_GRACE_PERIOD, 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webBasicWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web basic WP';
        $product->slug = 'local-web-basic-wp';
        $product->description = 'Local web-basic-wp product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 26843545600,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 2684354560, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 25, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 5, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => false,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '128M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::SERVICES_TECHNICAL_GRACE_PERIOD, 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_BASIC_WP_REGISTRATION_PRICE, $defaultPrice);
    }

    private function mailOnly(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Mail & Spam';
        $product->slug = 'mail_only';
        $product->description = 'Email managed in Coast';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => 1, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => 0, 'product_id' => $product->id],
            ['name' => ProductSpecName::SHOP_IS_WEBHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SSL_SOLD_SEPARATELY, 'value' => 0, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_SERVICES_SPAM_FILTER, 'value' => 'yes', 'product_id' => $product->id],
            ['name' => ProductSpecName::SERVICES_TECHNICAL_GRACE_PERIOD, 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LEGACY_MAIL_ONLY, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1299);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_REGISTRATION_PRICE, $defaultPrice);
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
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 107374182400,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 10737418240,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 10, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '256M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::SERVICES_TECHNICAL_GRACE_PERIOD, 'value' => 30, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 399);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_PREMIUM_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webGrow(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web grow';
        $product->slug = 'local-web-grow';
        $product->description = 'Local web-grow product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_GROW, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 214748364800,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 21474836480,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '512M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1999);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_GROW_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webGrowWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web grow WP';
        $product->slug = 'local-web-grow-wp';
        $product->description = 'Local web-grow WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_GROW_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 214748364800,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 21474836480,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '512M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 1999);
    }

    private function webOnlyMini(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web only mini';
        $product->slug = 'local-web-mini';
        $product->description = 'Local web-mini product';
        $product->orderable = true;
        $product->weight = 50;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_MINI, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::SHOP_IS_MAILHOSTING_PRODUCT, 'value' => false, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_MINI_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webOnlyBasic(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly basic';
        $product->slug = 'local-webonly-basic';
        $product->description = 'Local webonly-basic product';
        $product->orderable = true;
        $product->weight = 40;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 999);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webOnlyBasicWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly basic WP';
        $product->slug = 'local-webonly-basic-wp';
        $product->description = 'Local webonly-basic-wp product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 999);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_BASIC_WP_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webOnlyGrow(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly grow';
        $product->slug = 'local-webonly-grow';
        $product->description = 'Local webonly-grow product';
        $product->orderable = true;
        $product->weight = 31;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_GROW, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1499);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_GROW_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webOnlyGrowWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly grow WP';
        $product->slug = 'local-webonly-grow-wp';
        $product->description = 'Local webonly-grow WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_GROW_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 1499);
    }

    private function webOnlyStart(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly start';
        $product->slug = 'local-webonly-start';
        $product->description = 'Local webonly-start product';
        $product->orderable = true;
        $product->weight = 20;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_START, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::PRODUCT_COMPARISON_BADGE,
                'value' => 'pages.steps.cross-sell.hosting.most-popular',
                'product_id' => $product->id,
            ],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1999);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_START_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webOnlyStartWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly start WP';
        $product->slug = 'local-webonly-start-wp';
        $product->description = 'Local webonly-start WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_START_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 1999);
    }

    private function webOnlyPlus(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly plus';
        $product->slug = 'local-webonly-plus';
        $product->description = 'Local webonly-plus product';
        $product->orderable = true;
        $product->weight = 50;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_PLUS, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 2499);

        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_PLUS_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webOnlyPlusWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Webonly plus WP';
        $product->slug = 'local-webonly-plus-wp';
        $product->description = 'Local webonly-plus WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_ONLY_PLUS_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => false,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 2499);
    }

    private function webStart(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web start';
        $product->slug = 'local-web-start';
        $product->description = 'Local web-start product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_START, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 536870912000,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 53687091200,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '512M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 2499);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_START_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webStartWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web start WP';
        $product->slug = 'local-web-start-wp';
        $product->description = 'Local web-start WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_START_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 536870912000,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 53687091200,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '512M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 2499);
    }

    private function webPlus(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web plus';
        $product->slug = 'local-web-plus';
        $product->description = 'Local web-plus product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_PLUS, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 536870912000,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 53687091200,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '512M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 2999);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_WEB_PLUS_REGISTRATION_PRICE, $defaultPrice);
    }

    private function webPlusWp(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Web plus WP';
        $product->slug = 'local-web-plus-wp';
        $product->description = 'Local web-plus WP product';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_WEB_PLUS_WP, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 536870912000,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE,
                'value' => 53687091200,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 100, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            [
                'name' => ProductSpecName::HOSTING_PHP_SETTINGS_MEMORY_LIMIT,
                'value' => '512M',
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 2999);
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
            [
                'name' => ProductSpecName::HOSTING_LIMITS_MAX_TRAFFIC,
                'value' => 536870912000,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_LIMITS_DISK_SPACE, 'value' => 5368709120, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 50, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 15, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 399);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();
        $prolongationPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', ProductPriceType::PROLONGATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_BRONZE_REGISTRATION_PRICE, $defaultPrice);
        $this->referenceRepo->set(ProductReference::HOSTING_BRONZE_PROLONGATION_PRICE, $prolongationPrice);
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
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 75, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 20, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 399);
        $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();
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
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_BOX, 'value' => 200, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_LIMITS_MAX_DB, 'value' => 66, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::HOSTING_PERMISSIONS_MANAGE_CRONTAB,
                'value' => true,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 399);
        $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();
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

    private function dnsTemplates(): void
    {
        $template = new DnsTemplate();
        $template->slug = 'hosting';
        $template->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'mail.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'smtp.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'www.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '*.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'MX';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '10 primary.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '20 fallback.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'TXT';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '"v=spf1 include:_spf.sandwave.testing mx a ~all"';
        $row->template_record_set_id = $set->id;
        $row->save();

        $template = new DnsTemplate();
        $template->slug = 'external-hosting';
        $template->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'mail.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'smtp.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = 'www.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '*.{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'A';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ipv4}';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'MX';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '10 primary.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '20 fallback.sandwave.testing.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'TXT';
        $set->ttl = 600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '"v=spf1 include:_spf.sandwave.testing mx a ~all"';
        $row->template_record_set_id = $set->id;
        $row->save();
    }

    private function providers(): void
    {
        $provider = new Provider();
        $provider->slug = ProviderSlug::DIRECTADMIN;
        $provider->type = ProviderType::HOSTING;
        $provider->enabled = true;
        $provider->default = true;
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, $provider);
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_DEFAULT, $provider);

        $provider = new Provider();
        $provider->slug = ProviderSlug::PLESK;
        $provider->type = ProviderType::HOSTING;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_PLESK, $provider);

        $provider = new Provider();
        $provider->slug = ProviderSlug::PLACEHOLDER;
        $provider->type = ProviderType::HOSTING;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_PLACEHOLDER, $provider);

        $provider = new Provider();
        $provider->slug = ProviderSlug::PLESK;
        $provider->type = ProviderType::MAILONLY;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();

        $this->referenceRepo->set(ProductReference::HOSTING_PROVIDER_MAIL_ONLY_PLESK, $provider);

        $server = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class);
        $provider = new Provider();
        $provider->type = ProviderType::MAILONLY;
        $provider->slug = ProviderSlug::DIRECTADMIN;
        $provider->enabled = true;
        $provider->default = true;
        $provider->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::LIMIT;
        $providerSetting->value = '99';
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::QUOTA;
        $providerSetting->value = '6666';
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::DEFAULTSERVERID;
        $providerSetting->value = (string) $server->id;
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, $provider);

        $provider = new Provider();
        $provider->type = ProviderType::MAILONLY;
        $provider->slug = ProviderSlug::PLACEHOLDER;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_PLACEHOLDER, $provider);
    }

    private function servers(): void
    {
        $server = new Server();
        $server->type = ServerType::PLESK;
        $server->name = ServerType::PLESK->value;
        $server->hostname = 'mock';
        $server->domain = 'mock';
        $server->endpoint = '/plesk';
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::1';
        $server->port = 3000;
        $server->use_ssl = false;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->secret_key = '53de495a-b405-ca64-35d3-54b0352edeec';
        $server->save();
        $this->referenceRepo->set(ProductReference::HOSTING_SERVER_PLESK, $server);

        $server = new Server();
        $server->type = ServerType::DIRECTADMIN;
        $server->name = ServerType::DIRECTADMIN->value;
        $server->hostname = 'mock.sandwaveio.dev';
        $server->domain = 'mock.sandwaveio.dev';
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::1';
        $server->port = 80;
        $server->use_ssl = false;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->username = 'admin';
        $server->password = 'changeme';
        $server->loginkey = 'TestLoginKey';
        $server->save();
        $this->referenceRepo->set(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, $server);

        $server = new Server();
        $server->type = ServerType::DIRECTADMIN_MAIL;
        $server->name = ServerType::DIRECTADMIN_MAIL->value;
        $server->hostname = 'mock.sandwaveio.dev/mail';
        $server->domain = 'mock.sandwaveio.dev/mail';
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::1';
        $server->port = 80;
        $server->use_ssl = false;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->username = 'admin';
        $server->password = 'changeme';
        $server->loginkey = 'TestLoginKey';
        $server->save();
        $this->referenceRepo->set(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, $server);
    }

    private function spamExperts(): void
    {
        $cluster = new SpamExpertsCluster();
        $cluster->hostname = 'https://mock.sandwaveio.dev';
        $cluster->business_unit = 'Versio';
        $cluster->username = 'mock';
        $cluster->password = 'mock_encrypted_password';
        $cluster->ssl = false;
        $cluster->save();
    }

    private function oldProductChangePaths(): void
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

    private function newProductChangePaths(): void
    {
        $webMiniProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_MINI, Product::class);
        $basicWebOnlyProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC, Product::class);
        $basicWebOnlyWpProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC_WP, Product::class);
        $basicMailOnlyProduct = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN,
            Product::class,
        );
        $basicProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class);
        $basicWpProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP, Product::class);
        $growWebOnlyProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW, Product::class);
        $growWebOnlyWpProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW_WP, Product::class);
        $growMailOnlyProduct = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK,
            Product::class,
        );
        $growProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW, Product::class);
        $growWpProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW_WP, Product::class);
        $startWebOnlyProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START, Product::class);
        $startWebOnlyWpProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START_WP, Product::class);
        $startMailOnlyProduct = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_START_PLESK,
            Product::class,
        );
        $startProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START, Product::class);
        $startWpProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START_WP, Product::class);

        $paths = [
            [$webMiniProduct,        $basicProduct],
            [$webMiniProduct,        $basicWebOnlyProduct],
            [$webMiniProduct,        $growProduct],
            [$webMiniProduct,        $growWebOnlyProduct],
            [$webMiniProduct,        $startProduct],
            [$webMiniProduct,        $startWebOnlyProduct],

            [$basicWebOnlyProduct,   $basicProduct],
            [$basicWebOnlyProduct,   $growProduct],
            [$basicWebOnlyProduct,   $startProduct],
            [$basicWebOnlyProduct,   $growWebOnlyProduct],
            [$basicWebOnlyProduct,   $startWebOnlyProduct],
            [$basicWebOnlyWpProduct, $basicWpProduct],
            [$basicWebOnlyWpProduct, $growWpProduct],
            [$basicWebOnlyWpProduct, $startWpProduct],
            [$basicWebOnlyWpProduct, $growWebOnlyWpProduct],
            [$basicWebOnlyWpProduct, $startWebOnlyWpProduct],

            [$basicMailOnlyProduct,  $basicProduct],
            [$basicMailOnlyProduct,  $basicWpProduct],
            [$basicMailOnlyProduct,  $growProduct],
            [$basicMailOnlyProduct,  $growWpProduct],
            [$basicMailOnlyProduct,  $startProduct],
            [$basicMailOnlyProduct,  $startWpProduct],
            [$basicMailOnlyProduct,  $growMailOnlyProduct],
            [$basicMailOnlyProduct,  $startMailOnlyProduct],

            [$basicProduct,          $growProduct],
            [$basicProduct,          $startProduct],
            [$basicWpProduct,        $growWpProduct],
            [$basicWpProduct,        $startWpProduct],

            [$growWebOnlyProduct,    $growProduct],
            [$growWebOnlyProduct,    $startProduct],
            [$growWebOnlyProduct,    $startWebOnlyProduct],
            [$growWebOnlyWpProduct,  $growWpProduct],
            [$growWebOnlyWpProduct,  $startWpProduct],
            [$growWebOnlyWpProduct,  $startWebOnlyWpProduct],

            [$growMailOnlyProduct,   $growProduct],
            [$growMailOnlyProduct,   $growWpProduct],
            [$growMailOnlyProduct,   $startProduct],
            [$growMailOnlyProduct,   $startWpProduct],
            [$growMailOnlyProduct,   $startMailOnlyProduct],

            [$growProduct,           $startProduct],
            [$growWpProduct,         $startWpProduct],

            [$startWebOnlyProduct,   $startProduct],
            [$startWebOnlyWpProduct, $startWpProduct],

            [$startMailOnlyProduct,  $startProduct],
            [$startMailOnlyProduct,  $startWpProduct],
        ];

        foreach ($paths as $index => $path) {
            [$fromProduct, $toProduct] = $path;

            $upgrade = new ProductAllowedChange();
            $upgrade->from_product_id = $fromProduct->id;
            $upgrade->to_product_id = $toProduct->id;
            $upgrade->change_type = ProductChangeType::UPGRADE;
            $upgrade->is_available_for_customer = true;
            $upgrade->display_order = $index + 1;
            $upgrade->save();

            $downgrade = new ProductAllowedChange();
            $downgrade->from_product_id = $toProduct->id;
            $downgrade->to_product_id = $fromProduct->id;
            $downgrade->change_type = ProductChangeType::DOWNGRADE;
            $downgrade->is_available_for_customer = false;
            $downgrade->display_order = $index + 1;
            $downgrade->save();
        }
    }

    private function compositeHosting(): void
    {
        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_BASIC,
            Product::class,
        )->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_BASIC_WP,
            Product::class,
        )->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN,
            Product::class,
        )->id;
        $composition->web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_BASIC,
            Product::class,
        )->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_BASIC_WP,
            Product::class,
        )->id;
        $composition->save();

        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_GROW,
            Product::class,
        )->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_GROW_WP,
            Product::class,
        )->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK,
            Product::class,
        )->id;
        $composition->web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_GROW,
            Product::class,
        )->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_GROW_WP,
            Product::class,
        )->id;
        $composition->save();

        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_START,
            Product::class,
        )->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_START_WP,
            Product::class,
        )->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_START_PLESK,
            Product::class,
        )->id;
        $composition->web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_START,
            Product::class,
        )->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_START_WP,
            Product::class,
        )->id;
        $composition->save();

        $composition = new HostingProductComposition();
        $composition->composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_PLUS,
            Product::class,
        )->id;
        $composition->wp_composed_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_PLUS_WP,
            Product::class,
        )->id;
        $composition->mail_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK,
            Product::class,
        )->id;
        $composition->web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_PLUS,
            Product::class,
        )->id;
        $composition->wp_web_only_product_id = $this->referenceRepo->get(
            ProductReference::HOSTING_WEB_ONLY_PLUS_WP,
            Product::class,
        )->id;
        $composition->save();
    }

    private function provisioningHosting(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Provisioning hosting';
        $product->slug = 'provisioning-hosting';
        $product->description = 'A provision hosting product within the new Provision Domain.';
        $product->orderable = true;
        $product->weight = 1337;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::HOSTING_PROVISIONING, $product);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT->value,
                'value' => true,
                'product_id' => $product->id,
            ],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => true, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1999);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::HOSTING_PROVISIONING_REGISTRATION_PRICE, $defaultPrice);
    }
}
