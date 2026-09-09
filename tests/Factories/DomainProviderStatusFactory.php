<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Domains\Models\DomainProviderStatus;
use Waterfront\Infra\RtrClient\Enums\SubjectStatusType;

/**
 * @extends Factory<DomainProviderStatus>
 */
class DomainProviderStatusFactory extends Factory
{
    protected $model = DomainProviderStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => SubjectStatusType::OK->value,
            'received_result' => $this->faker->text(),
        ];
    }
}
