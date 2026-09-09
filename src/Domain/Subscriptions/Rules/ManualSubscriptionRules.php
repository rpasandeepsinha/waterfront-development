<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Rules;

use Illuminate\Validation\Rule;
use Waterfront\Domain\Products\Enums\ProductPriceType;

class ManualSubscriptionRules
{
    /**
     * @return array<mixed>
     */
    public function getManualSubscriptionRules(): array
    {
        return [
            'subscriptions.manual-subscription.*.status' => [Rule::in([ProductPriceType::REGISTRATION])],
        ];
    }
}
