<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

use Database\Seeders\Products\ProductReference;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Models\Product;

class ProductPriceReferenceSeeder
{
    public function __construct(private readonly ReferenceRepository $referenceRepository)
    {
    }

    public function run(): void
    {
        $this->referenceRepository->set(ProductReference::DNS_FREE_REGISTRATION_PRICE, $this->findProductPrice('free-dns', 12, 12));
        $this->referenceRepository->set(ProductReference::DNS_PREMIUM_REGISTRATION_PRICE, $this->findProductPrice('premium-dns', 12, 12));

        $this->referenceRepository->set(ProductReference::DOMAIN_FR_REGISTRATION_PRICE, $this->findProductPrice('extension_fr', 12, 12));
        $this->referenceRepository->set(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, $this->findProductPrice('extension_nl', 12, 12));
        $this->referenceRepository->set(ProductReference::DOMAIN_COM_REGISTRATION_PRICE, $this->findProductPrice('extension_com', 12, 12));
        $this->referenceRepository->set(ProductReference::DOMAIN_EU_REGISTRATION_PRICE, $this->findProductPrice('extension_eu', 12, 12));

        $this->referenceRepository->set(ProductReference::REDIRECT_REGISTRATION_PRICE, $this->findProductPrice('redirect', 12, 12));
        $this->referenceRepository->set(ProductReference::SSL_WILDCARD_REGISTRATION_PRICE, $this->findProductPrice('ssl_wildcard', 12, 12));
    }

    private function findProductPrice(string $productSlug, int $contractPeriod, int $billingPeriod): ProductPriceComponent
    {
        $product = Product::where(['slug' => $productSlug])->firstOrFail();
        return ProductPriceComponent::where(['product_id' => $product->id])
            ->where('billing_period', $billingPeriod)
            ->where('contract_period', $contractPeriod)
            ->where('type', PriceComponentType::REGISTRATION)
            ->firstOrFail();
    }
}
