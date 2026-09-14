<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCancelType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ProductShouldDowngradeCancelType implements ValidationRule, DataAwareRule
{
    protected array $data = []; // @phpstan-ignore-line comes from laravel request so can't type it

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(':attribute should be a valid SubscriptionChangeType');

            return;
        }

        // Get array key from multi data attributes in Laravel
        preg_match('/subscriptions\.(\d+)\.cancel_type/', $attribute, $matches);

        if (count($matches) === 0) {
            $fail('Could not retrieve cancel_type array key from :attribute');

            return;
        }

        $index = intval($matches[1]);
        $cancelType = SubscriptionCancelType::tryFrom($value);

        if ($cancelType === null) {
            $fail(':attribute should be a valid SubscriptionChangeType');

            return;
        }

        /**
         * @var array{subscriptions: array{
         *     0: array{
         *         type: string,
         *         uuid: string,
         *         cancel: bool,
         *         cancel_type: string
         *     }
         * }} $data
         */
        $data = $this->data;
        $subscriptionUuid = $data['subscriptions'][$index]['uuid'];

        $subscription = Subscription::where('uuid', $subscriptionUuid)->with('product.productSpecs')->first();
        if ($subscription === null) {
            $fail('Invalid subscription uuid in request data.');

            return;
        }

        if ($data['subscriptions'][$index]['cancel'] === false) {
            return;
        }

        $spec = $subscription->product->productSpecs->where('name', 'product.downgrade_when_cancelled')->first();

        if (
            $spec !== null
            && $spec->value === '1'
            && $cancelType !== SubscriptionCancelType::CANCEL_DOWNGRADE
            && $cancelType !== SubscriptionCancelType::CANCEL_END_DATE
        ) {
            $fail(
                sprintf(
                    "Can't cancel subscription with uuid [%s] that should downgrade or be cancelled at end date",
                    $subscriptionUuid,
                ),
            );
        }
    }

    // @phpstan-ignore-next-line Laravel interface implementation
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
