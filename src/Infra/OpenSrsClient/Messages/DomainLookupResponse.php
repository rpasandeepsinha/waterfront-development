<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Infra\OpenSrsClient\Exceptions\OpenSrsResultException;

class DomainLookupResponse extends BaseResponse
{
    /** Domain is available for registration. */
    private const CODE_AVAILABLE = 210;

    /** Domain is already registered. */
    private const CODE_TAKEN = 211;

    /**
     * @throws OpenSrsResultException when the lookup itself failed (bad TLD, auth, registry down, ...)
     */
    public function getResult(string $domain): CheckResult
    {
        $code = $this->getResponseCode();

        // For a lookup both "available" (210) and "taken" (211) come back with is_success = 1;
        // any other code that is not a success is a genuine failure.
        if ($code !== self::CODE_AVAILABLE && $code !== self::CODE_TAKEN && ! $this->isSuccess()) {
            throw new OpenSrsResultException($this->getResponseText(), $code);
        }

        $attributes = $this->getAttributes();

        $status = match ($code) {
            self::CODE_AVAILABLE => CheckResult::STATUS_FREE,
            self::CODE_TAKEN => CheckResult::STATUS_ACTIVE,
            default => CheckResult::STATUS_UNKNOWN,
        };

        $reason = isset($attributes['reason']) && is_string($attributes['reason']) ? $attributes['reason'] : null;
        $isPremium = isset($attributes['reason']) && $attributes['reason'] === 'Premium Name';

        return new CheckResult($domain, $status, $reason, $isPremium ?: null);
    }
}
