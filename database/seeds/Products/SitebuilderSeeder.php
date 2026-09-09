<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Database\Seeders\Support\ProductPriceGenerator;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Products\Enums\ProductSpecName;
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
use Waterfront\Infra\Configuration\ConfigurationInterface;

class SitebuilderSeeder extends Seeder
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        /**
         * A site builder product is implemented as 'hosting' in the code. This should be refactored
         * so it is a standalone product with its own group. Within the seeders we decided to separate
         * the two as much as possible.
         */
        $group = $this->referenceRepo->get(ProductReference::HOSTING_GROUP, ProductGroup::class);

        $this->baseKit($group);
        $this->baseKitShopProduct($group);

        $this->servers();
        $this->providers();
        $this->sitebuilderUpgradePaths();
    }

    private function baseKit(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Sitebuilder';
        $product->slug = 'sitebuilder';
        $product->description = 'Bouw eenvoudig je website zonder technische kennis';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::SITEBUILDER_BASEKIT, $product);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1999);

        $defaultPrice = $prices->where('contract_period', 12)->where('billing_period', 1)->firstOrFail();

        $this->referenceRepo->set(ProductReference::SITEBUILDER_BASEKIT_REGISTRATION_PRICE, $defaultPrice);

        ProductSpec::insert([
            ['name' => ProductSpecName::HOSTING_SSL_SOLD_SEPARATELY, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_USES_MAIL_ONLY_SERVER, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::HOSTING_HAS_WEBSITE, 'value' => false, 'product_id' => $product->id],
            ['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE, 'value' => 9620, 'product_id' => $product->id],
        ]);
    }

    private function baseKitShopProduct(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Sitebuilder webshop';
        $product->slug = 'basekit-shop';
        $product->description = 'Bouw eenvoudig jouw webshop zonder technische kennis';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::SITEBUILDER_SHOP_BASEKIT, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::BASEKIT_PACKAGE_REFERENCE, 'value' => 9621, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 2999);

        $defaultPrice = $prices->where('contract_period', 12)->where('billing_period', 1)->firstOrFail();

        $this->referenceRepo->set(ProductReference::SITEBUILDER_SHOP_BASEKIT_REGISTRATION_PRICE, $defaultPrice);
    }

    private function servers(): void
    {
        $mockHostname = $this->configuration->getAsString('app.mock_hostname');

        // Needs to use SSL or else the (auto-login) POST doesn't work in mockoon
        $server = new Server();
        $server->type = ServerType::SITEBUILDER;
        $server->name = ServerType::SITEBUILDER->value;
        $server->hostname = "https://$mockHostname/basekit/";
        $server->domain = $mockHostname;
        $server->ipv4 = '127.0.0.1';
        $server->ipv6 = '::1';
        $server->port = 443;
        $server->use_ssl = true;
        $server->allow_new_websites = true;
        $server->maximum_websites = 9999;
        $server->username = 'admin';
        $server->password = 'changeme';
        $server->save();
        $this->referenceRepo->set(ProductReference::SITEBUILDER_SERVER, $server);
    }

    private function providers(): void
    {
        $provider = new Provider();
        $provider->type = ProviderType::SITEBUILDER;
        $provider->slug = ProviderSlug::BASEKIT;
        $provider->enabled = true;
        $provider->default = true;
        $provider->save();

        $serverReference = $this->referenceRepo->get(ProductReference::SITEBUILDER_SERVER, Server::class);

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::BRANDREFERENCE;
        $providerSetting->value = '123';
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::PACKAGEREFERENCE;
        $providerSetting->value = '456';
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $providerSetting = new ProviderSetting();
        $providerSetting->key = ProviderSettingKey::DEFAULTSERVERID;
        $providerSetting->value = (string) $serverReference->id;
        $providerSetting->provider_id = $provider->id;
        $providerSetting->save();

        $this->referenceRepo->set(ProductReference::SITEBUILDER_PROVIDER, $provider);

        $provider = new Provider();
        $provider->type = ProviderType::SITEBUILDER;
        $provider->slug = ProviderSlug::PLACEHOLDER;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();

        $this->referenceRepo->set(ProductReference::SITEBUILDER_PROVIDER_PLACEHOLDER, $provider);
    }

    private function sitebuilderUpgradePaths(): void
    {
        $fromProduct = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $toProduct = $this->referenceRepo->get(ProductReference::SITEBUILDER_SHOP_BASEKIT, Product::class);

        $upgrade = new ProductAllowedChange();
        $upgrade->from_product_id = $fromProduct->id;
        $upgrade->to_product_id = $toProduct->id;
        $upgrade->change_type = ProductChangeType::UPGRADE;
        $upgrade->is_available_for_customer = true;
        $upgrade->display_order = 1;
        $upgrade->save();
    }
}
