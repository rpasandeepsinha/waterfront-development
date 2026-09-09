<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\DTO\OfferedProductDTO;
use Waterfront\Domain\Products\Models\ProductExperimentOfferings;

class ProductExperimentOfferingRepository
{
    public function findOfferingForCustomer(Customer $customer): ?ProductExperimentOfferings
    {
        return ProductExperimentOfferings::query()
            ->whereHas('customers', fn ($query) => $query->where('customers.id', $customer->id))
            ->with(['productOne', 'productTwo'])
            ->first();
    }

    public function findOfferedProductBySlug(ProductExperimentOfferings $offering, string $slug): ?OfferedProductDTO
    {
        foreach ($this->getOfferedProducts($offering) as $offered) {
            if ($offered->product->slug === $slug) {
                return $offered;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function getOfferedProductSlugs(ProductExperimentOfferings $offering): array
    {
        return array_map(
            static fn (OfferedProductDTO $offered): string => $offered->product->slug,
            $this->getOfferedProducts($offering)
        );
    }

    /**
     * The redemption sits on the row linking this customer to this offering, not on the offering itself:
     * the same offering is handed to everyone in the experiment group.
     */
    public function isRedeemedByCustomer(ProductExperimentOfferings $offering, Customer $customer): bool
    {
        $redeemedAt = DB::table('product_experiment_offerings_customers')
            ->where('product_experiment_offering_id', $offering->id)
            ->where('customer_id', $customer->id)
            ->value('redeemed_at');

        return $redeemedAt !== null;
    }

    public function markRedeemedByCustomer(ProductExperimentOfferings $offering, Customer $customer): void
    {
        DB::table('product_experiment_offerings_customers')
            ->where('product_experiment_offering_id', $offering->id)
            ->where('customer_id', $customer->id)
            ->update(['redeemed_at' => CarbonImmutable::now()]);
    }

    /**
     * @return list<OfferedProductDTO>
     */
    private function getOfferedProducts(ProductExperimentOfferings $offering): array
    {
        return [
            new OfferedProductDTO($offering->productOne, $offering->product_1_free),
            new OfferedProductDTO($offering->productTwo, $offering->product_2_free),
        ];
    }
}
