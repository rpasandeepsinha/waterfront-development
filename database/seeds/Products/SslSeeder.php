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
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;

class SslSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'SSL';
        $group->slug = ProductGroupType::SSL;
        $group->ledger_code = 8010;
        $group->default_contract_period = 36;
        $group->default_billing_period = 1;
        $group->save();

        $this->extendedValidation($group);
        $this->wildcard($group);
        $this->singleDomain($group);
        $this->comodoPositiveSsl($group);
        $this->domainValidationSsl($group);
        $this->placeholder($group);

        $this->providers();
    }

    private function singleDomain(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Single Domain';
        $product->slug = 'ssl_single_domain';
        $product->description = 'single-domain';
        $product->weight = 30;
        $product->orderable = true;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::SSL_SINGLE_DOMAIN, $product);

        ProductSpec::insert([
            ['name' => 'ssl.product_id', 'value' => 31, 'product_id' => $product->id],
            [
                'name' => ProductSpecName::PRODUCT_COMPARISON_BADGE,
                'value' => 'pages.steps.cross-sell.hosting.most-popular',
                'product_id' => $product->id,
            ],
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
            ->where('type', PriceComponentType::PROLONGATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::SSL_SINGLE_DOMAIN_REGISTRATION_PRICE, $defaultPrice);
        $this->referenceRepo->set(ProductReference::SSL_SINGLE_DOMAIN_PROLONGATION_PRICE, $prolongationPrice);
    }

    private function domainValidationSsl(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Domain Validation SSL';
        $product->slug = 'Domain Validation SSL';
        $product->description = '';
        $product->weight = 30;
        $product->orderable = true;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'ssl.product_id', 'value' => 'ssl_sectigo', 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 399);
    }

    private function wildcard(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'wildcard';
        $product->slug = 'ssl_wildcard';
        $product->description = 'wildcard';
        $product->weight = 20;
        $product->orderable = true;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::SSL_WILDCARD, $product);

        ProductSpec::insert([
            ['name' => 'ssl.product_id', 'value' => 32, 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 899);
    }

    private function extendedValidation(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Extended Validation';
        $product->slug = 'ssl_extended_validation';
        $product->description = 'extended-validation';
        $product->weight = 10;
        $product->orderable = true;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::SSL_EXTENDED_VALIDATION, $product);

        ProductSpec::insert([
            ['name' => 'ssl.product_id', 'value' => 24, 'product_id' => $product->id],
        ]);

        $prices = ProductPriceGenerator::generateStandardPrices($product, 1599);
        $defaultPrice = $prices
            ->where('contract_period', 12)
            ->where('billing_period', 1)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();

        $this->referenceRepo->set(ProductReference::SSL_EXTENDED_VALIDATION_REGISTRATION_PRICE, $defaultPrice);
    }

    private function comodoPositiveSsl(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Comodo PositiveSSL';
        $product->slug = 'ssl-certificaten_comodo_positivessl';
        $product->description = 'SSL certificaat voor één domeinnaam, inclusief het "www" subdomein.';
        $product->weight = 30;
        $product->orderable = true;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'ssl.product_id', 'value' => 'ssl_sectigo', 'product_id' => $product->id],
        ]);

        ProductPriceGenerator::generateStandardPrices($product, 1199);
    }

    private function placeholder(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'SSL placeholder product';
        $product->slug = 'ssl_placeholder';
        $product->description = 'SSL product that uses the placeholder driver';
        $product->weight = 999;
        $product->orderable = false;
        $product->product_group_id = $group->id;
        $product->save();

        $registrationPrice = new ProductPriceComponent();
        $registrationPrice->type = PriceComponentType::REGISTRATION;
        $registrationPrice->product_id = $product->id;
        $registrationPrice->contract_period = 12;
        $registrationPrice->billing_period = 12;
        $registrationPrice->price = 799;
        $registrationPrice->orderable = true;
        $registrationPrice->starts_at = CarbonImmutable::now();
        $registrationPrice->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 1699;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::SSL_PLACEHOLDER, $product);
        $this->referenceRepo->set(ProductReference::SSL_PLACEHOLDER_REGISTRATION_PRICE, $registrationPrice);
    }

    private function providers(): void
    {
        $provider = new Provider();
        $provider->type = ProviderType::SSL;
        $provider->slug = ProviderSlug::OPEN_PROVIDER;
        $provider->enabled = true;
        $provider->default = true;
        $provider->save();

        $provider = new Provider();
        $provider->type = ProviderType::SSL;
        $provider->slug = ProviderSlug::REALTIME_REGISTER;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();
        $this->referenceRepo->set(ProductReference::SSL_PROVIDER_RTR, $provider);

        $provider = new Provider();
        $provider->type = ProviderType::SSL;
        $provider->slug = ProviderSlug::PLACEHOLDER;
        $provider->enabled = true;
        $provider->default = false;
        $provider->save();

        $this->referenceRepo->set(ProductReference::SSL_PROVIDER_PLACEHOLDER, $provider);
    }
}
