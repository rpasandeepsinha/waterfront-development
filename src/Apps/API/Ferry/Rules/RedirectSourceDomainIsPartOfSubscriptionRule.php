<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Infra\Common\PublicSuffixList;

class RedirectSourceDomainIsPartOfSubscriptionRule implements ValidationRule
{
    public function __construct(
        private readonly Customer $customer,
        private readonly PublicSuffixList $publicSuffixList,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            ! is_string($value)
        ) {
            // The source needs to be an url
            $fail('The source field needs to be a valid string representing a domain. "example.domain.com"');
            return;
        }

        $domain = $this->publicSuffixList
            ->getRules()
            ->resolve($value);

        $registrableDomain = $domain
            ->registrableDomain()
            ->toString();

        $foundMatchingDomainToSource = $this->customer
            ->subscriptions()
            ->whereHas('product.productGroup', function ($query) {
                $query->where('slug', ProductGroupType::REDIRECT);
            })
            ->where('domain', $registrableDomain)
            ->exists();

        if (! $foundMatchingDomainToSource) {
            $fail("The source field with value: $value has no representation as a redirect subscription domain: $registrableDomain");
        }
    }
}
