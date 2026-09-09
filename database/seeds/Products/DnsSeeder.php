<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsRegion;
use Waterfront\Domain\DNS\Models\DnsTemplate;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSet;
use Waterfront\Domain\DNS\Models\DnsTemplateRecordSetRow;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Enums\ProductType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;

class DnsSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'DNS';
        $group->slug = ProductGroupType::DNS;
        $group->ledger_code = 8011;
        $group->default_contract_period = 36;
        $group->default_billing_period = 1;
        $group->save();

        $this->free($group);
        $this->premium($group);

        $this->templates();
        $this->nameServers();
        $this->vanityNameServers();
        $this->productChangePaths();
    }

    private function premium(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Premium DNS';
        $product->slug = 'premium-dns';
        $product->description = 'Premium DNS';
        $product->orderable = true;
        $product->weight = 4;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::DNS_PREMIUM, $product);

        ProductSpec::insert([
            ['name' => ProductSpecName::DNS_VISIBLE_LOG_LINES->value, 'value' => 5000, 'product_id' => $product->id],
            ['name' => ProductSpecName::DNS_CAN_EDIT_RECORDS->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::DNS_IS_PREMIUM->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_DOWNGRADE_WHEN_CANCELED->value, 'value' => ProductType::BASIC_DNS->value, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_COMPARISON_BADGE, 'value' => 'pages.steps.cross-sell.hosting.most-popular', 'product_id' => $product->id],
        ]);
    }

    private function free(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Gratis DNS';
        $product->slug = 'free-dns';
        $product->description = 'Gratis DNS';
        $product->orderable = true;
        $product->weight = 1;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::DNS_FREE, $product);

        ProductSpec::insert([
            ['name' => 'product.product-should-be-hidden-in-shoppingcart', 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::DNS_VISIBLE_LOG_LINES->value, 'value' => 0, 'product_id' => $product->id],
            ['name' => ProductSpecName::DNS_CAN_EDIT_RECORDS->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT->value, 'value' => true, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_MERGE_INVOICE_INTO_PARENT->value, 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function templates(): void
    {
        // This will be applied during the creation of a new DNS zone.
        $template = new DnsTemplate();
        $template->slug = 'default';
        $template->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'NS';
        $set->ttl = 3600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ns1}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ns2}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ns3}.';
        $row->template_record_set_id = $set->id;
        $row->save();

        $set = new DnsTemplateRecordSet();
        $set->name = '{domain}';
        $set->type = 'SOA';
        $set->ttl = 3600;
        $set->template_id = $template->id;
        $set->save();

        $row = new DnsTemplateRecordSetRow();
        $row->content = '{ns1}. domain-admin.sandwave.testing. 1539941638 3600 600 86400 3600';
        $row->template_record_set_id = $set->id;
        $row->save();
    }

    private function nameServers(): void
    {
        $region = new DnsRegion();
        $region->name = 'Amsterdam';
        $region->save();

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns1.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();
        $this->referenceRepo->set(ProductReference::DNS_NAMESERVER_1, $nameServer);

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns2.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns3.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();

        $region = new DnsRegion();
        $region->name = 'Haarlem';
        $region->save();

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns4.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();
        $this->referenceRepo->set(ProductReference::DNS_NAMESERVER_2, $nameServer);

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns5.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns6.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();

        $region = new DnsRegion();
        $region->name = 'Ede';
        $region->save();

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns7.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();
        $this->referenceRepo->set(ProductReference::DNS_NAMESERVER_3, $nameServer);

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns8.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();

        $nameServer = new DnsNameserver();
        $nameServer->nameserver = 'ns9.sandwaveio.dev';
        $nameServer->dns_region_id = $region->id;
        $nameServer->save();
    }

    private function vanityNameServers(): void
    {
        $nameserver = new DnsVanityNameserver();
        $nameserver->nameserver = 'ns1.vanitytld-test.nl';
        $nameserver->save();
        $this->referenceRepo->set(ProductReference::DNS_VANITY_NAMESERVER_1, $nameserver);

        $nameserver = new DnsVanityNameserver();
        $nameserver->nameserver = 'ns2.vanitytld-test.nl';
        $nameserver->save();
        $this->referenceRepo->set(ProductReference::DNS_VANITY_NAMESERVER_2, $nameserver);

        $nameserver = new DnsVanityNameserver();
        $nameserver->nameserver = 'ns3.vanitytld-test.nl';
        $nameserver->save();
        $this->referenceRepo->set(ProductReference::DNS_VANITY_NAMESERVER_3, $nameserver);
    }

    private function productChangePaths(): void
    {
        $legacyProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $premiumProduct = $this->referenceRepo->get(ProductReference::DNS_PREMIUM, Product::class);

        $legacyToPremiumUpgrade = new ProductAllowedChange();
        $legacyToPremiumUpgrade->from_product_id = $legacyProduct->id;
        $legacyToPremiumUpgrade->to_product_id = $premiumProduct->id;
        $legacyToPremiumUpgrade->change_type = ProductChangeType::UPGRADE;
        $legacyToPremiumUpgrade->is_available_for_customer = true;
        $legacyToPremiumUpgrade->display_order = 1;
        $legacyToPremiumUpgrade->save();
    }
}
