<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;

class DomainRegistrationResponse extends BaseResponse
{
    /** Registration completed synchronously. */
    private const CODE_SUCCESS = 200;

    /** Registration accepted but still processing at the registry. */
    private const CODE_ASYNC = 250;

    public function getResult(): RegistrationResult
    {
        $status = match ($this->getResponseCode()) {
            self::CODE_SUCCESS => DomainStatus::ACTIVE,
            self::CODE_ASYNC => DomainStatus::REQUESTED,
            default => DomainStatus::FAILED,
        };

        $result = new RegistrationResult($status);

        if ($status === DomainStatus::FAILED) {
            $result->setReason($this->reason());
        }

        return $result;
    }

    private function reason(): string
    {
        $attributes = $this->getAttributes();
        $error = isset($attributes['error']) && is_string($attributes['error']) ? $attributes['error'] : '';

        return trim($this->getResponseText() . ($error !== '' ? "\n" . $error : ''));
    }
}
