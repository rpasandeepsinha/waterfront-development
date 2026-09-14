<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Services\ManualMigration;

use Waterfront\Apps\API\Compass\Requests\ManualMigrationMigrateRequest;
use Waterfront\Apps\API\Compass\Requests\ManualMigrationValidateRequest;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Ferry\Enums\ManualMigrationDomainProvider;
use Waterfront\Domain\Products\DTO\PriceRequest;
use Waterfront\Domain\Products\DTO\ProlongationPriceRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Support\Exceptions\NotImplementedException;

readonly class SubscriptionFormatter
{
    public function __construct(
        private ProductRepository $productRepository,
        private PriceResolver $priceResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function formatFromRequest(
        ManualMigrationValidateRequest|ManualMigrationMigrateRequest $request,
        Customer $customer,
    ): array {
        $product = $this->productRepository->findProductByUuid($request->product_uuid);
        $priceRequest = new PriceRequest([new ProlongationPriceRequest($product)], $customer);
        $priceList = $this->priceResolver->getPriceList($priceRequest);
        $productPrice = $priceList->getProductPrice(
            $product->slug,
            $request->integer('contract_period'),
            $request->integer('billing_period'),
        );

        $data = [
            'domain' => $request->domain_name === '' ? null : $request->domain_name,
            'start_date' => $request->string('start_date')->toString(),
            'next_contract_date' => $request->string('end_date')->toString(),
            'next_billing_date' => $request->string('next_billing_date')->toString(),
            'contract_period' => $productPrice->contractPeriod,
            'billing_period' => $productPrice->billingPeriod,
            'slug' => $product->slug,
            'product_group_slug' => $product->productGroup->slug,
            'reference_product_id' => $request->string('reference_product_id')->toString(),
            'reference_subscription_id' => $request->reference_subscription_id,
        ];

        // Only for domain migrations that are not targeting the "default RTR" account we should fill in some info.
        if (
            $product->productGroup->slug === ProductGroupType::EXTENSION
            && $request->source_domain_provider !== null
            && $request->source_domain_provider !== ManualMigrationDomainProvider::RTRNEW->value
        ) {
            $data['reference_domain_provider_business_unit_slug'] = $request->source_business_unit;
            $data['driver'] = $request->source_domain_provider;
        }

        return match ($product->productGroup->slug) {
            ProductGroupType::EXTENSION => [
                ImplementableProducts::DOMAIN_EXTENSION->value => [
                    [
                        ...$data,
                        'extension' => $product->name,
                    ],
                ],
            ],
            ProductGroupType::HOSTING => [
                ImplementableProducts::HOSTING->value => [
                    [
                        ...$data,
                        'driver' => $request->provider,
                        'hostname' => $request->hostname,
                        'server_data' => $request instanceof ManualMigrationMigrateRequest
                            ? $this->formatServerDataFromRequest($request)
                            : [],
                    ],
                ],
            ],
            default => throw new NotImplementedException(),
        };
    }

    /**
     * @return array<string, string|int|null>
     */
    private function formatServerDataFromRequest(ManualMigrationMigrateRequest $request): array
    {
        return match ($request->provider) {
            ProviderSlug::DIRECTADMIN->value => [
                'directadmin_customer_name' => $request->username,
            ],
            ProviderSlug::PLESK->value => [
                'plesk_customer_id' => $request->plesk_customer_id,
                'plesk_customer_username' => $request->username,
            ],
            default => [],
        };
    }
}
