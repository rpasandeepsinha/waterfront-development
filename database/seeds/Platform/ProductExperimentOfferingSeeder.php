<?php

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Database\Seeders\Products\ProductReference;
use Database\Seeders\Scenarios\ScenarioReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductExperimentOfferings;

class ProductExperimentOfferingSeeder extends Seeder
{
    public function __construct(private readonly ReferenceRepository $referenceRepo)
    {
    }

    public function run(): void
    {
        $this->groupWithFreeAcronis();
        $this->groupWithPaidAcronis();
    }

    private function groupWithFreeAcronis(): void
    {
        $offering = new ProductExperimentOfferings();
        $offering->name = 'Security bundle - premium DNS and Acronis free';
        $offering->product_1_id = $this->referenceRepo->get(ProductReference::DNS_PREMIUM, Product::class)->id;
        $offering->product_1_free = true;
        $offering->product_2_id = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_50, Product::class)->id;
        $offering->product_2_free = true;
        $offering->save();

        $offering->customers()->attach(
            $this->referenceRepo->get(ScenarioReference::TEST_KEES, Customer::class)->id
        );

        $this->referenceRepo->set(PlatformReference::PRODUCT_EXPERIMENT_OFFERING_SECURITY_BUNDLE_ALL_FREE, $offering);
    }

    private function groupWithPaidAcronis(): void
    {
        $offering = new ProductExperimentOfferings();
        $offering->name = 'Security bundle - premium DNS free, Acronis paid';
        $offering->product_1_id = $this->referenceRepo->get(ProductReference::DNS_PREMIUM, Product::class)->id;
        $offering->product_1_free = true;
        $offering->product_2_id = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_50, Product::class)->id;
        $offering->product_2_free = false;
        $offering->save();

        $offering->customers()->attach(
            $this->referenceRepo->get(ScenarioReference::DISCOUNT_KEES, Customer::class)->id
        );

        $this->referenceRepo->set(PlatformReference::PRODUCT_EXPERIMENT_OFFERING_SECURITY_BUNDLE_ACRONIS_PAID, $offering);
    }
}
