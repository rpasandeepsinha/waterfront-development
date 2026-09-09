<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

class SslRules
{
    /**
     * @return array<mixed>
     */
    public function getSslRules(): array
    {
        return ['subscriptions.ssl.*.domain' => ['required']];
    }
}
