<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'product_uuid' => Uuid::uuid4(),
            'domain' => $this->faker->domainName(),
            'start_date' => CarbonImmutable::now(),
            'billing_period' => 12,
            'contract_period' => 12,
            // The end_date & next_billing_date will be set automatically by the SubscriptionObserver.
            'next_billing_date' => null,
            'end_date' => null,
            'cancel_date' => null,
            'gross_price' => $this->faker->numberBetween(100, 5000),
            'net_price' => $this->faker->numberBetween(100, 5000),
        ];
    }

    public function withCustomer(): self
    {
        return $this->state(fn (): array => [
            'customer_id' => new CustomerFactory()->createOne()->id,
        ]);
    }

    public function withPrice(): self
    {
        return $this->afterCreating(function (Subscription $subscription): void {
            $price = new SubscriptionPriceFactory()->for($subscription)->createOne();
            $subscription->subscription_price_id = $price->id;
            $subscription->save();
        });
    }

    public function forDomain(string $domain): self
    {
        return $this->state(fn (): array => [
            'domain' => $domain,
        ]);
    }

    public function administrativeStatus(string $administrativeStatus): SubscriptionFactory
    {
        return $this->state(fn (array $attributes): array => [
            'administrative_status' => $administrativeStatus,
        ]);
    }

    public function administrativeStatusActive(): SubscriptionFactory
    {
        return $this->administrativeStatus(AdministrativeStatus::ACTIVE->value);
    }

    public function administrativeStatusCancelled(): SubscriptionFactory
    {
        return $this->state(fn (array $attributes): array => [
            'administrative_status' => AdministrativeStatus::CANCELED->value,
            'cancel_date' => CarbonImmutable::now(),
        ]);
    }

    public function administrativeStatusArchived(): SubscriptionFactory
    {
        return $this->administrativeStatus(AdministrativeStatus::ARCHIVED->value);
    }

    public function administrativeStatusArchiving(): SubscriptionFactory
    {
        return $this->administrativeStatus(AdministrativeStatus::ARCHIVING->value);
    }

    public function administrativeStatusSuspended(): SubscriptionFactory
    {
        return $this->administrativeStatus(AdministrativeStatus::SUSPENDED->value);
    }

    public function administrativeStatusInactive(): SubscriptionFactory
    {
        return $this->administrativeStatus(AdministrativeStatus::INACTIVE->value);
    }

    public function administrativeStatusExpired(): SubscriptionFactory
    {
        return $this->administrativeStatus(AdministrativeStatus::EXPIRED->value);
    }

    public function technicalStatus(string|bool|null $technicalStatus): self
    {
        return $this->state(fn (array $attributes): array => [
            'technical_status' => $technicalStatus,
        ]);
    }

    public function technicalStatusOk(): SubscriptionFactory
    {
        return $this->technicalStatus(TechnicalStatus::OK->value);
    }

    public function technicalStatusPending(): SubscriptionFactory
    {
        return $this->technicalStatus(TechnicalStatus::PENDING->value);
    }

    public function technicalStatusDomainActive(): SubscriptionFactory
    {
        return $this->technicalStatus(DomainStatus::ACTIVE->value);
    }

    public function parentSubscription(Subscription $subscription): self
    {
        return $this->state(fn (): array => [
            'parent_subscription_id' => $subscription->id,
        ]);
    }
}
