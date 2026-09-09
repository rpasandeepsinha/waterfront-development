<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Models\ProvisioningResult;

/**
 * @extends Factory<ProvisioningResult>
 */
class ProvisioningResultFactory extends Factory
{
    protected $model = ProvisioningResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4(),
            'response' => '{}',
            'status' => $this->faker->randomElement(ProvisionStatus::class),
        ];
    }

    public function success(): self
    {
        return $this->state([
           'status' => ProvisionStatus::SUCCESS,
        ]);
    }

    public function failed(): self
    {
        return $this->state([
           'status' => ProvisionStatus::FAILED,
        ]);
    }

    public function validationError(): self
    {
        return $this->state([
            'status' => ProvisionStatus::VALIDATION_ERROR,
        ]);
    }

    public function retrying(): self
    {
        return $this->state([
           'status' => ProvisionStatus::RETRYING,
        ]);
    }

    public function deleted(): self
    {
        return $this->state([
           'status' => ProvisionStatus::DELETED,
        ]);
    }

    public function deleting(): self
    {
        return $this->state([
           'status' => ProvisionStatus::DELETING,
        ]);
    }

    public function deletion_failed(): self
    {
        return $this->state([
           'status' => ProvisionStatus::DELETION_FAILED,
        ]);
    }

    public function pending(): self
    {
        return $this->state([
           'status' => ProvisionStatus::PENDING,
        ]);
    }
}
