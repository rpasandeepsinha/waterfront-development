<?php

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Waterfront\Domain\Experiment\Enums\ExperimentType;
use Waterfront\Domain\Experiment\Models\Experiment;

class ExperimentSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo
    ) {
    }

    public function run(): void
    {
        $this->priceLadderExperiment();
    }

    public function priceLadderExperiment(): void
    {
        $experiment = new Experiment();
        $experiment->slug = ExperimentType::PRICING_LADDER;
        $experiment->save();

        $this->referenceRepo->set(PlatformReference::EXPERIMENT_PRICE_LADDER, $experiment);
    }
}
