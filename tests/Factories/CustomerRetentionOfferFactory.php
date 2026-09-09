<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;

/**
 * @extends Factory<CustomerRetentionOffer>
 */
class CustomerRetentionOfferFactory extends Factory
{
    protected $model = CustomerRetentionOffer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $customerType */
        $customerType = $this->faker->randomElement(array_column(CustomerType::cases(), 'value'));

        /** @var string $selectedAction */
        $selectedAction = $this->faker->randomElement(array_column(SelectedAction::cases(), 'value'));

        return [
            'created_by_metadata' => new IdentityMetadataDTO(
                uuid: Uuid::uuid4(),
                email: $this->faker->email(),
            ),
            'customer_type' => $customerType,
            'subscription_id' => null,
            'selected_action' => $selectedAction,
            'puzzel_ticket_id' => $this->faker->numerify('######'),
            'effective_at' => CarbonImmutable::now(),
        ];
    }

    public function withSubscription(): self
    {
        return $this->state(fn (): array => [
            'subscription_id' => SubscriptionFactory::new()->withCustomer(),
        ]);
    }

    public function customerType(CustomerType $customerType): self
    {
        return $this->state(fn (): array => [
            'customer_type' => $customerType->value,
        ]);
    }

    public function selectedAction(SelectedAction $selectedAction): self
    {
        return $this->state(fn (): array => [
            'selected_action' => $selectedAction->value,
        ]);
    }

    public function withSubscriptionMutation(): self
    {
        return $this->state(fn (): array => [
            'subscription_mutation_id' => SubscriptionMutationFactory::new(),
        ]);
    }
}
