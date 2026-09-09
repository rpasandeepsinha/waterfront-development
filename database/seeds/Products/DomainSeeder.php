<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Products;

use Carbon\CarbonImmutable;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Models\OpenproviderProviderCredentials;
use Waterfront\Domain\Domains\Models\RtrProviderCredentials;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductIntroductionDiscount;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPromotionPlatform;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPeriod;
use Waterfront\Domain\Products\Models\ProductPromotion;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Translations\Models\TranslationKey;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;

class DomainSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo,
    ) {
    }

    public function run(): void
    {
        $group = new ProductGroup();
        $group->uuid = Str::uuid()->toString();
        $group->name = 'Domein';
        $group->ledger_code = 8007;
        $group->slug = ProductGroupType::EXTENSION;
        $group->default_contract_period = 12;
        $group->default_billing_period = 12;
        $group->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_GROUP, $group);

        $this->providers();
        $this->businessUnits();
        $this->providerCredentials();

        // See https://adac.realtimeregister.com/tlds/categories/ (YH UAT)

        // popular-1
        $this->nl($group);
        $this->com($group);
        $this->eu($group);
        $this->de($group);
        $this->be($group);

        // popular-2
        $this->tech($group);
        $this->fun($group);
        $this->net($group);
        $this->org($group);
        $this->info($group);

        // popular-3
        $this->nu($group);
        $this->online($group);
        $this->site($group);
        $this->store($group);
        $this->club($group);

        // europe
        $this->fr($group);
        $this->it($group);
        $this->coUk($group);

        // international-1
        $this->pm($group);
        $this->re($group);
        $this->tf($group);

        // international-2
        $this->yt($group);
        $this->tv($group);

        // misc
        $this->biz($group);
        $this->name($group);

        // ProductDiscount
        $this->wf($group);

        $this->placeholder($group);

        $this->premiumDomain($group);
    }

    private function nl(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.nl';
        $product->slug = 'extension_nl';
        $product->description = '.nl Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_NL, $product);

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'no', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $priceExplanation = new TranslationKey();
        $priceExplanation->key = 'extension_nl.registration-12-12.description';
        $priceExplanation->source = 'atlantis';
        $priceExplanation->save();

        $nlTranslation = new TranslationString();
        $nlTranslation->language_id = TranslationLanguage::query()->where('locale', 'nl')->firstOrFail()->id;
        $nlTranslation->key_id = $priceExplanation->id;
        $nlTranslation->translated_string = 'Registreer of verhuis de eerste 5 .NL domeinnamen voor € 0,49. Meer .NL domeinnamen registreer je voor € 3,99. Na verlenging betaal je het reguliere tarief van € 24,99.';
        $nlTranslation->save();

        $enTranslation = new TranslationString();
        $enTranslation->language_id = TranslationLanguage::query()->where('locale', 'en')->firstOrFail()->id;
        $enTranslation->key_id = $priceExplanation->id;
        $enTranslation->translated_string = 'Register or tranfer your first 5 .NL domains for € 0,49. Register more .nl domains for € 3,99. After renewal, you will pay the original price of € 24,99.';
        $enTranslation->save();

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->price = 1000;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->type = PriceComponentType::PROMOTION;
        $price->expires_at = null;
        $price->save();

        $productPromotion = new ProductPromotion();
        $productPromotion->product_id = $product->id;
        $productPromotion->uuid = Uuid::uuid4();
        $productPromotion->platform = ProductPromotionPlatform::CUSTOMER_PANEL;
        $productPromotion->placement_url = '/dashboard';
        $productPromotion->call_to_action = [
            'title' => 'pages.dashboard.promotions.example.title',
            'description' => 'pages.dashboard.promotions.example.description',
            'price_description' => 'pages.dashboard.promotions.example.price_description',
            'button_text' => 'pages.dashboard.promotions.example.button_text',
            'destination_url' => 'https://atlantis.sandwaveio.dev',
        ];
        $productPromotion->weight = 3;
        $productPromotion->start_date = CarbonImmutable::today()->subMonth();
        $productPromotion->end_date = CarbonImmutable::today()->addMonths(6);
        $productPromotion->save();
    }

    private function be(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.be';
        $product->slug = 'extension_be';
        $product->description = '.be Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_BE, $product);

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 0, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'no', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $priceDataList = [
            ['contract_period' => 12, 'billing_period' => 12, 'regular_price' => 3299, 'promotion_price' => 499, 'is_default' => true],
            ['contract_period' => 24, 'billing_period' => 24, 'regular_price' => 5298, 'promotion_price' => 998, 'is_default' => false],
            ['contract_period' => 36, 'billing_period' => 36, 'regular_price' => 6897, 'promotion_price' => 1497, 'is_default' => false],
        ];

        foreach ($priceDataList as $priceData) {
            $productPeriod = ProductPeriod::where('product_id', $product->id)
                ->where('billing_period', $priceData['billing_period'])
                ->where('contract_period', $priceData['contract_period'])
                ->firstOrNew();

            $productPeriod->product_id = $product->id;
            $productPeriod->billing_period = $priceData['billing_period'];
            $productPeriod->contract_period = $priceData['contract_period'];
            $productPeriod->is_default = $priceData['is_default'];
            $productPeriod->save();

            $price = new ProductPriceComponent();
            $price->type = PriceComponentType::REGISTRATION;
            $price->product_id = $product->id;
            $price->contract_period = $priceData['contract_period'];
            $price->billing_period = $priceData['billing_period'];
            $price->price = $priceData['regular_price'];
            $price->orderable = true;
            $price->starts_at = CarbonImmutable::now();
            $price->save();

            $promotionPrice = new ProductPriceComponent();
            $promotionPrice->product_id = $product->id;
            $promotionPrice->type = PriceComponentType::PROMOTION;
            $promotionPrice->contract_period = $priceData['contract_period'];
            $promotionPrice->billing_period = $priceData['billing_period'];
            $promotionPrice->price = $priceData['promotion_price'];
            $promotionPrice->starts_at = CarbonImmutable::now();
            $promotionPrice->orderable = true;
            $promotionPrice->save();

            if ($priceData['is_default']) {
                $this->referenceRepo->set(ProductReference::DOMAIN_BE_REGISTRATION_PRICE, $price);
            }

            $price = new ProductPriceComponent();
            $price->type = PriceComponentType::REGISTRATION;
            $price->product_id = $product->id;
            $price->contract_period = $priceData['contract_period'];
            $price->billing_period = $priceData['billing_period'];
            $price->price = $priceData['regular_price'];
            $price->orderable = true;
            $price->starts_at = CarbonImmutable::now();
            $price->save();
        }

        $productPromotion = new ProductPromotion();
        $productPromotion->product_id = $product->id;
        $productPromotion->uuid = Uuid::uuid4();
        $productPromotion->platform = ProductPromotionPlatform::CUSTOMER_PANEL;
        $productPromotion->placement_url = '/dashboard';
        $productPromotion->call_to_action = [
            'title' => 'pages.dashboard.promotions.example.title',
            'description' => 'pages.dashboard.promotions.example.description',
            'price_description' => 'pages.dashboard.promotions.example.price_description',
            'button_text' => 'pages.dashboard.promotions.example.button_text',
            'destination_url' => 'https://atlantis.sandwaveio.dev',
        ];
        $productPromotion->weight = 3;
        $productPromotion->start_date = CarbonImmutable::today()->subMonth();
        $productPromotion->end_date = CarbonImmutable::today()->addMonths(6);
        $productPromotion->save();
    }

    private function com(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.com';
        $product->slug = 'extension_com';
        $product->description = '.com Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_COM, $product);

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'no', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $price = new ProductPriceComponent();
        $price->product_id = $product->id;
        $price->price = 1000;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->type = PriceComponentType::PROMOTION;
        $price->expires_at = null;
        $price->save();

        $promotion = new ProductPromotion();
        $promotion->product_id = $product->id;
        $promotion->uuid = Uuid::uuid4();
        $promotion->platform = ProductPromotionPlatform::CUSTOMER_PANEL;
        $promotion->placement_url = '/dashboard';
        $promotion->call_to_action = [
            'title' => 'pages.dashboard.promotions.example.title',
            'description' => 'pages.dashboard.promotions.example.description',
            'price_description' => 'pages.dashboard.promotions.example.price_description',
            'button_text' => 'pages.dashboard.promotions.example.button_text',
            'destination_url' => 'https://atlantis.sandwaveio.dev',
        ];
        $promotion->weight = 3;
        $promotion->start_date = CarbonImmutable::today()->subMonth();
        $promotion->end_date = CarbonImmutable::today()->addMonths(6);
        $promotion->save();
    }

    private function eu(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.eu';
        $product->slug = 'extension_eu';
        $product->description = '.eu Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_EU, $product);

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'no', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);

        $introductionPrice = new ProductIntroductionDiscount();
        $introductionPrice->product_id = $product->id;
        $introductionPrice->contract_period = 12;
        $introductionPrice->max_uses_per_customer = 5;
        $introductionPrice->save();
    }

    private function de(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.de';
        $product->slug = 'extension_de';
        $product->description = '.de Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
        ]);
    }

    private function tech(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.tech';
        $product->slug = 'extension_tech';
        $product->description = '.tech Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function fun(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.fun';
        $product->slug = 'extension_fun';
        $product->description = '.fun Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function net(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.net';
        $product->slug = 'extension_net';
        $product->description = '.net Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function org(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.org';
        $product->slug = 'extension_org';
        $product->description = '.org Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function info(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.info';
        $product->slug = 'extension_info';
        $product->description = '.info Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function nu(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.nu';
        $product->slug = 'extension_nu';
        $product->description = '.nu Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function online(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.online';
        $product->slug = 'extension_online';
        $product->description = '.online Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function site(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.site';
        $product->slug = 'extension_site';
        $product->description = '.site Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function store(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.store';
        $product->slug = 'extension_store';
        $product->description = '.store Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function club(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.club';
        $product->slug = 'extension_club';
        $product->description = '.club Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function fr(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.fr';
        $product->slug = 'extension_fr';
        $product->description = '.fr Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        $this->referenceRepo->set(ProductReference::DOMAIN_FR, $product);

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function it(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.it';
        $product->slug = 'extension_it';
        $product->description = '.it Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function coUk(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.co.uk';
        $product->slug = 'extension_co.uk';
        $product->description = '.co.uk Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function pm(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.pm';
        $product->slug = 'extension_pm';
        $product->description = '.pm Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function re(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.re';
        $product->slug = 'extension_re';
        $product->description = '.re Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function tf(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.tf';
        $product->slug = 'extension_tf';
        $product->description = '.tf Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function wf(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.wf';
        $product->slug = 'extension_wf';
        $product->description = '.wf Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);

        $discount = new ProductDiscount();
        $discount->name = '.wf domain reseller';
        $discount->description = '.wf domain reseller';
        $discount->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_RESELLER_DISCOUNT, $discount);
    }

    private function yt(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.yt';
        $product->slug = 'extension_yt';
        $product->description = '.yt Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function tv(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.tv';
        $product->slug = 'extension_tv';
        $product->description = '.tv Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function biz(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.biz';
        $product->slug = 'extension_biz';
        $product->description = '.biz Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function name(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = '.name';
        $product->slug = 'extension_name';
        $product->description = '.name Domein';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();

        ProductSpec::insert([
            ['name' => 'domain.allow_whois', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.allow_whois_private', 'value' => 'support', 'product_id' => $product->id],
            ['name' => 'domain.dnssec_enabled', 'value' => 1, 'product_id' => $product->id],
            ['name' => 'domain.provider_id', 'value' => $this->getDefaultProviderId(), 'product_id' => $product->id],
            ['name' => 'services.technical_grace_period', 'value' => 30, 'product_id' => $product->id],
            ['name' => 'services.tld-has-zonecheck', 'value' => true, 'product_id' => $product->id],
        ]);
    }

    private function placeholder(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Domain Placeholder Product';
        $product->slug = 'extension_dev';
        $product->description = '.dev Domein dat de placeholder driver gebruikt';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
    }

    private function premiumDomain(ProductGroup $group): void
    {
        $product = new Product();
        $product->uuid = Str::uuid()->toString();
        $product->name = 'Premium Domain (papier.club)';
        $product->slug = 'extension_premium_papier_club';
        $product->description = 'Premium Domain (papier.club)';
        $product->orderable = true;
        $product->weight = 9999;
        $product->product_group_id = $group->id;
        $product->save();
    }

    private function providers(): void
    {
        $rtrProvider = new Provider();
        $rtrProvider->type = ProviderType::DOMAIN;
        $rtrProvider->slug = ProviderSlug::REALTIME_REGISTER;
        $rtrProvider->enabled = true;
        $rtrProvider->default = true;
        $rtrProvider->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_PROVIDER_RTR, $rtrProvider);
        $this->referenceRepo->set(ProductReference::DOMAIN_PROVIDER_DEFAULT, $rtrProvider);

        $placeholderProvider = new Provider();
        $placeholderProvider->type = ProviderType::DOMAIN;
        $placeholderProvider->slug = ProviderSlug::PLACEHOLDER;
        $placeholderProvider->enabled = true;
        $placeholderProvider->default = false;
        $placeholderProvider->save();
        $this->referenceRepo->set(ProductReference::DOMAIN_PROVIDER_PLACEHOLDER, $placeholderProvider);

        $opProvider = new Provider();
        $opProvider->type = ProviderType::DOMAIN;
        $opProvider->slug = ProviderSlug::OPEN_PROVIDER;
        $opProvider->enabled = false;
        $opProvider->default = false;
        $opProvider->save();
    }

    private function getDefaultProviderId(): int
    {
        return new ProviderRepository()->getEnabledDefaultByType(ProviderType::DOMAIN)->id;
    }

    private function businessUnits(): void
    {
        $wfBusinessUnit = new DomainProviderBusinessUnit();
        $wfBusinessUnit->name = 'Waterfront';
        $wfBusinessUnit->slug = 'waterfront';
        $wfBusinessUnit->save();

        $argeWebBusinessUnit = new DomainProviderBusinessUnit();
        $argeWebBusinessUnit->name = 'Argeweb';
        $argeWebBusinessUnit->slug = 'argeweb';
        $argeWebBusinessUnit->save();

        $this->referenceRepo->set(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_WATERFRONT, $wfBusinessUnit);
        $this->referenceRepo->set(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_ARGEWEB, $argeWebBusinessUnit);
    }

    private function providerCredentials(): void
    {
        $wfBusinessUnit = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_WATERFRONT, DomainProviderBusinessUnit::class);
        $argeWebBusinessUnit = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_ARGEWEB, DomainProviderBusinessUnit::class);

        $wfRtrProviderCredentials = new RtrProviderCredentials();
        $wfRtrProviderCredentials->api_url = 'http://mock:3000/rtr/';
        $wfRtrProviderCredentials->api_key = 'api_key';
        $wfRtrProviderCredentials->handle = 'waterfront';
        $wfRtrProviderCredentials->domain_business_unit_id = $wfBusinessUnit->id;
        $wfRtrProviderCredentials->save();

        $argeWebRtrProviderCredentials = new RtrProviderCredentials();
        $argeWebRtrProviderCredentials->api_url = 'http://mock:3000/rtr/';
        $argeWebRtrProviderCredentials->api_key = 'api_key';
        $argeWebRtrProviderCredentials->handle = 'argeweb';
        $argeWebRtrProviderCredentials->domain_business_unit_id = $argeWebBusinessUnit->id;
        $argeWebRtrProviderCredentials->save();

        $wfOpProviderCredentials = new OpenproviderProviderCredentials();
        $wfOpProviderCredentials->api_url = 'http://mock:3000/openprovider';
        $wfOpProviderCredentials->username = 'username';
        $wfOpProviderCredentials->password = 'password';
        $wfOpProviderCredentials->domain_business_unit_id = $wfBusinessUnit->id;
        $wfOpProviderCredentials->save();

        $argeWebOpProviderCredentials = new OpenproviderProviderCredentials();
        $argeWebOpProviderCredentials->api_url = 'http://mock:3000/openprovider';
        $argeWebOpProviderCredentials->username = 'username';
        $argeWebOpProviderCredentials->password = 'password';
        $argeWebOpProviderCredentials->domain_business_unit_id = $argeWebBusinessUnit->id;
        $argeWebOpProviderCredentials->save();
    }
}
