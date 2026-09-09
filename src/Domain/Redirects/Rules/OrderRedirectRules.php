<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Rules;

use Closure;
use Waterfront\Domain\Domains\Rules\DomainNameRule;

class OrderRedirectRules
{
    /**
     * @return array<string, array<int, (Closure)|string|DomainNameRule>>
     */
    public function getRedirectRules(): array
    {
        return [
            'subscriptions.redirect.*.domain' => ['required'],
        ];
    }
}
