<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Microsoft365\Models\Microsoft365KpnProduct;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPeriod;
use Waterfront\Domain\Products\Models\ProductSpec;

class Microsoft365Seeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    /**
     * With Microsoft 365 we work with a parent child subscription model. The parent signifies a Microsoft subscription
     * and the child a Microsoft seat. The customer pays for the seat so the child will have a product price and the
     * parent has a price of 0. The main use case of the parent subscription is to make sure all the child
     * subscriptions are renewed at the same time, and to be able to invoice based on the remaining period until the
     * renewal date.
     */
    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Microsoft 365';
        $group->slug = ProductGroupType::MICROSOFT_365;
        $group->ledger_code = 8012;
        $group->save();

        $this->businessStandard($group);
        $this->appsForBusiness($group);
        $this->businessBasic($group);
        $this->oneDrivePlan1($group);
        $this->oneDrivePlan2($group);
        $this->exchangeOnlinePlan1($group);
        $this->exchangePlan2($group);
        $this->exchangeOnlineKiosk($group);
        $this->azureInformationProtectionPlan1($group);
        $this->powerBiPro($group);
        $this->sharepointPlan1($group);
        $this->visioPlan2($group);
        $this->projectPlan3($group);
        $this->office365E1($group);
        $this->office365E3($group);
        $this->copilot($group);
    }

    private function businessStandard(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Microsoft 365 Business Standard Administrative';
        $product->slug = 'microsoft-business-standard-parent';
        $product->description = 'Microsoft 365 Business Standard subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Microsoft 365 Business Standard';
        $seatProduct->slug = 'microsoft-business-standard';
        $seatProduct->description = 'Microsoft 365 Business Standard seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
            [
                'name' => ProductSpecName::MICROSOFT365_ALLOW_COPILOT->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00179B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00655B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1799;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 19188;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $seatProduct->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 14088;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $seatProduct->id;
        $price->type = PriceComponentType::PROLONGATION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 19188;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();
    }

    private function businessBasic(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Microsoft 365 Business Basic Administrative';
        $product->slug = 'microsoft-business-basic-parent';
        $product->description = 'Microsoft Microsoft 365 Business Basic subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Microsoft 365 Business Basic';
        $seatProduct->slug = 'microsoft-business-basic';
        $seatProduct->description = 'Microsoft Microsoft 365 Business Basic seat';
        $seatProduct->orderable = true;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->weight = 30;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
            [
                'name' => ProductSpecName::MICROSOFT365_ALLOW_COPILOT->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00178B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00742B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $productPeriod = new ProductPeriod();
        $productPeriod->product_id = $seatProduct->id;
        $productPeriod->contract_period = 12;
        $productPeriod->billing_period = 12;
        $productPeriod->action_period = 3;
        $productPeriod->action_period_price = 99;
        $productPeriod->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10788;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->product_id = $seatProduct->id;
        $price->type = PriceComponentType::PROMOTION;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 8088;
        $price->starts_at = CarbonImmutable::now();
        $price->orderable = true;
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10788;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function appsForBusiness(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Microsoft 365 Apps for Business Administrative';
        $product->slug = 'microsoft-apps-for-business-parent';
        $product->description = 'Microsoft 365 Apps for Business subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT, $product);

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Microsoft 365 Apps for Business';
        $seatProduct->slug = 'microsoft-apps-for-business';
        $seatProduct->description = 'Microsoft 365 Apps for Business seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 20;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();
        $this->referenceRepo->set(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD, $seatProduct);

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
            [
                'name' => ProductSpecName::MICROSOFT365_ALLOW_COPILOT->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(
            ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT_REGISTRATION_PRICE_MONTH,
            $price,
        );

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00180B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT_REGISTRATION_PRICE_YEAR, $price);

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00657B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1599;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD_REGISTRATION_PRICE_MONTH, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 17988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $this->referenceRepo->set(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD_REGISTRATION_PRICE_YEAR, $price);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 99;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function oneDrivePlan1(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Onedrive Plan 1 administrative';
        $product->slug = 'microsoft-onedrive-p1-parent';
        $product->description = 'Microsoft Onedrive Plan 1 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Onedrive Plan 1';
        $seatProduct->slug = 'microsoft-onedrive-p1';
        $seatProduct->description = 'Microsoft Onedrive Plan 1 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00621B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00674B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 899;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 10188;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function oneDrivePlan2(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Onedrive Plan 2 administrative';
        $product->slug = 'microsoft-onedrive-p2-parent';
        $product->description = 'Microsoft Onedrive Plan 2 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Onedrive Plan 2';
        $seatProduct->slug = 'microsoft-onedrive-p2';
        $seatProduct->description = 'Microsoft Onedrive Plan 2 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00622B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00675B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 16788;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function exchangeOnlinePlan1(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Exchange Online Plan 1 Administrative';
        $product->slug = 'microsoft-exchange-p1-parent';
        $product->description = 'Microsoft Exchange Plan 1 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Exchange Online Plan 1';
        $seatProduct->slug = 'microsoft-exchange-p1';
        $seatProduct->description = 'Microsoft Exchange Plan 1 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00611B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00668B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 999;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 9588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function exchangePlan2(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Exchange Plan 2 Administrative';
        $product->slug = 'microsoft-exchange-p2-parent';
        $product->description = 'Microsoft Exchange Plan 2 subscription';
        $product->orderable = false;
        $product->weight = 13;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Exchange Plan 2';
        $seatProduct->slug = 'microsoft-exchange-p2';
        $seatProduct->description = 'Microsoft Exchange Plan 2 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 14;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00612B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00669B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1299;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 14388;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function exchangeOnlineKiosk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Exchange Online Kiosk Administrative';
        $product->slug = 'microsoft-kiosk-parent';
        $product->description = 'Microsoft Exchange Online Kiosk subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Exchange Online Kiosk';
        $seatProduct->slug = 'microsoft-kiosk';
        $seatProduct->description = 'Microsoft Exchange Online Kiosk subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 30;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00613B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00667B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 799;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 7188;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function azureInformationProtectionPlan1(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Azure Information Protection Plan 1 Administrative';
        $product->slug = 'microsoft-azure-information-protection-p1-parent';
        $product->description = 'Azure Information Protection Plan 1 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Azure Information Protection Plan 1';
        $seatProduct->slug = 'microsoft-azure-information-protection-p1';
        $seatProduct->description = 'Azure Information Protection Plan 1 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00608B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00665B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1249;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 13788;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function powerBiPro(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Power BI Pro Administrative';
        $product->slug = 'microsoft-power-bi-pro-parent';
        $product->description = 'Power BI Pro subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Power BI Pro';
        $seatProduct->slug = 'microsoft-power-bi-pro';
        $seatProduct->description = 'Power BI Pro subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00216B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00676B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 16788;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function sharepointPlan1(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'SharePoint Plan 1 Administrative';
        $product->slug = 'microsoft-sharepoint-p1-parent';
        $product->description = 'SharePoint Plan 1 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'SharePoint Plan 1';
        $seatProduct->slug = 'microsoft-sharepoint-p1';
        $seatProduct->description = 'SharePoint Plan 1 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00626B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00672B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 799;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 8388;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function visioPlan2(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Visio Plan 2 Administrative';
        $product->slug = 'microsoft-visio-p2-parent';
        $product->description = 'Visio Plan 2 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Visio Plan 2';
        $seatProduct->slug = 'microsoft-visio-p2';
        $seatProduct->description = 'Visio Plan 2 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00231B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00684B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1849;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 20988;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function projectPlan3(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Project Plan 3 Administrative';
        $product->slug = 'microsoft-project-plan-3-parent';
        $product->description = 'Project Plan 3 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Project Plan 3';
        $seatProduct->slug = 'microsoft-project-plan-3';
        $seatProduct->description = 'Project Plan 3 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00624B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00682B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 3499;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 40788;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function office365E1(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Office 365 E1 Administrative';
        $product->slug = 'microsoft-office365-e1-parent';
        $product->description = 'Office 365 E1 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Office 365 E1';
        $seatProduct->slug = 'microsoft-office365-e1';
        $seatProduct->description = 'Office 365 E1 subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A004B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00682B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 1299;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 14388;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function office365E3(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Office 365 E3 Administrative';
        $product->slug = 'microsoft-office365-e3-parent';
        $product->description = 'Office 365 E3 subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Office 365 E3';
        $seatProduct->slug = 'microsoft-office365-e3';
        $seatProduct->description = 'Office 365 E3 subscription seat';
        $seatProduct->orderable = false;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
            [
                'name' => ProductSpecName::MICROSOFT365_ALLOW_COPILOT->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 1;
        $kpnProduct->kpn_product_code = '120A00185B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A00759B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 1;
        $price->billing_period = 1;
        $price->price = 2899;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 33588;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }

    private function copilot(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Microsoft 365 Copilot Administrative';
        $product->slug = 'microsoft-copilot-for-microsoft-365-parent';
        $product->description = 'Microsoft 365 Copilot subscription';
        $product->orderable = false;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $seatProduct = new Product();
        $seatProduct->uuid = Str::uuid()->toString();
        $seatProduct->name = 'Microsoft 365 Copilot';
        $seatProduct->slug = 'microsoft-copilot-for-microsoft-365';
        $seatProduct->description = 'Microsoft 365 Copilot subscription seat';
        $seatProduct->orderable = true;
        $seatProduct->weight = 9999;
        $seatProduct->product_group_id = $group->id;
        $seatProduct->save();

        ProductSpec::insert([
            [
                'name' => ProductSpecName::PRODUCT_ALLOW_CANCEL_AS_CHILD->value,
                'value' => true,
                'product_id' => $seatProduct->id,
            ],
        ]);

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $product->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 0;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        $kpnProduct = new Microsoft365KpnProduct();
        $kpnProduct->product_id = $product->id;
        $kpnProduct->contract_period = 12;
        $kpnProduct->kpn_product_code = '120A01070B';
        $kpnProduct->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::REGISTRATION;
        $price->product_id = $seatProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 14388;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();
    }
}
