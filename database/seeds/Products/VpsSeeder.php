<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Models\ProductAllowedChange;
use Waterfront\Domain\Voucher\Enum\VoucherAmountType;
use Waterfront\Domain\Voucher\Models\Voucher;
use Waterfront\Domain\VPS\Models\Environment;

class VpsSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        // Unlike other products, there are multiple VPS product groups.
        $this->productGroups();
        $this->environments();

        // Service offerings
        $this->vpsCloud2();
        $this->vpsCloud5();
        $this->vpsCloud10();
        $this->vpsCloud20WithVoucher();

        // Operating systems
        $this->ubuntu();
        $this->ubuntuWithSSH();
        $this->windows();

        $this->osReinstallPaths();
    }

    private function productGroups(): void
    {
        $vpsGroup = new ProductGroup();
        $vpsGroup->uuid = Str::uuid()->toString();
        $vpsGroup->name = 'VPS';
        $vpsGroup->ledger_code = 0;
        $vpsGroup->slug = ProductGroupType::VPS;
        $vpsGroup->default_billing_period = 1;
        $vpsGroup->default_contract_period = 1;
        $vpsGroup->save();
        $this->referenceRepo->set(ProductReference::VPS_GROUP, $vpsGroup);

        $osGroup = new ProductGroup();
        $osGroup->uuid = Str::uuid()->toString();
        $osGroup->name = 'VPS OS';
        $osGroup->ledger_code = 0;
        $osGroup->slug = ProductGroupType::CLOUDSTACK_OS;
        $osGroup->default_billing_period = 1;
        $osGroup->default_contract_period = 1;
        $osGroup->save();
        $this->referenceRepo->set(ProductReference::VPS_GROUP_OS, $osGroup);
    }

    private function environments(): void
    {
        $environment = new Environment();
        $environment->slug = 'HAARLEM';
        $environment->name = 'Versio Haarlem';
        $environment->api_url = 'http://mock:3000/cloudstack/';
        $environment->ui_url = 'https://man.auroracompute.eu/ams3/ui';
        $environment->domain_id = '35071d7e-9fda-4af0-a1c2-791caf015e13';
        $environment->domain_name = 'versio-staging/vps';
        $environment->preferred = true;
        $environment->default_email_address = 'support@versio.nl';
        $environment->default_role_id = '84378d4c-b299-4877-94c0-64a6883ae2da';
        $environment->api_key = 'api_key';
        $environment->secret_key = 'secret_key';
        $environment->save();
        $this->referenceRepo->set(ProductReference::VPS_ENVIRONMENT_HAARLEM, $environment);

        $environment = new Environment();
        $environment->slug = 'AMSTERDAM';
        $environment->name = 'Versio Amsterdam';
        $environment->api_url = 'http://mock:3000/cloudstack/';
        $environment->ui_url = 'https://man.auroracompute.eu/ams3/ui';
        $environment->domain_id = Str::uuid()->toString();
        $environment->domain_name = 'versio-fake/vps';
        $environment->preferred = true;
        $environment->default_email_address = 'support@versio.nl';
        $environment->default_role_id = Str::uuid()->toString();
        $environment->api_key = 'api_key';
        $environment->secret_key = 'secret_key';
        $environment->save();
        $this->referenceRepo->set(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, $environment);
    }

    private function vpsCloud2(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP, ProductGroup::class);
        $haarlemEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);
        $amsterdamEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'VPS Cloud II';
        $product->slug = 'vps-cloud-2';
        $product->description = 'VPS Cloud II';
        $product->orderable = true;
        $product->weight = 61;
        $product->product_group_id = $group->id;
        $product->save();
        $haarlemEnvironment->products()->attach($product->id, ['product_identifier' => '15393ee6-ab0c-45ec-80dc-a0eb33b68a3a']);
        $amsterdamEnvironment->products()->attach($product->id, ['product_identifier' => 'ab0c3ee6-80dc-ec0a-15qy-j6se92l69c8w']);
        $this->referenceRepo->set(ProductReference::VPS_CLOUD_2, $product);

        ProductSpec::insert([
            ['name' => 'vps.cpu', 'value' => 2, 'product_id' => $product->id],
            ['name' => 'vps.memory', 'value' => 2, 'product_id' => $product->id],
            ['name' => 'vps.storage', 'value' => 80, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::VPS_CLOUD_2_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function vpsCloud5(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP, ProductGroup::class);
        $amsterdamEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'VPS Cloud V';
        $product->slug = 'vps-cloud-5';
        $product->description = 'VPS Cloud V';
        $product->orderable = true;
        $product->weight = 62;
        $product->product_group_id = $group->id;
        $product->save();
        $amsterdamEnvironment->products()->attach($product->id, ['product_identifier' => 'b59aeb3a-b4f4-4766-8527-ddc663568c9b']);
        $this->referenceRepo->set(ProductReference::VPS_CLOUD_5, $product);

        ProductSpec::insert([
            ['name' => 'vps.cpu', 'value' => 4, 'product_id' => $product->id],
            ['name' => 'vps.memory', 'value' => 5, 'product_id' => $product->id],
            ['name' => 'vps.storage', 'value' => 200, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::VPS_CLOUD_5_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function vpsCloud10(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP, ProductGroup::class);
        $haarlemEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);
        $amsterdamEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'VPS Cloud X';
        $product->slug = 'vps-cloud-10';
        $product->description = 'VPS Cloud X';
        $product->orderable = true;
        $product->weight = 63;
        $product->product_group_id = $group->id;
        $product->save();
        $haarlemEnvironment->products()->attach($product->id, ['product_identifier' => '5da20ef5-f124-4814-9ecc-361fd5141a67']);
        $amsterdamEnvironment->products()->attach($product->id, ['product_identifier' => '361fd514-ab0c-45ec-80dc-5da20ef58a3a']);
        $this->referenceRepo->set(ProductReference::VPS_CLOUD_10, $product);

        ProductSpec::insert([
            ['name' => 'vps.cpu', 'value' => 6, 'product_id' => $product->id],
            ['name' => 'vps.memory', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'vps.storage', 'value' => 320, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 5999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::VPS_CLOUD_10_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 5999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function vpsCloud20WithVoucher(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP, ProductGroup::class);
        $haarlemEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'VPS Cloud XX';
        $product->slug = 'vps-cloud-20';
        $product->description = 'VPS Cloud 20';
        $product->orderable = true;
        $product->weight = 64;
        $product->product_group_id = $group->id;
        $product->save();
        $haarlemEnvironment->products()->attach($product->id, ['product_identifier' => '15393ee6-ab0c-45ec-80dc-a0eb33b68a3a']);
        $this->referenceRepo->set(ProductReference::VPS_CLOUD_20, $product);

        ProductSpec::insert([
            ['name' => 'vps.cpu', 'value' => 10, 'product_id' => $product->id],
            ['name' => 'vps.memory', 'value' => 20, 'product_id' => $product->id],
            ['name' => 'vps.storage', 'value' => 750, 'product_id' => $product->id],
            ['name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD, 'value' => true, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 11999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::VPS_CLOUD_20_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 11999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $voucher = new Voucher();
        $voucher->uuid = Str::uuid()->toString();
        $voucher->amount_type = VoucherAmountType::FIXED;
        $voucher->amount = 499;
        $voucher->product_group_uuid = $group->uuid;
        $voucher->product_uuid = $product->uuid;
        $voucher->internal_name = 'VPS Cloud XX Redundant Marketing Campaign';
        $voucher->display_name = 'VPS Cloud XX Redundant voucher';
        $voucher->description = 'A voucher for the awesome Marketing Campaign';
        $voucher->code = 'thisisnowverycheap';
        $voucher->apply_with_discount = false;
        $voucher->allow_multiple_claims_same_customer = true;
        $voucher->save();
        $this->referenceRepo->set(ProductReference::VPS_CLOUD_20_VOUCHER, $voucher);
    }

    private function ubuntu(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP_OS, ProductGroup::class);
        $haarlemEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);
        $amsterdamEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Ubuntu 24.04';
        $product->slug = 'ubuntu-24.04';
        $product->description = 'Ubuntu OS';
        $product->orderable = true;
        $product->weight = 65;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG->value, 'value' => 'Ubuntu-24.04', 'product_id' => $product->id],
        ]);

        $haarlemEnvironment->products()->attach($product->id, ['product_identifier' => Str::uuid()->toString()]);
        $amsterdamEnvironment->products()->attach($product->id, ['product_identifier' => Str::uuid()->toString()]);
        $this->referenceRepo->set(ProductReference::VPS_OS_UBUNTU, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::VPS_OS_UBUNTU_REGISTRATION_PRICE, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function ubuntuWithSSH(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP_OS, ProductGroup::class);
        $haarlemEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);
        $amsterdamEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Ubuntu 24.04 SSH';
        $product->slug = 'ubuntu-24-04-ssh';
        $product->description = 'Ubuntu OS With SSH Required';
        $product->orderable = true;
        $product->weight = 65;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => ProductSpecName::SSH_KEY_REQUIRED->value, 'value' => 1, 'product_id' => $product->id],
            ['name' => ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG->value, 'value' => 'Ubuntu-24.04', 'product_id' => $product->id],
        ]);

        $haarlemEnvironment->products()->attach($product->id, ['product_identifier' => Str::uuid()->toString()]);
        $amsterdamEnvironment->products()->attach($product->id, ['product_identifier' => Str::uuid()->toString()]);
        $this->referenceRepo->set(ProductReference::VPS_OS_UBUNTU_SSH_REQUIRED, $product);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function windows(): void
    {
        $group = $this->referenceRepo->get(ProductReference::VPS_GROUP_OS, ProductGroup::class);
        $haarlemEnvironment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);

        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Windows';
        $product->slug = 'windows';
        $product->description = 'Windows OS';
        $product->orderable = true;
        $product->weight = 66;
        $product->product_group_id = $group->id;
        $product->save();
        $haarlemEnvironment->products()->attach($product->id, ['product_identifier' => Str::uuid()->toString()]);
    }

    private function osReinstallPaths(): void
    {
        $osProducts = [
            $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU, Product::class),
            $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_SSH_REQUIRED, Product::class),
        ];

        $displayOrder = 1;
        foreach ($osProducts as $fromProduct) {
            foreach ($osProducts as $toProduct) {
                $reinstall = new ProductAllowedChange();
                $reinstall->from_product_id               = $fromProduct->id;
                $reinstall->to_product_id                 = $toProduct->id;
                $reinstall->change_type                   = ProductChangeType::REINSTALL;
                $reinstall->is_available_for_customer     = true;
                $reinstall->display_order                 = $displayOrder++;
                $reinstall->save();
            }
        }
    }
}
