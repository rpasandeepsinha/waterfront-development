<?php

declare(strict_types=1);

namespace Database\Seeders\Products;

use Database\Seeders\Support\ProductPriceGenerator;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Servers\Models\LegacyRedirectingServer;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class RedirectSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Redirect';
        $group->slug = ProductGroupType::REDIRECT;
        $group->ledger_code = 8011;
        $group->default_contract_period = 36;
        $group->default_billing_period = 36;
        $group->save();

        $this->freeRedirect($group);
        $this->caddyRedirect($group);
        $this->legacyDatabasePaidRedirect($group);
        $this->paidRedirect($group);
        $this->upgrades();

        $this->legacyRedirectingServers();
    }

    private function freeRedirect(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Free redirect';
        $product->slug = 'free-redirect';
        $product->description = 'Free Redirect';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
    }

    private function caddyRedirect(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Caddy redirect';
        $product->slug = 'caddy-redirect';
        $product->description = 'Caddy Redirect';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::REDIRECT_CADDY_REDIRECT, $product);

        ProductPriceGenerator::generateStandardPrices($product, 99, false);
    }

    private function legacyDatabasePaidRedirect(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Legacy Redirect';
        $product->slug = 'legacy-redirect';
        $product->description = 'Legacy Redirect';
        $product->orderable = true;
        $product->weight = 10;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::USES_LEGACY_REDIRECT_DATABASE,
                'value' => true,
                'product_id' => $product->id,
            ],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 99, false);
    }

    private function paidRedirect(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Redirect';
        $product->slug = 'redirect';
        $product->description = 'Redirect';
        $product->orderable = true;
        $product->weight = 10;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::REDIRECT_PAID_REDIRECT, $product);
    }

    private function upgrades(): void
    {
        $redirectProduct = $this->referenceRepo->get(ProductReference::REDIRECT_PAID_REDIRECT, Product::class);

        $upgradeDestinations = [
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC_WP, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW_WP, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW_WP, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START_WP, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_START_PLESK, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_START, Product::class),
            $this->referenceRepo->get(ProductReference::HOSTING_WEB_START_WP, Product::class),
        ];

        foreach ($upgradeDestinations as $upgradeDestination) {
            $upgrade = new ProductAllowedChange();
            $upgrade->from_product_id = $redirectProduct->id;
            $upgrade->to_product_id = $upgradeDestination->id;
            $upgrade->change_type = ProductChangeType::UPGRADE;
            $upgrade->is_available_for_customer = true;
            $upgrade->display_order = 1;
            $upgrade->save();
        }
    }

    private function legacyRedirectingServers(): void
    {
        $server = new LegacyRedirectingServer();
        $server->hostname = 'fake-redirect-server.test';
        $server->ipv4 = '1.2.3.4';
        $server->ipv6 = '::2';
        $server->original_business_unit = 'hugo-host';
        $server->save();

        $server = new LegacyRedirectingServer();
        $server->hostname = 'example.original-server.com';
        $server->original_business_unit = 'another-legacy-business-unit';
        $server->ipv4 = '127.0.0.2';
        $server->save();
    }
}
