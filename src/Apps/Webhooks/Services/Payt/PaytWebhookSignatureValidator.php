<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\Services\Payt;

use SensitiveParameter;

class PaytWebhookSignatureValidator
{
    public function validate(string $body, string $signature, #[SensitiveParameter] string $secret): bool
    {
        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
