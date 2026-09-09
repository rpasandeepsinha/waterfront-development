<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class NonMigratedSubscriptionAlreadyExists implements DataAwareRule, ValidationRule
{
    /**
     * @var array<mixed>
     */
    protected array $data = [];

    /**
     * @param array<mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subscriptionData = $this->getSubscriptionData($attribute);

        if ($subscriptionData === null) {
            // Shouldn't really happen though.
            $fail('Attribute :$attribute not found in subscription data');
            return;
        }

        /** @var string|null $domain */
        $domain = Arr::get($subscriptionData, 'domain');

        if ($domain === null) {
            // Nothing to check if it doesn't have a domain.
            return;
        }

        /** @var string|null $productSlug */
        $productSlug = Arr::get($subscriptionData, 'slug');

        if ($productSlug === null) {
            // An empty product slug is checked in the "slug" field validation.
            return;
        }

        $existingSubscription = Subscription::query()
            ->whereNotIn('administrative_status', AdministrativeStatus::administrativelyEnded())
            ->whereProductSlug($productSlug)
            ->where('domain', $domain)
            ->whereDoesntHave('migratedSubscriptions')
            ->first();

        if ($existingSubscription instanceof Subscription) {
            $fail(sprintf(
                "An active subscription that is not part of any migration was found for product '%s' and domain '%s' (found subscription id: %d)",
                $productSlug,
                $domain,
                $existingSubscription->id,
            ));
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSubscriptionData(string $attribute): array|null
    {
        $attributeExploded = explode('.', $attribute);
        array_pop($attributeExploded);
        $subscriptionAttribute = implode('.', $attributeExploded);

        /** @var array<string, mixed>|null $subscriptionData */
        $subscriptionData = Arr::get($this->data, $subscriptionAttribute);

        return $subscriptionData;
    }
}
