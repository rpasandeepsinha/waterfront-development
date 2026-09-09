<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

class DnsRules
{
    /**
     * @return array<mixed>
     */
    public function getDnsRules(): array
    {
        return [
            'subscriptions.*.*.children.dns.*.domain' => 'required',
            'subscriptions.dns.*.domain' => 'required',
        ];
    }
}
